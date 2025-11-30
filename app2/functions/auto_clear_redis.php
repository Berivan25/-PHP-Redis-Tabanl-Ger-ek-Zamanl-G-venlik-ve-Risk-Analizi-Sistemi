<?php
require_once __DIR__ . '/redis.php'; 

function auto_clear_redis_daily() {
    $redis = get_redis_connection();
    if (!$redis) return;

    $today = date('Y-m-d');
    $last_clear_date = $redis->get('last_clear_date');

 
    if ($last_clear_date !== $today) {
       
        $keys_to_delete = $redis->keys("risk_score:*");
        foreach ($keys_to_delete as $key) {
            $redis->del($key);
        }

        $keys_to_delete = $redis->keys("failures:*");
        foreach ($keys_to_delete as $key) {
            $redis->del($key);
        }

        $keys_to_delete = $redis->keys("captcha_required:*");
        foreach ($keys_to_delete as $key) {
            $redis->del($key);
        }

        $keys_to_delete = $redis->keys("blocked:*");
        foreach ($keys_to_delete as $key) {
            $redis->del($key);
        }

       
        $redis->set('last_clear_date', $today);
    }

    $visitor_logs = $redis->lRange('visitor_logs', 0, -1);
    $current_time = time(); 
}
