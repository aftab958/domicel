<?php
$result = null;
$error = null;

if (isset($_POST['cnic']) && !empty(trim($_POST['cnic']))) {
    $cnic = trim($_POST['cnic']);
    $api_url = "http://localhost/?search=" . urlencode($cnic);

    // cURL request (1 minute timeout)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 seconds
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response) {
        $data = json_decode($response, true);
        if ($data && $data['status'] == 'success') {
            $result = $data;
        } else {
            $error = "No record found for this CNIC.";
        }
    } else {
        $error = "API Error: " . $curl_error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student CNIC Card</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
<style>
    body {
        font-family: 'Roboto', sans-serif;
        background: #e0f7fa;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        min-height: 100vh;
        padding: 50px 0;
    }
    .container {
        width: 400px;
        background: #fff;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    form {
        display: flex;
        margin-bottom: 20px;
    }
    input[type="text"] {
        flex: 1;
        padding: 10px;
        font-size: 16px;
        border-radius: 8px 0 0 8px;
        border: 1px solid #ccc;
        outline: none;
    }
    button {
        padding: 10px 20px;
        font-size: 16px;
        border: none;
        background: #26c6da;
        color: white;
        border-radius: 0 8px 8px 0;
        cursor: pointer;
        transition: background 0.3s;
    }
    button:hover {
        background: #00acc1;
    }
    .card {
        text-align: center;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 12px;
        background: #f9f9f9;
        animation: fadeIn 0.8s ease-in-out;
    }
    .card img {
        width: 120px;
        height: 140px;
        border-radius: 10px;
        object-fit: cover;
        margin-bottom: 15px;
        border: 2px solid #26c6da;
    }
    .card h2 {
        margin: 10px 0 5px;
        font-size: 20px;
        color: #007c91;
    }
    .card p {
        margin: 5px 0;
        font-size: 14px;
    }
    .error {
        color: red;
        margin-bottom: 15px;
        text-align: center;
    }
    @keyframes fadeIn { 0% { opacity: 0; transform: translateY(20px); } 100% { opacity:1; transform: translateY(0); } }
</style>
</head>
<body>
    <div class="container">
        <form method="post">
            <input type="text" name="cnic" placeholder="Enter CNIC" required>
            <button type="submit">Search</button>
        </form>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($result): 
            $d = $result['domicile'];
        ?>
            <div class="card">
                <img src="<?= htmlspecialchars($d['Image']) ?>" alt="Student Photo">
                <h2><?= htmlspecialchars($d['Full Name']) ?></h2>
                <p><strong>Father/Guardian:</strong> <?= htmlspecialchars($d['Guardian Name'] ?: 'N/A') ?></p>
                <p><strong>Address:</strong> <?= htmlspecialchars($d['Address']) ?></p>
                <p><strong>Tehsil:</strong> <?= htmlspecialchars($d['Tehsil']) ?></p>
                <p><strong>Date of Birth:</strong> <?= htmlspecialchars($d['Date of Birth']) ?></p>
                <p><strong>Occupation:</strong> <?= htmlspecialchars($d['Occupation']) ?></p>
                <p><strong>NIC:</strong> <?= htmlspecialchars($d['NIC']) ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
