<?php
session_start();

require_once __DIR__ . '/functions/redis.php';
require_once __DIR__ . '/functions/security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['event_type']) || !isset($input['event_data'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Geçersiz veri']);
        exit;
    }

    $redis = get_redis_connection();
    if (!$redis) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Redis bağlantısı sağlanamadı']);
        exit;
    }

    $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $session_id = session_id();

    
   $user_id = $_SESSION['user_id'] ?? 'guest';

    $event = [
        'timestamp' => microtime(true),
        'ip' => $user_ip,
        'session_id' => $session_id,
        'user_id' => $user_id,
        'event_type' => $input['event_type'],
        'event_data' => $input['event_data'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ];

    $key = "user_behavior:$user_id:$user_ip";

    $redis->lPush($key, json_encode($event));
    $redis->lTrim($key, 0, 99); 
    $redis->expire($key, 604800);



    log_user_behavior($user_id, $user_ip, $input['event_type'], 0);

    echo json_encode(['status' => 'success']);
    exit;

} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Yalnızca POST metodu desteklenir']);
}
