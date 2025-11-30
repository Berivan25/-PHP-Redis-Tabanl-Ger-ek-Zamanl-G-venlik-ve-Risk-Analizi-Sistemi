<?php
require_once __DIR__ . '/functions/security.php';

$redis = get_redis_connection();
if (!$redis) {
    die('Redis bağlantısı sağlanamadı.');
}

$user_ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$session_id = session_id() ?: 'UNKNOWN_SESSION';


$user_id = $redis->get("ip_user:$user_ip");
$username = $user_id ? $redis->hGet('usernames', $user_id) : 'Bilinmeyen';

$events = $redis->lRange("user_behavior:$user_ip", 0, 99);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kullanıcı Davranışları</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        caption { font-size: 1.2em; margin-bottom: 10px; font-weight: bold; }
    </style>
</head>
<body>

<h2>Kullanıcı Davranış Geçmişi</h2>
<p><strong>IP:</strong> <?= htmlspecialchars($user_ip) ?> |
   <strong>Kullanıcı:</strong> <?= htmlspecialchars($username) ?> |
   <strong>Oturum ID:</strong> <?= htmlspecialchars($session_id) ?></p>

<?php if (!$events): ?>
    <p>Bu IP adresi için herhangi bir davranış kaydı bulunamadı.</p>
<?php else: ?>
    <table>
        <tr>
            <th>Zaman</th>
            <th>Olay Türü</th>
            <th>Olay Verisi</th>
            <th>Kullanıcı Tarayıcısı</th>
        </tr>
        <?php foreach ($events as $raw): ?>
            <?php
            $data = json_decode($raw, true);
            if (!is_array($data)) continue;

            $timestamp = isset($data['timestamp']) ? (float)$data['timestamp'] : microtime(true);
            $date = date('Y-m-d H:i:s', (int)$timestamp);

            $event_type = htmlspecialchars($data['event_type'] ?? 'Bilinmiyor');

            $event_data = '';
            if (!empty($data['event_data']) && is_array($data['event_data'])) {
                foreach ($data['event_data'] as $k => $v) {
                    $event_data .= htmlspecialchars($k) . ': ' . htmlspecialchars((string)$v) . "<br>";
                }
            }

            $user_agent = htmlspecialchars($data['user_agent'] ?? '—');
            ?>
            <tr>
                <td><?= $date ?></td>
                <td><?= $event_type ?></td>
                <td><?= $event_data ?></td>
                <td><?= $user_agent ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

</body>
</html>
