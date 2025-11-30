<?php
require_once __DIR__ . '/functions/security.php';
require_once __DIR__ . '/functions/risk_scoring.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$redis = get_redis_connection();
if (!$redis) {
    die("Redis bağlantısı kurulamadı.");
}

$keys = $redis->keys('user_behavior:*');

$data_by_ip = [];

foreach ($keys as $key) {
    $json = $redis->get($key);
    $entry = json_decode($json, true);
    if (!$entry || !isset($entry['ip'])) continue;

    $ip = $entry['ip'];
    $username = $entry['username'] ?? 'guest';
    $session_id = $entry['session_id'] ?? '';
    $user_agent = $entry['user_agent'] ?? '';
    $event_type = $entry['event_type'] ?? 'unknown';
    $event_data = $entry['event_data'] ?? [];
    $timestamp = $entry['timestamp'] ?? 0;

    $id = "$ip|$username";

    if (!isset($data_by_ip[$id])) {
        $data_by_ip[$id] = [
            'ip' => $ip,
            'username' => $username,
            'risk_score' => 0,
            'last_activity' => 0,
            'total_events' => 0,
            'event_counts' => [],
            'last_session_id' => '',
            'last_user_agent' => ''
        ];
    }

    $data_by_ip[$id]['total_events']++;
    $data_by_ip[$id]['last_activity'] = max($data_by_ip[$id]['last_activity'], $timestamp);
    $data_by_ip[$id]['last_session_id'] = $session_id;
    $data_by_ip[$id]['last_user_agent'] = $user_agent;

    if (!isset($data_by_ip[$id]['event_counts'][$event_type])) {
        $data_by_ip[$id]['event_counts'][$event_type] = 0;
    }
    $data_by_ip[$id]['event_counts'][$event_type]++;
}


foreach ($data_by_ip as $id => &$info) {
    $score = 0;
    $ec = $info['event_counts'];

    if (($ec['login_failed'] ?? 0) > 3) $score += 10;
    if (($ec['captcha_failed'] ?? 0) > 2) $score += 5;
    if (($ec['mouse_inactive'] ?? 0) > 5) $score += 5;
    if ($info['total_events'] > 50) $score += 5;

    if ($redis->sIsMember('blocked_ips', $info['ip'])) {
        $score += 20;
    }
    if ($redis->sIsMember('captcha_required_ips', $info['ip'])) {
        $score += 5;
    }

    $info['risk_score'] = $score;
}
unset($info);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8" />
    <title>Risk Logları</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        th { background-color: #f0f0f0; }
        h1 { margin-bottom: 20px; }
        .risk-high { color: red; font-weight: bold; }
        .risk-medium { color: orange; font-weight: bold; }
        .risk-low { color: green; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Risk Logları</h1>
    <table>
        <thead>
            <tr>
                <th>Kullanıcı</th>
                <th>IP Adresi</th>
                <th>Risk Puanı</th>
                <th>Risk Seviyesi</th>
                <th>Son Aktivite</th>
                <th>Toplam Olay</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data_by_ip as $info): ?>
                <tr>
                    <td><?= htmlspecialchars($info['username']); ?></td>
                    <td><?= htmlspecialchars($info['ip']); ?></td>
                    <td><?= $info['risk_score']; ?></td>
                    <td>
                        <?php
                            if ($info['risk_score'] >= 21) {
                                echo '<span class="risk-high">Yüksek</span>';
                            } elseif ($info['risk_score'] >= 11) {
                                echo '<span class="risk-medium">Orta</span>';
                            } else {
                                echo '<span class="risk-low">Düşük</span>';
                            }
                        ?>
                    </td>
                    <td><?= $info['last_activity'] ? date('Y-m-d H:i:s', (int)$info['last_activity']) : 'Bilinmiyor'; ?></td>
                    <td><?= $info['total_events']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
