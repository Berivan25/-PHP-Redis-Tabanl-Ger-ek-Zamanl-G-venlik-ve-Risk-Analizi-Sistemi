<?php
require_once __DIR__ . '/functions/redis.php';

$redis = get_redis_connection();
if (!$redis) {
    die("Redis bağlantısı kurulamadı.");
}


$threshold = time() - 30 * 24 * 60 * 60;;


$keys = $redis->keys('user_behavior:*');

foreach ($keys as $key) {
    
    $logs = $redis->lRange($key, 0, -1);
    foreach ($logs as $index => $logJson) {
        $log = json_decode($logJson, true);
        if (!$log) continue;
        if (($log['timestamp'] ?? 0) < $threshold) {
            
            $redis->lRem($key, 0, $logJson);
        }
    }
}


echo "Redis temizliği tamamlandı.\n";
