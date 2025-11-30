<?php
try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->set("selam", "Merhaba Redis!");
    echo $redis->get("selam");
} catch (Exception $e) {
    echo "Redis bağlantı hatası: " . $e->getMessage();
}


$keys = $redis->keys('risk_score:*');
if (empty($keys)) {
    echo "Risk skorlarına ait herhangi bir anahtar bulunamadı.";
} else {
    echo "Risk skorları bulunan anahtarlar:<br>";
    foreach ($keys as $key) {
        echo htmlspecialchars($key) . "<br>";
    }
}
foreach ($keys as $key) {
    $score = $redis->get($key);
    echo htmlspecialchars($key) . " => " . $score . "<br>";
}

?>
