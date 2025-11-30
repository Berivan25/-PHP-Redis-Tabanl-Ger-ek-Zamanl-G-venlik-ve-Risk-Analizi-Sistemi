<?php
phpinfo();

require_once __DIR__ . '/functions/security.php';

init_user_session('page_view', ['url' => $_SERVER['REQUEST_URI']]);
check_security_status();


?>