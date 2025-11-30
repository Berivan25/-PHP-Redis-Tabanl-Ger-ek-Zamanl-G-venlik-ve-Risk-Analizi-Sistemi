<?php
include "db.php";
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions/security.php';
init_user_session('page_view', ['url' => $_SERVER['REQUEST_URI']]);
check_security_status();


error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token(); 
    $username = $conn->real_escape_string($_POST['username'] ?? '');
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $errors = [];
    
    if (!$username || !$email || !$password) {
        $errors[] = "Lütfen tüm alanları doldurun.";
    }
         
    if (strlen($password) < 8) $errors[] = "Şifre en az 8 karakter olmalı.";
    if (!preg_match('/[A-Z]/', $password)) $errors[] = "Şifre en az bir büyük harf içermeli.";
    if (!preg_match('/[a-z]/', $password)) $errors[] = "Şifre en az bir küçük harf içermeli.";
    if (!preg_match('/\d/', $password)) $errors[] = "Şifre en az bir rakam içermeli.";
    if (!preg_match('/[\W_]/', $password)) $errors[] = "Şifre en az bir özel karakter içermeli.";

    if (empty($errors)) {
       
        $sql_check = "SELECT COUNT(*) as count FROM kullanici WHERE username = '$username' OR email = '$email'";
        $result_check = $conn->query($sql_check);
        $row = $result_check->fetch_assoc();

        if ($row['count'] > 0) {
            $error = "Bu kullanıcı adı veya e-posta zaten kayıtlı.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO kullanici (username, email, password) VALUES ('$username', '$email', '$hashed_password')";
            if ($conn->query($sql) === TRUE) {
                $success = "Kayıt başarılı! <a href='index.php'>Giriş Yap</a>";
            } else {
                $error = "Hata: " . $conn->error;
            }
        }
    } else {
       
        $error = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8" />
<title>Kayıt Ol</title>
<style>
    body {
        background: #f2f2f2;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100vh;
    }
    .register-container {
        background-color: white;
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        width: 360px;
    }
    h2 {
        text-align: center;
        margin-bottom: 20px;
    }
    label {
        font-weight: bold;
        display: block;
        margin-bottom: 6px;
    }
    input[type="text"],
    input[type="email"],
    input[type="password"],
    button {
        width: 100%;
        padding: 10px;
        margin-bottom: 16px;
        border-radius: 8px;
        border: 1px solid #ccc;
        box-sizing: border-box;
    }
    button {
        background-color: #0c63e4;
        color: white;
        border: none;
        font-size: 16px;
        cursor: pointer;
    }
    button:hover {
        background-color: #084bb5;
    }
    .error {
        color: red;
        margin-bottom: 16px;
        text-align: center;
    }
    .success {
        color: green;
        margin-bottom: 16px;
        text-align: center;
    }
</style>
</head>
<body>
<div class="register-container">
    <h2>Kayıt Ol</h2>
    <?php if (!empty($error)): ?>
        <p class="error"><?= $error ?></p>
    <?php elseif (!empty($success)): ?>
        <p class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <?php echo csrf_token_input(); ?>

        <label>Kullanıcı Adı:</label>
        <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES) ?>">

        <label>E-posta:</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>">

        <label>Şifre:</label>
        <input type="password" name="password" required>

        <button type="submit">Kayıt Ol</button>
    </form>
</div>
<script src="/app2/tracker.js"></script>
</body>
</html>


