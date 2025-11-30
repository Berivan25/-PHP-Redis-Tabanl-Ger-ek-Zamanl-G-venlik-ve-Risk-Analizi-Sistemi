<?php

//require_once __DIR__ . '/risk_scoring.php';
require_once __DIR__ . '/redis.php'; 

function get_client_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}

function parse_user_agent(string $user_agent): array {
    $os = 'Bilinmiyor';
    $browser = 'Bilinmiyor';

    $osList = [
        'Linux' => '/linux/i',
        'Mac' => '/macintosh|mac os x/i',
        'Windows' => '/windows|win32/i',
    ];

    $browserList = [
        'Internet Explorer' => '/MSIE|Trident/i',
        'Firefox' => '/Firefox/i',
        'Chrome' => '/Chrome/i',
        'Safari' => '/Safari/i',
        'Opera' => '/Opera/i',
    ];

    foreach ($osList as $name => $pattern) {
        if (preg_match($pattern, $user_agent)) {
            $os = $name;
            break;
        }
    }

    foreach ($browserList as $name => $pattern) {
        if (preg_match($pattern, $user_agent)) {
            $browser = $name;
            break;
        }
    }

    return ['browser' => $browser, 'os' => $os];
}

function init_user_session(string $event_type = 'page_view', array $event_data = []): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $redis = get_redis_connection();
    if (!$redis) {
        error_log("Redis bağlantısı kurulamadı.");
        return false;
    }

    $ip = get_client_ip();
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN_AGENT';
    $session_id = session_id();
    $parsed = parse_user_agent($agent);

    if (!isset($_SESSION['first_visit_timestamp'])) {
        $_SESSION['first_visit_timestamp'] = microtime(true);
        $_SESSION['visit_count'] = 1;
    } else {
        $_SESSION['visit_count']++;
    }

    
    $username = $_SESSION['username'] ?? ($_SESSION['user_id'] ?? 'guest');

    $log_data = [
        'timestamp' => microtime(true),
        'ip' => $ip,
        'session_id' => $session_id,
        'user_agent' => $agent,
        'browser' => $parsed['browser'],
        'os' => $parsed['os'],
        'event_type' => $event_type,
        'event_data' => $event_data,
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'php_first_visit_ts' => $_SESSION['first_visit_timestamp'],
        'php_visit_count' => $_SESSION['visit_count'],
    ];

   
    $key = "user_behavior:$username:$ip";
    $redis->lPush($key, json_encode($log_data));
    $redis->lTrim($key, 0, 99);
    $redis->expire($key, 3600);


    $rate_key = "rate_limit:$ip";
    $redis->incr($rate_key);
    $redis->expire($rate_key, 60);
    if ((int)$redis->get($rate_key) > 100) {
        $redis->sAdd('blocked_ips', $ip);
        $redis->setex("blocked_ip_ttl:$ip", 600, 1);
        error_log("IP $ip çok fazla istek attı, engellendi.");
    }

    

    if ($username !== 'guest') {
        $redis->sAdd("user_ips:$username", $ip);
        $redis->sAdd("ip_users:$ip", $username);
    }

    return true;
}

function check_security_status(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $redis = get_redis_connection();
    if (!$redis) {
        error_log("Redis bağlantısı yok, kontrol atlandı.");
        return;
    }

    $ip = get_client_ip();
    $sid = session_id();

   
    $risk_score = calculate_risk_score($redis, $ip, $sid);

    if ($risk_score >= 21) {
        $redis->sAdd('blocked_ips', $ip);
        $redis->setex("blocked_ip_ttl:$ip", 600, 1);
        block_user("Yüksek risk puanı ($risk_score)");
    } elseif ($risk_score >= 11 && !isset($_SESSION['captcha_passed'])) {
        $redis->sAdd('captcha_required_sessions', $sid);
        header('Location: /app2/captcha_verification.php');
        exit();
    }

    if ($redis->sIsMember('blocked_ips', $ip)) {
        $ttl = $redis->ttl("blocked_ip_ttl:$ip");
        if ($ttl <= 0) {
            $redis->sRem('blocked_ips', $ip);
            $redis->del("blocked_ip_ttl:$ip");
        } else {
            block_user("IP adresiniz geçici olarak engellenmiş. ($ttl saniye kaldı)");
        }
    }

    if ($redis->sIsMember('blocked_sessions', $sid)) {
        block_user("Bu oturum kara listede: $sid");
    }

    if ($redis->sIsMember('captcha_required_sessions', $sid) && !isset($_SESSION['captcha_passed'])) {
        header('Location: /app2/captcha_verification.php');
        exit();
    }
}

function block_user(string $reason): void {
    error_log("Kullanıcı engellendi: IP=" . get_client_ip() . ", SID=" . session_id() . ", Neden: $reason");
    header('Location: /app2/blocked.php');
    exit();
}

function mark_captcha_passed(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['captcha_passed'] = true;

    try {
        $redis = get_redis_connection();
        if ($redis) {
            $redis->sRem('captcha_required_sessions', session_id());
        }
    } catch (\RedisException $e) {
        error_log("CAPTCHA kaldırma hatası: " . $e->getMessage());
    }
}

function calculate_risk_score($redis, $ip, $sid): int {
    $score = 0;

    $username = $_SESSION['username'] ?? ($_SESSION['user_id'] ?? 'guest');

   
    $login_failures = (int)$redis->get("failures:$ip|$username") ?: 0;
    if ($login_failures > 3) $score += 50;

    
    $blocked = (int)$redis->get("blocked:$ip|$username") ?: 0;
    if ($blocked) $score += 100;

    
    if ($username !== 'guest') {
        $user_ips = $redis->sCard("user_ips:$username");
        if ($user_ips > 1) $score += 20;
    }

    
    $ip_users = $redis->sCard("ip_users:$ip");
    if ($ip_users > 1) $score += 20;

   
    $redis->set("risk_score:{$ip}|{$username}", $score);

    return min($score, 100);
}


function log_visitor_ip_and_time(): void {
    $redis = get_redis_connection();
    if (!$redis) return;

    $ip = get_client_ip();
    $timestamp = date('Y-m-d H:i:s');
    $redis->lPush('visitor_logs', json_encode([
        'ip' => $ip,
        'time' => $timestamp
    ]));
    $redis->lTrim('visitor_logs', 0, 499);
}


function log_user_behavior($username, $ip_address, $action_type, $risk_score = 0) {
    require_once __DIR__ . '/../db.php'; 

    global $conn;

    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $stmt = $conn->prepare("INSERT INTO user_behavior_logs (username, ip_address, action_type, risk_score, user_agent) VALUES (?, ?, ?, ?, ?)");
    
    if ($stmt) {
        $stmt->bind_param("sssis", $username, $ip_address, $action_type, $risk_score, $user_agent);
        $stmt->execute();
        $stmt->close();
    } else {
        error_log("MySQL Prepare Hatası: " . $conn->error);
    }
}



