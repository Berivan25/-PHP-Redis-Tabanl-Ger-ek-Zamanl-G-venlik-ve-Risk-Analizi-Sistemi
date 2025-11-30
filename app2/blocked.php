<?php
session_start();
require_once __DIR__ . '/functions/redis.php';
require_once __DIR__ . '/functions/security.php';

$user_ip = get_client_ip();
$identifier = $_SESSION['last_login_attempt_identifier'] ?? 'guest';
$redis = get_redis_connection();

$remaining_time = 0;
$blocked_type = $_GET['type'] ?? '';

$user_id = null;
if ($identifier !== 'guest') {
    include 'db.php';
    $stmt = $conn->prepare("SELECT id FROM kullanici WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $user_id = $user['id'] ?? null;
    $stmt->close();
    
    $conn->close(); 
}

$user_key = "{$user_ip}|{$user_id}";

if ($redis) {
   
    $ttl_ip = $redis->exists("blocked_ip_ttl:$user_ip") ? $redis->ttl("blocked_ip_ttl:$user_ip") : 0;
    $ttl_user = $redis->exists("block:{$user_key}") ? $redis->ttl("block:{$user_key}") : 0;

    $is_ip_blocked = $redis->sIsMember('blocked_ips', $user_ip);
    $is_user_blocked = $redis->exists("block:{$user_key}");

    if ($is_ip_blocked && $blocked_type === 'ip') {
        $remaining_time = $ttl_ip;
    } elseif ($is_user_blocked && $blocked_type === 'user') {
        $remaining_time = $ttl_user;
    } elseif ($is_ip_blocked && $is_user_blocked) {
        $remaining_time = max($ttl_ip, $ttl_user);
        $blocked_type = 'both';
    } else {
        header('Location: /app2/login.php');
        exit();
    }
   
    $redis->close();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Erişim Engellendi</title>
<style>
body { background-color: #f8f9fa; font-family: Arial; display:flex; justify-content:center; align-items:center; height:100vh; text-align:center; }
.container { max-width:600px; background:#fff; padding:40px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
h1 { color:#dc3545; font-size:2.5rem; margin-bottom:20px; }
p { color:#6c757d; font-size:1.1rem; }
#countdown { font-size:2.2rem; font-weight:bold; color:#212529; margin-top:20px; }
</style>
</head>
<body>
<div class="container">
<h1>Erişim Engellendi</h1>
<p>Çok fazla giriş denemesi yaptığınız için <b><?php echo htmlspecialchars($blocked_type); ?></b> engellenmiştir.</p>
<p>Lütfen bekleyin ve sürenin sonunda tekrar deneyiniz.</p>
<p>Kalan süre:</p>
<div id="countdown"></div>
</div>
<script>
let timeLeft = <?php echo $remaining_time; ?>;
const countdownElement = document.getElementById('countdown');

function updateCountdown() {
    if (timeLeft <= 0) {
        countdownElement.textContent = "Süre bitti, tekrar deneyiniz.";
        setTimeout(()=>{window.location.href='/app2/login.php';},1000);
        clearInterval(timer);
        return;
    }
    const minutes = Math.floor(timeLeft / 60);
    const seconds = timeLeft % 60;
    countdownElement.textContent = `${minutes.toString().padStart(2,'0')}:${seconds.toString().padStart(2,'0')}`;
    timeLeft--;
}
const timer = setInterval(updateCountdown, 1000);
updateCountdown();
</script>
</body>
</html>