<?php
require_once __DIR__ . '/functions/security.php';

session_start();

$redis = get_redis_connection();
$user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
$username = $_SESSION['username'] ?? 'guest'; 

$risk_score = 0;
$log = [];

if ($redis) {
 
    $behavior_logs = $redis->lRange("user_behavior:$username:$user_ip", -10, -1);

    foreach ($behavior_logs as $entry) {
        $data = json_decode($entry, true);
        $log[] = $data;

        if ($data['event_type'] === 'login_attempt_failed') {
            $risk_score += 20;
        }

        if ($data['event_type'] === 'page_view') {
            $risk_score -= 5;
        }

        if ($data['event_type'] === 'mouse_move') {
            $risk_score -= 10;
        }

        if ($data['event_type'] === 'idle_time' && ($data['event_data']['duration'] ?? 0) > 10) {
            $risk_score += 15;
        }

        if ($data['event_type'] === 'no_mouse_activity') {
            $risk_score += 30;
        }
    }

    $risk_score = max(0, $risk_score);
}

$risk_level = 'Düşük';
if ($risk_score >= 40 && $risk_score < 70) {
    $risk_level = 'Orta';
} elseif ($risk_score >= 70) {
    $risk_level = 'Yüksek';
}

header('Content-Type: application/json');
echo json_encode([
    'ip' => $user_ip,
    'username' => $username,
    'risk_score' => $risk_score,
    'risk_level' => $risk_level,
    'last_behaviors' => $log
]);
