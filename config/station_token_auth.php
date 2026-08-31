<?php

if (!function_exists('station_token_vault_key')) {
    function station_token_vault_key(): string
    {
        $envKey = getenv('STATION_TOKEN_ENCRYPTION_KEY');
        if (is_string($envKey) && $envKey !== '') return hash('sha256', $envKey, true);

        $keyPath = dirname(__DIR__) . '/scratch/station_token.key';
        if (!is_file($keyPath)) {
            $key = random_bytes(32);
            if (file_put_contents($keyPath, base64_encode($key), LOCK_EX) === false) {
                throw new RuntimeException('ไม่สามารถสร้างกุญแจเข้ารหัส Station Token ได้');
            }
            @chmod($keyPath, 0600);
            return $key;
        }
        $key = base64_decode(trim((string)file_get_contents($keyPath)), true);
        if (!is_string($key) || strlen($key) !== 32) throw new RuntimeException('กุญแจเข้ารหัส Station Token ไม่ถูกต้อง');
        return $key;
    }
}

if (!function_exists('encrypt_station_token')) {
    function encrypt_station_token(string $plainToken): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plainToken, 'aes-256-gcm', station_token_vault_key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) throw new RuntimeException('เข้ารหัส Station Token ไม่สำเร็จ');
        return base64_encode($iv . $tag . $cipher);
    }
}

if (!function_exists('decrypt_station_token')) {
    function decrypt_station_token(?string $payload): string
    {
        if (!$payload) return '';
        try {
            $raw = base64_decode($payload, true);
            if (!is_string($raw) || strlen($raw) < 29) return '';
            $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', station_token_vault_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
            return is_string($plain) ? $plain : '';
        } catch (Throwable $e) { return ''; }
    }
}

if (!function_exists('authenticate_station_token')) {
    function authenticate_station_token(PDO $pdo): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $match)) {
            return null;
        }

        $plainToken = trim($match[1]);
        if (!preg_match('/^ncdst_[A-Za-z0-9_-]{40,}$/', $plainToken)) {
            return null;
        }

        $stmt = $pdo->prepare("SELECT * FROM station_access_tokens
            WHERE token_hash = ? AND revoked_at IS NULL
              AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
        $stmt->execute([hash('sha256', $plainToken)]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$token) {
            return null;
        }

        $stationId = trim($_SERVER['HTTP_X_STATION_ID'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $update = $pdo->prepare("UPDATE station_access_tokens
            SET last_used_at = NOW(), last_ip = ?, last_station_id = ? WHERE token_id = ?");
        $update->execute([$ip, $stationId ?: null, $token['token_id']]);

        $token['permission_list'] = array_values(array_filter(array_map('trim', explode(',', $token['permissions']))));
        $token['station_id'] = $stationId;
        return $token;
    }
}

if (!function_exists('station_token_can')) {
    function station_token_can(array $token, string $permission): bool
    {
        return in_array($permission, $token['permission_list'] ?? [], true);
    }
}

if (!function_exists('station_token_allows_hoscode')) {
    function station_token_allows_hoscode(array $token, string $hoscode): bool
    {
        return $token['hoscode'] === 'ALL' || hash_equals((string)$token['hoscode'], $hoscode);
    }
}
