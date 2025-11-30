<?php
require_once __DIR__ . '/functions/security.php'; 
session_start();

header('Content-Type: application/json');


$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);


if (!$data || !isset($data['mouseMoves'], $data['totalDistance'], $data['keyStrokes'], $data['duration'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Eksik veri']);
    exit;
}

$mouseMoves    = (int)$data['mouseMoves'];
$totalDistance = (int)$data['totalDistance'];
$keyStrokes    = (int)$data['keyStrokes'];
$duration      = (int)$data['duration']; 


$ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
$username = $_SESSION['user_id'] ?? 'guest';


$redis = get_redis_connection();
if (!$redis) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Redis bağlantısı kurulamadı']);
    exit;
}


$risk = 0;
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin')
if ($mouseMoves < 10)         $risk += 15;
if ($totalDistance < 300)     $risk += 20;
if ($keyStrokes < 5)          $risk += 30;
if ($duration < 3000)         $risk += 35; 

$risk = min($risk, 100);


$redis->hMSet("behavior_data:$username:$ip", [
    'mouseMoves'    => $mouseMoves,
    'totalDistance' => $totalDistance,
    'keyStrokes'    => $keyStrokes,
    'duration_ms'   => $duration,
    'behavior_risk' => $risk,
    'timestamp'     => time()
]);


$risk_key = "risk_score:$ip|$username";
$redis->incrBy($risk_key, $risk);


echo json_encode([
    'status' => 'success',
    'message' => 'Davranış verisi kaydedildi',
    'risk_score_added' => $risk
]);


