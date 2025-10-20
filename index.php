<?php
// api.php
// Usage: https://yourdomain.com/api.php?search=3310605585987
// Output: JSON

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$FIXED_COOKIE = "ASP.NET_SessionId=uopq04m535zoaqefmryt4kgr";
$TIMEOUT = 120; // 2 minutes

/* ---------- CURL helper ---------- */
function curl_get($url, $headers = [], $timeout = 120) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (DomicileFetcher/1.0)');
    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok'=>$res!==false,'body'=>$res,'error'=>$err,'http_code'=>$code];
}

/* ---------- HTML extraction helpers ---------- */
function findNear($html, $label, $max = 200) {
    if (preg_match('/'.preg_quote($label,'/').'.{0,'.$max.'}?value="([^"]*)"/is',$html,$m)) return trim($m[1]);
    if (preg_match('/'.preg_quote($label,'/').'.{0,'.$max.'}?>([^<]+)</is',$html,$m)) return trim(strip_tags($m[1]));
    return '';
}
function findTextArea($html,$label){
    if (preg_match('/'.preg_quote($label,'/').'.{0,200}?<textarea[^>]*>(.*?)<\/textarea>/is',$html,$m)) return trim(strip_tags($m[1]));
    return '';
}
function findSelected($html,$label){
    if (preg_match('/'.preg_quote($label,'/').'.{0,200}?<option selected="selected"[^>]*>(.*?)<\/option>/is',$html,$m)) return trim($m[1]);
    return '';
}
function findImage($html){
    if (preg_match('/<img[^>]*id="ctl00_ContentPlaceHolder1_ImgCandidate"[^>]*src="([^"]+)"/i',$html,$m)){
        $src=$m[1];
        if(stripos($src,'http')===false) $src="https://domicile.punjab.gov.pk/".ltrim($src,'/');
        return $src;
    }
    return '';
}
function findGuardianType($html){
    if (preg_match('/rdoGuardian_[0-9]"[^>]*checked="checked"[^>]*value="([^"]+)"/i',$html,$m)) return trim($m[1]);
    return '';
}

/* ---------- Fetch domicile details by CAN ID ---------- */
function fetch_domicile_by_can($can, $cookie, $timeout) {
    $url = "https://domicile.punjab.gov.pk/Edit_Domicile.aspx?can_id=" . urlencode($can);
    $resp = curl_get($url, ["Cookie: $cookie"], $timeout);

    if (!$resp['ok'] || !$resp['body']) {
        return ['error' => "Failed to fetch domicile page", 'curl_error' => $resp['error'], 'http_code' => $resp['http_code']];
    }
    $html = $resp['body'];

    $data = [];
    $data['Full Name'] = findNear($html,'Full Name');
    $data['Guardian Type'] = findGuardianType($html);
    $guardian = findNear($html,'txtGuardianName');
    if ($guardian==='') $guardian = findNear($html,'Guardian Name');
    if ($guardian==='') $guardian = findNear($html,'Father');
    $data['Guardian Name'] = $guardian;
    $data['Address'] = findTextArea($html,'Address');
    $data['Place'] = findNear($html,'Place');
    $data['Tehsil'] = findSelected($html,'Tehsil');
    $data['Date of Arrival'] = findNear($html,'Date of Arrival');
    $data['Occupation'] = findNear($html,'Occupation');
    $data['Date of Birth'] = findNear($html,'Date of Birth');
    $data['Mark of Identification'] = findNear($html,'Mark of Identification');
    $data['NIC'] = findNear($html,'NIC No');
    $data['Marital Status'] = findSelected($html,'Marital Status');
    $data['Spouse Name'] = findNear($html,'txtSpouse');
    $data['Mobile Number'] = findNear($html,'Mobile Number');
    $data['Image'] = findImage($html);

    return ['data' => $data, 'http_code' => $resp['http_code']];
}

/* ---------- CNIC -> CAN ID via eLookup API ---------- */
function lookup_can_by_cnic($cnic) {
    $url = "https://elookup.xyz/CANIDdjdfjdsbfdjfsf/search.php?search=" . urlencode($cnic);
    $r = curl_get($url, [], 120);
    if (!$r['ok'] || !$r['body']) {
        return ['error' => "Failed to call CAN lookup API", 'curl_error'=>$r['error'], 'http_code'=>$r['http_code']];
    }
    $json = json_decode($r['body'], true);
    if (!$json || !isset($json['can_id'])) {
        return ['error' => "CAN ID not found in lookup response", 'response'=>$r['body']];
    }
    return ['can_id'=>$json['can_id'],'cnic'=>$json['cnic']];
}

/* ---------- Main ---------- */
$search = $_GET['search'] ?? '';
$search = trim($search);
if ($search === '') {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>"Missing 'search' parameter"], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    exit;
}

// if 13-digit CNIC, use eLookup
if (preg_match('/^\d{13}$/', $search)) {
    $lookup = lookup_can_by_cnic($search);
    if (isset($lookup['error'])) {
        echo json_encode(['status'=>'error','message'=>'CAN lookup failed','details'=>$lookup], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        exit;
    }
    $can_id = $lookup['can_id'];
    $cnic = $lookup['cnic'];

    $domicile = fetch_domicile_by_can($can_id, $FIXED_COOKIE, $TIMEOUT);
    if (isset($domicile['error'])) {
        echo json_encode(['status'=>'error','message'=>'Failed to fetch domicile info','details'=>$domicile], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'status'=>'success',
        'cnic'=>$cnic,
        'can_id'=>$can_id,
        'domicile'=>$domicile['data']
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
} else {
    // treat as CAN ID
    $domicile = fetch_domicile_by_can($search, $FIXED_COOKIE, $TIMEOUT);
    if (isset($domicile['error'])) {
        echo json_encode(['status'=>'error','message'=>'Failed to fetch domicile info','details'=>$domicile], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        exit;
    }
    echo json_encode([
        'status'=>'success',
        'can_id'=>$search,
        'domicile'=>$domicile['data']
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}
