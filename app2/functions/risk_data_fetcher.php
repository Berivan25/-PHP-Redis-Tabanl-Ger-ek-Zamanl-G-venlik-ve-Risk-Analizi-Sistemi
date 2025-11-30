<?php

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/redis.php';

function get_all_risk_scores($conn, $redis, $filter_ip = '', $filter_risk_min = 0, $filter_risk_max = 1000) {
    $users_data = [];

    
    $keys = $redis->keys('risk_score:*');

    foreach ($keys as $key) {
        $parts = explode('|', str_replace('risk_score:', '', $key));
        $ip = $parts[0];
        $user_id = $parts[1] ?? '';

        if (empty($user_id)) continue;

        $score = (int)$redis->get($key);

        if ($filter_ip && $ip !== $filter_ip) continue;
        if ($score < $filter_risk_min || $score > $filter_risk_max) continue;

        
        $stmt = $conn->prepare("SELECT username FROM kullanici WHERE id = ?");
        $stmt->bind_param('s', $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $username = $result['username'] ?? null;

        
        if (!$username && $user_id !== 'guest') continue;
        if (!$username && $user_id === 'guest') $username = 'Misafir';

        $active_users_raw = $redis->hGetAll('active_users');
        $last_seen = isset($active_users_raw[$user_id]) ? (json_decode($active_users_raw[$user_id], true)['last_seen'] ?? 0) : 0;

        $status = 'Aktif';
        if ($redis->exists("block:{$ip}|{$user_id}")) {
            $status = 'Engelli';
        } elseif ($redis->exists("captcha:{$ip}|{$user_id}")) {
            $status = 'CAPTCHA Gerekli';
        }

        $users_data[] = [
            'id' => $user_id,
            'username' => $username,
            'ip' => $ip,
            'score' => $score,
            'last_seen' => $last_seen,
            'status' => $status
        ];
    }

    
    $all_user_keys = array_merge($redis->keys('risk_score:*'), $redis->keys('block:*'), $redis->keys('captcha:*'));
    foreach ($all_user_keys as $key) {
        $ip_user = explode(':', $key);
        $user_key = end($ip_user);
        $parts = explode('|', $user_key);
        if (count($parts) < 2) continue;

        $ip = $parts[0];
        $user_id = $parts[1];

        if ($user_id !== 'guest') continue;

        $exists = false;
        foreach ($users_data as $u) {
            if ($u['id'] === $user_id && $u['ip'] === $ip) {
                $exists = true;
                break;
            }
        }
        if ($exists) continue;

        $score = (int)($redis->get("risk_score:{$user_key}") ?? 0);
        $last_seen = isset($active_users_raw[$user_id]) ? (json_decode($active_users_raw[$user_id], true)['last_seen'] ?? 0) : 0;

        $status = 'Aktif';
        if ($redis->exists("block:{$user_key}")) {
            $status = 'Engelli';
        } elseif ($redis->exists("captcha:{$user_key}")) {
            $status = 'CAPTCHA Gerekli';
        }

        $users_data[] = [
            'id' => $user_id,
            'username' => 'Misafir',
            'ip' => $ip,
            'score' => $score,
            'last_seen' => $last_seen,
            'status' => $status
        ];
    }

    return $users_data;
}
