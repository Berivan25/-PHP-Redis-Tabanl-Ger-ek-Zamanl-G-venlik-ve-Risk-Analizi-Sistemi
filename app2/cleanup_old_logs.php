<?php

$host = "localhost";
$kullanici = "root";
$sifre = "";
$veritabani = "proje_risk";

$conn = new mysqli($host, $kullanici, $sifre, $veritabani);
if ($conn->connect_error) {
    die("Bağlantı hatası: " . $conn->connect_error);
}


$query = "DELETE FROM user_behavior_logs WHERE created_at < NOW() - INTERVAL 30 DAY";

if ($conn->query($query) === TRUE) {
    echo "30 günden eski kayıtlar başarıyla silindi.\n";
} else {
    echo "Silme işlemi sırasında hata: " . $conn->error . "\n";
}


require_once __DIR__ . '/functions/redis.php';

$redis = get_redis_connection();
if ($redis) {
  
    
    echo "Redis temizliği için manuel ekleme yapabilirsin.\n";
} else {
    echo "Redis bağlantısı kurulamadı, temizleme yapılmadı.\n";
}

$conn->close();
?>
