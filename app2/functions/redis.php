<?php
function get_redis_connection() {
    static $redis = null;
    if ($redis === null) {
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379, 1.0);
            $ping_result = $redis->ping();
            if ($ping_result !== true && $ping_result !== '+PONG') {
                error_log("Redis ping başarısız.");
                $redis = null;
            }
        } catch (\RedisException $e) {
            error_log("Redis Hatası: " . $e->getMessage());
            $redis = null;
        } catch (\Exception $e) {
            error_log("Genel Hata: " . $e->getMessage());
            $redis = null;
        }
    }
    return $redis;
}
   
?>
