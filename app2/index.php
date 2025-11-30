<?php
session_start();

require_once __DIR__ . '/functions/security.php';
require_once __DIR__ . '/functions/redis.php';
require_once __DIR__ . '/csrf.php';


init_user_session('page_view', ['url' => $_SERVER['REQUEST_URI']]);
check_security_status();
log_visitor_ip_and_time();

$redis = get_redis_connection();

$user_id = $_SESSION['user_id'] ?? 'guest';
$user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';

if ($redis) {
    $redis->sAdd("ip_users:$user_ip", $user_id);
}


$is_logged_in = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? 'Misafir';

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ana Sayfa - Güvenli Uygulama</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            color: #333;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .app-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 800px;
        }
        .btn-group-custom {
            margin-top: 25px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="app-card text-center">
                    <h1 class="mb-4 text-primary">Güvenlik Uygulaması Demo Paneli</h1>
                    
                    <?php if ($is_logged_in): ?>
                        <div class="alert alert-success" role="alert">
                            <h4 class="alert-heading">Hoş Geldiniz, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>!</h4>
                            <p class="mb-0">Sistemde güvenli bir şekilde oturum açtınız. Şimdi farklı eylemleri deneyebilirsiniz.</p>
                        </div>
                        <a href="/app2/login.php" class="btn btn-danger mt-3">Çıkış Yap</a>
                    <?php else: ?>
                        <div class="alert alert-info" role="alert">
                            <h4 class="alert-heading">Merhaba Misafir!</h4>
                            <p class="mb-0">Sistemimizi test etmek için lütfen <a href="/app2/login.php" class="alert-link">Giriş Yap</a> sayfasını ziyaret edin.</p>
                        </div>
                    <?php endif; ?>

                    <div class="card mt-4">
                        <div class="card-body">
                            <h5 class="card-title">Kullanıcı Davranışlarını Kaydetme</h5>
                            <p class="card-text">
                                Aşağıdaki butonlara tıklayarak farklı kullanıcı olaylarını simüle edebilirsiniz. Tüm eylemleriniz,
                                yönetici panelinizdeki loglara kaydedilecektir.
                            </p>
                            <div class="btn-group-custom">
                                <button id="testClickBtn" class="btn btn-primary me-2">Tıklama Olayı</button>
                                <button id="testFormBtn" class="btn btn-warning">Form Gönderimi</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    fetch('/app2/user_behavior.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            event_type: 'page_view',
            event_data: {
                page: window.location.pathname,
                title: document.title
            }
        })
    });
    </script>

    <script>
    document.getElementById('testClickBtn').addEventListener('click', function() {
        const event = {
            event_type: 'click',
            event_data: {
                element_id: 'testClickBtn',
                description: 'Test butonuna tıklandı'
            }
        };

        fetch('user_behavior.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(event)
        })
        .then(res => res.json())
        .then(data => {
            console.log('Click event gönderildi:', data);
            alert('Tıklama olayı kaydedildi.');
        })
        .catch(err => {
            console.error('Hata:', err);
        });
    });
    </script>
    
    <script>
    document.getElementById('testFormBtn').addEventListener('click', function() {
        const event = {
            event_type: 'form_submission',
            event_data: {
                form_id: 'contactForm',
                description: 'Demo form gönderimi simülasyonu'
            }
        };

        fetch('user_behavior.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(event)
        })
        .then(res => res.json())
        .then(data => {
            console.log('Form gönderim event gönderildi:', data);
            alert('Form gönderimi olayı kaydedildi.');
        })
        .catch(err => {
            console.error('Hata:', err);
        });
    });
    </script>

    <script src="/app2/tracker.js"></script>

</body>
</html>