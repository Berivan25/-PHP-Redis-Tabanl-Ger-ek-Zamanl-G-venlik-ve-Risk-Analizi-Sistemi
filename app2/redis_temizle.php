<?php

require_once __DIR__ . '/functions/redis.php';


$redis = get_redis_connection();

if (!$redis) {
    die("Redis bağlantısı kurulamadı. Lütfen sunucunuzun çalışıp çalışmadığını kontrol edin.");
}


$username_to_delete = 'berivan';


$risk_score_keys = $redis->keys("risk_score:*|{$username_to_delete}");
if (!empty($risk_score_keys)) {
    $redis->del($risk_score_keys);
    echo count($risk_score_keys) . " adet risk skoru anahtarı silindi.\n";
} else {
    echo "Risk skoru anahtarı bulunamadı.\n";
}


$active_users_key = 'active_users';
if ($redis->hExists($active_users_key, $username_to_delete)) {
    $redis->hDel($active_users_key, $username_to_delete);
    echo "'active_users' hash'inden '{$username_to_delete}' silindi.\n";
} else {
    echo "'active_users' hash'inde '{$username_to_delete}' bulunamadı.\n";
}


$blocked_key = "blocked_user:{$username_to_delete}";
if ($redis->exists($blocked_key)) {
    $redis->del($blocked_key);
    echo "'blocked_user' anahtarı silindi.\n";
} else {
    echo "'blocked_user' anahtarı bulunamadı.\n";
}

$captcha_key = "captcha_required_user:{$username_to_delete}";
if ($redis->exists($captcha_key)) {
    $redis->del($captcha_key);
    echo "'captcha_required_user' anahtarı silindi.\n";
} else {
    echo "'captcha_required_user' anahtarı bulunamadı.\n";
}

echo "\n{$username_to_delete} kullanıcısına ait tüm veriler Redis'ten temizlendi.";




$behavior_log_key = "user_behavior:{$username_to_delete}:*";
$behavior_log_keys_to_delete = $redis->keys($behavior_log_key);
if (!empty($behavior_log_keys_to_delete)) {
    $redis->del($behavior_log_keys_to_delete);
    echo count($behavior_log_keys_to_delete) . " adet davranış logu anahtarı silindi.\n";
} else {
    echo "Davranış logu anahtarı bulunamadı.\n";
}

echo "\n{$username_to_delete} kullanıcısına ait tüm veriler Redis'ten temizlendi.";

?>