<?php

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function get_csrf_token() {
    return $_SESSION['csrf_token'] ?? generate_csrf_token();
}

function csrf_token_input() {
    $token = get_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function verify_csrf_token() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        $session_token = $_SESSION['csrf_token'] ?? '';

        if (!$token || !$session_token || !hash_equals($session_token, $token)) {
            http_response_code(403); 
            exit('CSRF doğrulaması başarısız.');
        }
    }
}

//hash_equals iki string'in eşit olup olmadığını kontrol eder

?>
