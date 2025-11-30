<?php
require_once __DIR__ . '/functions/security.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$redis = get_redis_connection();
if (!$redis) {
    echo "<h3>Redis bağlantısı başarısız.</h3>";
    exit;
}


$keys = $redis->keys("user_behavior_list:*");


rsort($keys);


$logs = [];
foreach ($keys as $key) {
    $entries = $redis->lRange($key, 0, 10); 
    foreach ($entries as $entry) {
        $data = json_decode($entry, true);
        if ($data) {
            $logs[] = $data;
        }
    }
}


usort($logs, function ($a, $b) {
    return $b['timestamp'] <=> $a['timestamp'];
});
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Risk Raporu</title>
    <style>
        body { font-family: Arial; background: #f0f0f0; padding: 20px; }
        table { border-collapse: collapse; width: 100%; background: white; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #ddd; }
    </style>
</head>
<body>
    <h2>Kullanıcı Davranış Raporu</h2>
    <table>
        <thead>
            <tr>
                <th>IP</th>
                <th>Session</th>
                <th>User-Agent</th>
                <th>Olay Türü</th>
                <th>Veri</th>
                <th>Zaman</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['ip'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($log['session_id'] ?? '-') ?></td>
                    <td><?= htmlspecialchars(substr($log['user_agent'], 0, 30)) ?>...</td>
                    <td><?= htmlspecialchars($log['event_type'] ?? '-') ?></td>
                    <td><?= is_array($log['event_data']) ? json_encode($log['event_data']) : '-' ?></td>
                    <td><?= date('Y-m-d H:i:s', $log['timestamp']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
