<?php

require_once __DIR__ . '/functions/redis.php';
require_once __DIR__ . '/db.php'; 


$username = 'rabia';
$ip_address = '192.168.1.55';
$action_type = 'Sayfa Görüntüleme';
$risk_score = 0;
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';


$query = "INSERT INTO user_behavior_logs (username, ip_address, action_type, risk_score, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($query);

if (!$stmt) {
    die("Hazırlama hatası: " . $conn->error);
}

$stmt->bind_param("sssis", $username, $ip_address, $action_type, $risk_score, $user_agent);

if ($stmt->execute()) {
    echo "Kayıt veritabanına başarıyla eklendi.<br>";


    $redis = get_redis_connection();
    if ($redis) {

        $redis->sAdd("ip_users:$ip_address", $username);

      
        $redis->set("risk_score:$ip_address|$username", $risk_score);

        
        $log_data = json_encode([
            'event_type' => $action_type, 
            'event_data' => [
                'username' => $username,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'risk_score' => $risk_score,
            ],
            'timestamp' => time()
        ]);
        
     
        $redis->lPush("user_behavior:$username:$ip_address", $log_data);

        echo "Redis'e veri yazıldı.";
    } else {
        echo "Redis bağlantısı kurulamadı.";
    }

} else {
    echo "Kayıt eklenirken hata oluştu: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>