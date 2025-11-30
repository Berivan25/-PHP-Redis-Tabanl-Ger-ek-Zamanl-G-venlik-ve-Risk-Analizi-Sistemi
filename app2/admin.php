<?php
session_start();
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions/security.php';
require_once __DIR__ . '/functions/redis.php'; 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions/risk_data_fetcher.php';

init_user_session('page_view', [
    'url' => $_SERVER['REQUEST_URI'],
    'username' => $_SESSION['username'] ?? 'guest'
]);

check_security_status();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /app2/blocked.php');
    exit();
}

date_default_timezone_set('Europe/Istanbul');

$redis = get_redis_connection();
if (!$redis) {
    die("Redis bağlantısı kurulamadı.");
}

$filter_ip = $_GET['ip'] ?? '';
$filter_risk_min = isset($_GET['risk_min']) ? (int)$_GET['risk_min'] : 0;
$filter_risk_max = isset($_GET['risk_max']) ? (int)$_GET['risk_max'] : 1000;
$sort_order = $_GET['sort_order'] ?? 'desc';

$users_data = get_all_risk_scores($conn, $redis, $filter_ip, $filter_risk_min, $filter_risk_max);


foreach ($users_data as &$data) {
    if (isset($data['id']) && isset($data['ip'])) {
        if ($redis->exists("block:{$data['ip']}|{$data['id']}")) {
            $data['status'] = 'Engelli';
        } elseif ($redis->exists("captcha:{$data['ip']}|{$data['id']}")) {
            $data['status'] = 'CAPTCHA Gerekli';
        } else {
            $data['status'] = 'Aktif';
        }
    }
}
unset($data);

