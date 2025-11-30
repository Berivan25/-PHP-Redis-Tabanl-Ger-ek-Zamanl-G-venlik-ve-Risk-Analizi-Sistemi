<?php
session_start();
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions/security.php';
require_once __DIR__ . '/functions/redis.php'; 
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek.']);
    exit;
}


verify_csrf_token();


if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim.']);
    exit;
}


$user_id = $_POST['user_id'] ?? '';
$ip = $_POST['ip'] ?? '';
$action = $_POST['action'] ?? '';
$type = $_POST['type'] ?? '';

if (!$action || !$type || !$ip) {
    echo json_encode(['success' => false, 'message' => 'Eksik parametreler.']);
    exit;
}


$redis = get_redis_connection();
if (!$redis) {
    echo json_encode(['success' => false, 'message' => 'Redis bağlantısı başarısız.']);
    exit;
}

try {
    switch ($type) {
        case 'block':
            if (!$user_id) throw new Exception('User ID eksik.');
            $user_key = "{$ip}|{$user_id}";
            if ($action === 'block') {
                $redis->set("block:{$user_key}", '1');
                $status = 'Engelli';
            } elseif ($action === 'unblock') {
                $redis->del("block:{$user_key}");
                $status = 'Aktif';
            } else {
                throw new Exception('Geçersiz işlem.');
            }
            break;

        case 'captcha':
            if (!$user_id) throw new Exception('User ID eksik.');
            $user_key = "{$ip}|{$user_id}";
            if ($action === 'enable') {
                $redis->set("captcha:{$user_key}", '1');
                $status = 'CAPTCHA Gerekli';
            } elseif ($action === 'disable') {
                $redis->del("captcha:{$user_key}");
                $status = 'Aktif';
            } else {
                throw new Exception('Geçersiz işlem.');
            }
            break;

        case 'clean':
            if ($user_id) {
              
                $redis->del("user_behavior:$user_id:$ip");
                $redis->del("risk_score:$ip|$user_id");
                $redis->del("block:$ip|$user_id");
                $redis->del("captcha:$ip|$user_id");
            } else {
                
                $patterns = [
                    "user_behavior:*:$ip",
                    "risk_score:$ip|*",
                    "block:$ip|*",
                    "captcha:$ip|*"
                ];

                foreach ($patterns as $pattern) {
                    $keys = $redis->keys($pattern);
                    foreach ($keys as $key) {
                        $redis->del($key);
                    }
                }
            }
            $status = 'Temizlendi';
            break;

        default:
            throw new Exception('Geçersiz tip.');
    }

    echo json_encode([
        'success' => true,
        'message' => 'İşlem başarıyla tamamlandı.',
        'status' => $status
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
