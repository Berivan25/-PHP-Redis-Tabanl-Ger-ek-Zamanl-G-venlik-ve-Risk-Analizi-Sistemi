<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/redis.php'; 
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/risk_data_fetcher.php'; 


if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$redis = get_redis_connection();
if (!$redis) {
    die("Redis bağlantısı kurulamadı!");
}


$risk_data = get_all_risk_scores($conn, $redis);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risk Skorları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .container-fluid { margin: auto; }
        .high-risk { color: red; font-weight: bold; }
        .medium-risk { color: orange; font-weight: bold; }
        .low-risk { color: green; font-weight: bold; }
        table { font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <h2>Kullanıcı Risk Skorları</h2>
        <table class="table table-striped table-hover mt-3">
            <thead class="table-dark">
                <tr>
                    <th>IP Adresi</th>
                    <th>Kullanıcı Adı</th>
                    <th>Son Görülme</th>
                    <th>Risk Skoru</th>
                    <th>Durum</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($risk_data)): ?>
                    <tr><td colspan="5" style="text-align:center;">Kayıt bulunamadı.</td></tr>
                <?php else: ?>
                    <?php foreach ($risk_data as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['ip'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($item['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo $item['last_seen'] ? date('Y-m-d H:i:s', $item['last_seen']) : 'Bilinmiyor'; ?></td>
                            <td class="<?= $item['score'] >= 50 ? 'risk-high' : 'risk-low' ?>"><?php echo $item['score']; ?></td>
                            <td>
                                <span class="badge 
                                    <?php if ($item['status'] == 'Engelli') echo 'bg-danger';
                                          elseif ($item['status'] == 'CAPTCHA Gerekli') echo 'bg-warning';
                                          else echo 'bg-success';
                                    ?>">
                                    <?= htmlspecialchars($item['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>