if ($sort_order === 'asc') {
    usort($users_data, function($a, $b) {
        return $a['score'] <=> $b['score'];
    });
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Admin Paneli - Aktif Kullanıcılar</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background-color: #f8f9fa; }
.risk-low { color: green; font-weight: bold; }
.risk-high { color: red; font-weight: bold; }
.user-list { font-size: 0.9rem; }
.modal-dialog { max-width: 900px; }
</style>
</head>
<body>
<div class="container my-5">
<h1 class="mb-4">Admin Paneli - Aktif Kullanıcılar</h1>

<form method="GET" class="row g-3 mb-4 align-items-end">
    <div class="col-md-4">
        <label for="ip" class="form-label">IP Filtrele</label>
        <input type="text" id="ip" name="ip" class="form-control" value="<?= htmlspecialchars($filter_ip) ?>" placeholder="IP adresi ara">
    </div>
    <div class="col-md-2">
        <label for="risk_min" class="form-label">Risk Min</label>
        <input type="number" id="risk_min" name="risk_min" class="form-control" min="0" max="1000" value="<?= $filter_risk_min ?>">
    </div>
    <div class="col-md-2">
        <label for="risk_max" class="form-label">Risk Max</label>
        <input type="number" id="risk_max" name="risk_max" class="form-control" min="0" max="1000" value="<?= $filter_risk_max ?>">
    </div>
    <div class="col-md-4 d-grid">
        <button type="submit" class="btn btn-primary">Filtrele</button>
    </div>

    <div class="col-md-2">
        <label for="sort_order" class="form-label">Sıralama</label>
        <select name="sort_order" id="sort_order" class="form-select">
            <option value="desc" <?= ($sort_order === 'desc') ? 'selected' : '' ?>>Risk Yüksekten Düşüğe</option>
            <option value="asc" <?= ($sort_order === 'asc') ? 'selected' : '' ?>>Risk Düşükten Yükseğe</option>
        </select>
    </div>

    <div class="col-md-2 d-grid">
        <a href="functions/risk_scoring.php" class="btn btn-outline-primary">Risk Skorları</a>
    </div>
</form>

<table class="table table-striped table-hover align-middle">
<thead class="table-dark">
<tr>
    <th>IP Adresi</th>
    <th>Kullanıcı Adı</th>
    <th>Son Görülme</th>
    <th>Risk Skoru</th>
    <th>Durum</th>
    <th>İşlemler</th>
</tr>
</thead>
</thead>
<tbody>
<?php if (empty($users_data)): ?>
<tr><td colspan="6" class="text-center">Filtreye uygun aktif kullanıcı bulunamadı.</td></tr>
<?php else: ?>

<?php

$grouped_by_ip = [];
foreach ($users_data as $data) {
    $ip = $data['ip'] ?? 'Bilinmiyor';
    if (!isset($grouped_by_ip[$ip])) {
        $grouped_by_ip[$ip] = [];
    }
    $grouped_by_ip[$ip][] = $data;
}
?>

<?php foreach ($grouped_by_ip as $ip => $users): ?>
<?php $first = true; ?>
<?php $rowspan = count($users); ?>
<?php foreach ($users as $data): ?>
<tr>
    <?php if ($first): ?>
        <td rowspan="<?= $rowspan ?>" style="text-align:center; vertical-align:middle; font-weight:bold;">
            <?= htmlspecialchars($ip) ?>
        </td>
    <?php endif; ?>
    <td><?= htmlspecialchars($data['username']) ?></td>
    <td><?= $data['last_seen'] ? date('Y-m-d H:i:s', $data['last_seen']) : 'Bilinmiyor'; ?></td>
    <td class="<?= $data['score'] >= 50 ? 'risk-high' : 'risk-low' ?>"><?= $data['score'] ?></td>
    <td>
        <span class="badge 
            <?php if ($data['status'] == 'Engelli') echo 'bg-danger';
                  elseif ($data['status'] == 'CAPTCHA Gerekli') echo 'bg-warning';
                  else echo 'bg-success';
            ?>">
            <?= htmlspecialchars($data['status']) ?>
        </span>
    </td>
    <td>
        <?php if ($data['status'] === 'Engelli'): ?>
            <button class="btn btn-sm btn-success manage-action-btn me-1" 
                data-userid="<?= isset($data['id']) ? htmlspecialchars($data['id']) : '' ?>" 
                data-ip="<?= isset($data['ip']) ? htmlspecialchars($data['ip']) : '' ?>" 
                data-type="block" 
                data-action="unblock">
                Engeli Kaldır
            </button>
        <?php else: ?>
            <button class="btn btn-sm btn-danger manage-action-btn me-1" 
                data-userid="<?= isset($data['id']) ? htmlspecialchars($data['id']) : '' ?>" 
                data-ip="<?= isset($data['ip']) ? htmlspecialchars($data['ip']) : '' ?>" 
                data-type="block" 
                data-action="block">
                Engelle
            </button>
        <?php endif; ?>

        <?php if ($data['status'] === 'CAPTCHA Gerekli'): ?>
            <button class="btn btn-sm btn-info manage-action-btn me-1" 
                data-userid="<?= isset($data['id']) ? htmlspecialchars($data['id']) : '' ?>" 
                data-ip="<?= isset($data['ip']) ? htmlspecialchars($data['ip']) : '' ?>" 
                data-type="captcha" 
                data-action="disable">
                CAPTCHA Kaldır
            </button>
        <?php else: ?>
            <button class="btn btn-sm btn-warning manage-action-btn me-1" 
                data-userid="<?= isset($data['id']) ? htmlspecialchars($data['id']) : '' ?>" 
                data-ip="<?= isset($data['ip']) ? htmlspecialchars($data['ip']) : '' ?>" 
                data-type="captcha" 
                data-action="enable">
                CAPTCHA Uygula
            </button>
        <?php endif; ?>

        <button class="btn btn-warning btn-sm clean-user-btn me-1" 
                data-userid="<?= isset($data['id']) ? htmlspecialchars($data['id']) : '' ?>"
                data-ip="<?= isset($data['ip']) ? htmlspecialchars($data['ip']) : '' ?>">
            Temizle 
        </button>
        <?php if ($first): ?>
            <td rowspan="<?= $rowspan ?>" style="text-align:center; vertical-align:middle;">
                <button type="button" class="btn btn-sm btn-info" 
                        data-bs-toggle="modal" 
                        data-bs-target="#ipDetailModal" 
                        data-ip="<?= htmlspecialchars($ip) ?>">
                    Detay
                </button>
            </td>
        <?php endif; ?>
    </td>
</tr>
<?php $first = false; ?>
<?php endforeach; ?>
<?php endforeach; ?>
<?php endif; ?>
</tbody>

</table>
</div>

<div class="modal fade" id="ipDetailModal" tabindex="-1" aria-labelledby="ipDetailModalLabel" aria-hidden="true">
<div class="modal-dialog modal-dialog-scrollable modal-lg">
<div class="modal-content">
<div class="modal-header">
    <h5 class="modal-title" id="ipDetailModalLabel">IP Detayları</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
</div>
<div class="modal-body"></div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CSRF_TOKEN = '<?= htmlspecialchars(get_csrf_token()) ?>';


function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toast-container') || (() => {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.style.position = 'fixed';
        container.style.top = '20px';
        container.style.right = '20px';
        container.style.zIndex = 1055;
        document.body.appendChild(container);
        return container;
    })();

    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${type} border-0`;
    toast.role = 'alert';
    toast.ariaLive = 'assertive';
    toast.ariaAtomic = 'true';
    toast.style.minWidth = '200px';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Kapat"></button>
        </div>
    `;
    toastContainer.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, { delay: 2500 });
    bsToast.show();
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}


