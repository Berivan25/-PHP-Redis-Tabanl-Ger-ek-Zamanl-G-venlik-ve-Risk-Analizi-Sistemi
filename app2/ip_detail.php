<?php
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    exit('Erişim reddedildi.');
}

require_once __DIR__ . '/functions/redis.php';

$ip = $_GET['ip'] ?? '';
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    exit('Geçersiz IP adresi.');
}

$redis = get_redis_connection();
if (!$redis) {
    exit('Redis bağlantısı kurulamadı.');
}

$pattern = "user_behavior:*:$ip";
$keys = $redis->keys($pattern);

$logs = [];
foreach ($keys as $key) {
    $additionalLogs = $redis->lRange($key, 0, 100);
    if ($additionalLogs) {
        $logs = array_merge($logs, $additionalLogs);
    }
}

if (empty($logs)) {
    echo '<p>Bu IP için kayıt bulunamadı.</p>';
    exit;
}

$usernames = $redis->hGetAll('usernames');

$userGroupedLogs = [];


foreach ($logs as $log) {
    $entry = json_decode($log, true);
    if (!$entry) continue;

    $user_id = $entry['user_id'] ?? ($entry['event_data']['username'] ?? 'Bilinmeyen');
    $userGroupedLogs[$user_id][] = $entry;
}


foreach ($userGroupedLogs as $user_id => &$entries) {
    usort($entries, function($a, $b) {
        return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
    });
}
unset($entries);

function getRiskScore($redis, $ip, $user_id) {
    $key = "risk_score:$ip|$user_id";
    $score = $redis->get($key);
    return $score !== false ? (int)$score : 0;
}

function isCaptchaShown($redis, $user_id) {
    return $redis->exists("captcha_required_user:$user_id") === 1;
}

function isBlocked($redis, $user_id) {
    return $redis->exists("blocked_user:$user_id") === 1;
}

$userMeta = [];
foreach ($userGroupedLogs as $user_id => $entries) {
    $displayName = $usernames[$user_id] ?? $user_id;

    $userMeta[$user_id] = [
        'display_name' => $displayName,
        'risk_score' => getRiskScore($redis, $ip, $user_id),
        'captcha' => isCaptchaShown($redis, $user_id) ? 'Evet' : 'Hayır',
        'blocked' => isBlocked($redis, $user_id) ? 'Evet' : 'Hayır',
    ];
}

$userGroupedLogsFiltered = [];
$userMetaFiltered = [];
$addedDisplayNames = [];

foreach ($userGroupedLogs as $user_id => $logs) {
    $displayName = $usernames[$user_id] ?? $user_id;

    if (in_array($displayName, $addedDisplayNames, true)) {
        
        continue;
    }

    $userGroupedLogsFiltered[$user_id] = $logs;
    $userMetaFiltered[$user_id] = $userMeta[$user_id];
    $addedDisplayNames[] = $displayName;
}

  
$totalLogCount = count($logs);
$totalUsers = count($userMetaFiltered);
$captchaCount = 0;
$blockedCount = 0;
$totalRisk = 0;

foreach ($userMetaFiltered as $meta) {
    if ($meta['captcha'] === 'Evet') $captchaCount++;
    if ($meta['blocked'] === 'Evet') $blockedCount++;
    $totalRisk += $meta['risk_score'];
}

$avgRisk = $totalUsers > 0 ? round($totalRisk / $totalUsers, 2) : 0;






$eventNames = [
    'page_view' => 'Sayfa Görüntüleme',
    'login_success' => 'Başarılı Giriş',
    'mouse_inactive' => 'Fare Pasifliği',
    'form_submission' => 'Form Gönderimi',
    'click' => 'Tıklama',
];

date_default_timezone_set('Europe/Istanbul'); 
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>IP Detayları - <?= htmlspecialchars($ip) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background-color: #f8f9fa; }
    .accordion-button { font-weight: 600; }
    .badge { font-size: 0.9em; }

    body { background-color: #f8f9fa; }
    .accordion-button { font-weight: 600; }
    .badge { font-size: 0.9em; }

   
    .event-login { background-color: #d1e7dd; }     
    .event-form  { background-color: #cfe2ff; }    
    .event-inactive { background-color: #f8d7da; }  
    .event-critical { background-color: #fff3cd; }  

</style>
</head>
<body class="p-4">

<div class="container">

        <div class="alert alert-info shadow-sm mb-4">
    <h5 class="mb-3">IP Genel Özeti</h5>
    <ul class="mb-0">
        <li><strong>Toplam Kayıt:</strong> <?= $totalLogCount ?></li>
        <li><strong>Kullanıcı Sayısı:</strong> <?= $totalUsers ?></li>
        <li><strong>CAPTCHA Gösterilen:</strong> <?= $captchaCount ?></li>
        <li><strong>Engellenmiş:</strong> <?= $blockedCount ?></li>
        <li><strong>Ortalama Risk Skoru:</strong> <?= $avgRisk ?></li>
    </ul>
</div>


  
    <h3 class="mb-4">IP: <code><?= htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') ?></code> için Kayıtlar</h3>

    <div class="accordion" id="userLogsAccordion">
        <?php $accordionId = 0; ?>
        <?php foreach ($userGroupedLogsFiltered as $user_id => $userLogs): ?>
            <?php $meta = $userMetaFiltered[$user_id]; ?>
            <div class="accordion-item mb-2">
                <h2 class="accordion-header" id="heading<?= $accordionId ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $accordionId ?>" aria-expanded="false" aria-controls="collapse<?= $accordionId ?>">
                        <?= htmlspecialchars($meta['display_name'], ENT_QUOTES, 'UTF-8') ?>
                        <span class="badge bg-primary ms-2"><?= count($userLogs) ?></span> kayıt
                        <br>
                        <small class="text-muted">
                            Risk Skoru: <strong><?= $meta['risk_score'] ?></strong> | 
                            CAPTCHA Gösterildi: <strong><?= $meta['captcha'] ?></strong> | 
                            Engellendi: <strong><?= $meta['blocked'] ?></strong>
                        </small>
                    </button>
                </h2>
                <div id="collapse<?= $accordionId ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $accordionId ?>" data-bs-parent="#userLogsAccordion">
                    <div class="accordion-body p-3 bg-white">
                        <ul class="list-group">
                            <?php foreach ($userLogs as $entry): 
                                $time = isset($entry['timestamp']) ? date('d.m.Y H:i:s', (int)$entry['timestamp']) : 'Zaman yok';
                                $event = $eventNames[$entry['event_type']] ?? $entry['event_type'] ?? 'Bilinmeyen';
                                $desc = $entry['event_data']['description'] ?? '';

                               
                                $eventClass = '';
                                if ($entry['event_type'] === 'login_success') {
                                    $eventClass = 'event-login';
                                } elseif ($entry['event_type'] === 'form_submission') {
                                    $eventClass = 'event-form';
                                } elseif ($entry['event_type'] === 'mouse_inactive') {
                                    $eventClass = 'event-inactive';
                                }

                               
                                if (($entry['event_data']['risk_score'] ?? 0) >= 80) {
                                    $eventClass .= ' event-critical';
                                }
                            ?>
                                <li class="list-group-item <?= $eventClass ?>">
                                    <strong><?= $time ?></strong> - <em><?= htmlspecialchars($event, ENT_QUOTES, 'UTF-8') ?></em>
                                    <?php if ($desc): ?>
                                        <br><small><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></small>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>

                        </ul>
                    </div>
                </div>
            </div>
            <?php $accordionId++; ?>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
