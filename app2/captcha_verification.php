<?php
session_start();

require_once __DIR__ . '/functions/security.php';
require_once __DIR__ . '/functions/redis.php';

$redis = get_redis_connection();

function generate_captcha_text($length = 6) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $captcha_text = '';
    for ($i = 0; $i < $length; $i++) {
        $captcha_text .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $captcha_text;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['captcha_text'] = generate_captcha_text();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['captcha_code'])) {
    $user_input = trim($_POST['captcha_code']);
    $session_captcha = $_SESSION['captcha_text'] ?? '';

    if ($user_input === $session_captcha) {
        mark_captcha_passed();

        $user_id = $_GET['user_id'] ?? null;
    
    
    if ($user_id) {
        header('Location: /app2/login.php?captcha_passed=1&user_id=' . urlencode($user_id));
    } else {
        
        header('Location: /app2/index.php');
    }
    exit();
    } else {
        $error_message = "Hatalı CAPTCHA kodu. Lütfen tekrar deneyin.";
    
        $_SESSION['captcha_text'] = generate_captcha_text();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>CAPTCHA Doğrulaması</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #e9ecef;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            padding: 50px;
            max-width: 500px;
            text-align: center;
        }
        h1 {
            color: #495057;
            font-size: 2.2rem;
            margin-bottom: 10px;
        }
        p {
            font-size: 1.1em;
            color: #6c757d;
        }
        .captcha-box {
            background-color: #eef2f5;
            padding: 15px;
            border-radius: 8px;
            margin: 25px auto;
            border: 1px solid #dee2e6;
        }
        .captcha-text {
            font-family: 'Courier New', monospace;
            font-size: 1.5rem;
            font-weight: bold;
            color: #343a40;
            letter-spacing: 5px;
        }
        input[type="text"], input[type="submit"] {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 1em;
        }
        input[type="text"] {
            border: 1px solid #ced4da;
        }
        input[type="submit"] {
            background-color: #28a745;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        input[type="submit"]:hover {
            background-color: #218838;
        }
        .error {
            color: #dc3545;
            margin-top: 15px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Ben Robot Değilim</h1>
        <p>Devam etmek için lütfen aşağıdaki kodu girin:</p>
        <?php if ($error_message): ?>
            <p class="error"><?= htmlspecialchars($error_message) ?></p>
        <?php endif; ?>
        <form action="" method="post">
            <div class="captcha-box">
                <span class="captcha-text"><?php echo htmlspecialchars($_SESSION['captcha_text']); ?></span>
            </div>
            <input type="text" name="captcha_code" placeholder="Kodu Buraya Girin" required />
            <br />
            <input type="submit" value="Doğrula" />
        </form>
    </div>
</body>
</html>