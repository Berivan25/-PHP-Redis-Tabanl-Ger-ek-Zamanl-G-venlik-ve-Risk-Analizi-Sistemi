<?php
session_start();

include 'db.php';
require_once __DIR__ . '/functions/security.php';
require_once __DIR__ . '/functions/redis.php';
require_once __DIR__ . '/csrf.php';

$user_ip = get_client_ip();
$redis = get_redis_connection();


if (isset($_GET['captcha_passed']) && $_GET['captcha_passed'] === '1' && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];

   
    $stmt = $conn->prepare("SELECT id, username, role FROM kullanici WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
       
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        
        
        if ($redis) {
            $user_key = "{$user_ip}|{$user_id}";
            $redis->del("captcha:{$user_key}");
            $redis->del("failures:{$user_key}");
        }
        
       
        header('Location: /app2/index.php');
        exit();
    }
}


init_user_session('page_view', [
    'url' => $_SERVER['REQUEST_URI'],
    'username' => $_SESSION['username'] ?? 'guest'
]);

check_security_status();

$login_errors = [];

$fail_count = 0;

function check_block_or_captcha($redis, $ip, $user_id) {
    $user_key = "{$ip}|{$user_id}";
    if ($redis->exists("block:{$user_key}")) {
        return 'blocked';
    } elseif ($redis->exists("captcha:{$user_key}")) {
        return 'captcha';
    }
    return 'ok';
}

$identifier = $_POST['identifier'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $identifier = $conn->real_escape_string($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

   
    $stmt = $conn->prepare("SELECT id, username, email, password, role FROM kullanici WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $user_id = $user['id'] ?? null;
    $fail_count_key = null;
    if ($user_id && $redis) {
        $fail_count_key = "failures:$user_ip|$user_id";
        $fail_count = (int)$redis->get($fail_count_key);
    }
    
    
    if ($user_id && $redis) {
        $status = check_block_or_captcha($redis, $user_ip, $user_id);
        if ($status === 'blocked') {
            header('Location: /app2/blocked.php?type=user');
            exit();
        } elseif ($status === 'captcha') {
            header('Location: /app2/captcha_verification.php?user_id=' . $user_id);
            exit();
        }
    }

   
    if ($redis && $redis->sIsMember('blocked_ips', $user_ip)) {
        block_user("Çok fazla başarısız giriş nedeniyle IP adresiniz engellenmiştir.", 'ip');
    }

    
    if ($fail_count >= 3) {
        $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
        $recaptcha_secret = '6LfSQKIrAAAAAIUVDm_BbdoyeRpZps5B63NpojEf';
        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $verify_response = file_get_contents($verify_url . '?secret=' . urlencode($recaptcha_secret) . '&response=' . urlencode($recaptcha_response));
        $response_data = json_decode($verify_response, true);
        if (!$response_data['success']) {
            $login_errors[] = 'CAPTCHA doğrulaması başarısız.';
        }
    }
    
    if (empty($login_errors)) {
        if ($user && password_verify($password, $user['password'])) {
           
            if ($user_id && $redis) {
                $redis->del("failures:$user_ip|$user_id");
                $redis->sRem('blocked_ips', $user_ip);
                $redis->del("blocked_ip_ttl:$user_ip");
                $redis->del("block:$user_ip|$user_id"); 
            }

            calculate_risk_score($redis, $user_ip, $user_id);

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['last_login_attempt_identifier'] = $user['username'];

            if ($redis) {
                $redis->hSet('usernames', $user['id'], $user['username']);
                $redis->hSet('usernames_reverse', $user['username'], $user['id']);
                $redis->hSet('active_users', $user['id'], json_encode([
                    'username' => $user['username'],
                    'ip' => $user_ip,
                    'last_seen' => time(),
                    'status' => 'Aktif'
                ]));
            }

            header('Location: /app2/index.php');
            exit();
        } else {
            $login_errors[] = 'Kullanıcı adı veya şifre hatalı.';
            $_SESSION['last_login_attempt_identifier'] = $identifier;

            calculate_risk_score($redis, $user_ip, $user_id);

            if ($user_id && $redis) {
                $risk_score = (int)$redis->get("risk_score:$user_ip|$user_id");
                $risk_threshold = 500;
                if ($risk_score >= $risk_threshold) {
                    $redis->set("block:$user_ip|$user_id", 1);
                    $redis->expire("block:$user_ip|$user_id", 600);
                    header('Location: /app2/blocked.php?type=user');
                    exit();
                }

                $fail_count = $redis->incr($fail_count_key);
                $redis->expire($fail_count_key, 600);
            
                if ($fail_count >= 5) {
                    $redis->set("block:$user_ip|$user_id", 1);
                    $redis->expire("block:$user_ip|$user_id", 600);
                    header('Location: /app2/blocked.php?type=user');
                    exit();
                }
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Giriş Yap</title>
<style>
body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
.login-container { background-color: #fff; padding: 20px 40px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 300px; }
h2 { text-align: center; color: #333; }
label { display: block; margin-bottom: 5px; color: #555; }
input[type="text"], input[type="password"] { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
button { width: 100%; padding: 10px; background-color: #007BFF; color: #fff; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
button:hover { background-color: #084bb5; }
.error { color: red; margin-bottom: 16px; text-align: center; }
.captcha-section { margin-top: 10px; }
</style>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
<div class="login-container">
<h2>Giriş Yap</h2>
<?php if (!empty($login_errors)): ?>
<div class="error">
<?php foreach ($login_errors as $err): ?>
<div><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<form method="POST" action="">
<?php echo csrf_token_input(); ?>
<label>Kullanıcı Adı veya E-posta</label>
<input type="text" name="identifier" required value="<?php echo htmlspecialchars($_POST['identifier'] ?? '', ENT_QUOTES); ?>" />
<label>Şifre</label>
<input type="password" name="password" required />
<?php if ($fail_count >= 3): ?>
<div class="captcha-section">
<div class="g-recaptcha" data-sitekey="6LfSQKIrAAAAABeYRTiMrMdDbsBuziABAnUXjd-K"></div>
</div>
<?php endif; ?>
<button type="submit">Giriş Yap</button>
</form>
</div>
</body>
</html>