function updateUserRow(userId, newStatus) {
    const row = document.querySelector(`button[data-userid="${userId}"]`)?.closest('tr');
    if (!row) return;

    const statusCell = row.querySelector('td:nth-child(5) span.badge');
    if (statusCell) {
        statusCell.textContent = newStatus;
        statusCell.className = 'badge ' + (
            newStatus === 'Engelli' ? 'bg-danger' :
            newStatus === 'CAPTCHA Gerekli' ? 'bg-warning' :
            'bg-success'
        );
    }

    const blockBtn = row.querySelector('button[data-type="block"]');
    const captchaBtn = row.querySelector('button[data-type="captcha"]');

    if (blockBtn) {
        if (newStatus === 'Engelli') {
            blockBtn.textContent = 'Engeli Kaldır';
            blockBtn.dataset.action = 'unblock';
            blockBtn.className = 'btn btn-sm btn-success manage-action-btn me-1';
        } else {
            blockBtn.textContent = 'Engelle';
            blockBtn.dataset.action = 'block';
            blockBtn.className = 'btn btn-sm btn-danger manage-action-btn me-1';
        }
    }

    if (captchaBtn) {
        if (newStatus === 'CAPTCHA Gerekli') {
            captchaBtn.textContent = 'CAPTCHA Kaldır';
            captchaBtn.dataset.action = 'disable';
            captchaBtn.className = 'btn btn-sm btn-info manage-action-btn me-1';
        } else {
            captchaBtn.textContent = 'CAPTCHA Uygula';
            captchaBtn.dataset.action = 'enable';
            captchaBtn.className = 'btn btn-sm btn-warning manage-action-btn me-1';
        }
    }
}


function sendAction(url, userId, ipAddress, action, type) {
    if (!userId || !ipAddress) {
        showToast('User ID veya IP boş. İşlem yapılamıyor.', 'danger');
        return;
    }

    const body = new URLSearchParams();
    body.append('user_id', userId);
    body.append('ip', ipAddress);
    body.append('action', action);
    body.append('type', type);
    body.append('csrf_token', CSRF_TOKEN);

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            let newStatus;
            if (type === 'block') newStatus = action === 'block' ? 'Engelli' : 'Aktif';
            else if (type === 'captcha') newStatus = action === 'enable' ? 'CAPTCHA Gerekli' : 'Aktif';
            else if (type === 'clean') newStatus = 'Aktif';

            updateUserRow(userId, newStatus);
            showToast(data.message || 'İşlem başarıyla gerçekleşti.', 'success');
        } else {
            showToast(data.message || 'İşlem başarısız.', 'danger');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Sunucuya bağlanırken hata oluştu.', 'danger');
    });
}


document.querySelectorAll('.manage-action-btn').forEach(button => {
    button.addEventListener('click', () => {
        sendAction('/app2/manage_user_action.php', button.dataset.userid, button.dataset.ip, button.dataset.action, button.dataset.type);
    });
});

document.querySelectorAll('.clean-user-btn').forEach(button => {
    button.addEventListener('click', () => {
        sendAction('/app2/manage_user_action.php', button.dataset.userid, button.dataset.ip, 'clean', 'clean');
    });
});


const ipDetailModal = document.getElementById('ipDetailModal');
if (ipDetailModal) {
    ipDetailModal.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        const ip = button.getAttribute('data-ip');
        const modalBody = ipDetailModal.querySelector('.modal-body');
        modalBody.innerHTML = `<div class="text-center">
            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Yükleniyor...</span></div>
            <p class="mt-2">İçerik yükleniyor...</p>
        </div>`;
        fetch(`ip_detail.php?ip=${encodeURIComponent(ip)}`)
            .then(res => res.text())
            .then(html => modalBody.innerHTML = html)
            .catch(err => {
                console.error(err);
                modalBody.innerHTML = '<div class="alert alert-danger">Detaylar yüklenirken bir hata oluştu.</div>';
            });
    });
}
</script>

</body>
</html>
