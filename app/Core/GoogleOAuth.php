<?php
namespace App\Core;

/**
 * Google OpenID Connect login. It is deliberately disabled until all three
 * GOOGLE_* environment values are supplied; no credentials are stored here.
 */
final class GoogleOAuth
{
    private const AUTHORIZE_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const CERTS_ENDPOINT = 'https://www.googleapis.com/oauth2/v3/certs';
    private const SESSION_KEY = '_google_oauth';

    public static function enabled(): bool
    {
        $id = trim((string)getenv('GOOGLE_CLIENT_ID'));
        $secret = trim((string)getenv('GOOGLE_CLIENT_SECRET'));
        $redirect = trim((string)getenv('GOOGLE_REDIRECT_URI'));
        $parts = parse_url($redirect);
        return $id !== '' && $secret !== '' && filter_var($redirect, FILTER_VALIDATE_URL) !== false
            && is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http','https'], true)
            && trim((string)($parts['host'] ?? '')) !== '';
    }

    public static function authorizationUrl(string $locale): string
    {
        if (!self::enabled()) throw new \RuntimeException('Google sign-in is not configured.');
        $verifier = self::base64url(random_bytes(64));
        $state = self::base64url(random_bytes(32));
        $nonce = self::base64url(random_bytes(32));
        $_SESSION[self::SESSION_KEY] = [
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'locale' => valid_locale($locale) ? $locale : default_locale(),
            'expires' => time() + 600,
        ];
        $query = [
            'client_id' => trim((string)getenv('GOOGLE_CLIENT_ID')),
            'redirect_uri' => trim((string)getenv('GOOGLE_REDIRECT_URI')),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => self::base64url(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ];
        return self::AUTHORIZE_ENDPOINT . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{locale:string,user:array} */
    public static function complete(string $code, string $state): array
    {
        $stored = $_SESSION[self::SESSION_KEY] ?? null;
        unset($_SESSION[self::SESSION_KEY]);
        if (!is_array($stored) || empty($stored['state']) || !hash_equals((string)$stored['state'], $state) || (int)($stored['expires'] ?? 0) < time()) {
            throw new \RuntimeException('Invalid Google sign-in state.');
        }
        if ($code === '' || !self::enabled()) throw new \RuntimeException('Google sign-in is unavailable.');
        $token = self::post(self::TOKEN_ENDPOINT, [
            'code' => $code,
            'client_id' => trim((string)getenv('GOOGLE_CLIENT_ID')),
            'client_secret' => trim((string)getenv('GOOGLE_CLIENT_SECRET')),
            'redirect_uri' => trim((string)getenv('GOOGLE_REDIRECT_URI')),
            'grant_type' => 'authorization_code',
            'code_verifier' => (string)($stored['verifier'] ?? ''),
        ]);
        $claims = self::verifyIdToken((string)($token['id_token'] ?? ''), (string)$stored['nonce']);
        $user = self::findOrCreateUser($claims);
        self::establishSession($user);
        return ['locale' => (string)($stored['locale'] ?? default_locale()), 'user' => $user];
    }

    private static function findOrCreateUser(array $claims): array
    {
        $sub = (string)$claims['sub'];
        $email = mb_strtolower(trim((string)$claims['email']));
        $identity = Database::one('SELECT user_id FROM oauth_identities WHERE provider=? AND subject=? LIMIT 1', ['google', $sub]);
        if ($identity) {
            $user = Database::one('SELECT * FROM users WHERE id=? LIMIT 1', [(int)$identity['user_id']]);
            if (!$user || (string)$user['status'] === 'blocked') throw new \RuntimeException('This account is unavailable.');
            return $user;
        }
        // A verified Google email may link to one existing Saradeeeb account;
        // a second account is never created for the same address.
        $user = Database::one('SELECT * FROM users WHERE email=? LIMIT 1', [$email]);
        if ($user) {
            if ((string)$user['status'] === 'blocked') throw new \RuntimeException('This account is unavailable.');
        } else {
            $username = self::uniqueUsername($email, $sub);
            $name = trim((string)($claims['name'] ?? '')) ?: strstr($email, '@', true) ?: 'Google member';
            $algo = in_array('argon2id', password_algos(), true) ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
            $id = Database::insert('INSERT INTO users(name,username,email,password_hash,role,status,email_verified_at) VALUES(?,?,?,?,\'member\',\'active\',CURRENT_TIMESTAMP)', [
                mb_substr($name, 0, 120), $username, $email, password_hash(bin2hex(random_bytes(32)), $algo),
            ]);
            $user = Database::one('SELECT * FROM users WHERE id=? LIMIT 1', [$id]);
        }
        Database::insert('INSERT INTO oauth_identities(provider,subject,user_id,email,created_at,updated_at) VALUES(?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)', ['google', $sub, (int)$user['id'], $email]);
        return $user;
    }

    private static function uniqueUsername(string $email, string $sub): string
    {
        $local = strstr($email, '@', true) ?: '';
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $local) ?? '');
        $base = trim($base, '-');
        if ($base === '' || !preg_match('/^[a-z]/', $base)) $base = 'google-member';
        $base = mb_substr($base, 0, 96);
        $candidate = $base;
        $n = 2;
        while (Database::one('SELECT id FROM users WHERE username=? LIMIT 1', [$candidate])) {
            $candidate = mb_substr($base, 0, 90) . '-' . $n++;
            if ($n > 1000) $candidate = 'google-' . substr(hash('sha256', $sub), 0, 24);
        }
        return $candidate;
    }

    private static function establishSession(array $user): void
    {
        session_regenerate_id(true);
        if (($user['role'] ?? '') === 'admin' && !empty($user['two_factor_enabled'])) {
            unset($_SESSION['user_id']);
            $_SESSION['two_factor_pending_user_id'] = (int)$user['id'];
            $_SESSION['two_factor_pending_started'] = time();
        } else {
            $_SESSION['user_id'] = (int)$user['id'];
        }
        try { Database::exec('UPDATE users SET last_login_at=CURRENT_TIMESTAMP WHERE id=?', [(int)$user['id']]); } catch (\Throwable) {}
        if (function_exists('audit_log')) audit_log('google_login_success', 'user', (int)$user['id'], '');
    }

    private static function verifyIdToken(string $jwt, string $expectedNonce): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) throw new \RuntimeException('Invalid Google identity token.');
        $header = json_decode(self::base64decode($parts[0]), true);
        $payload = json_decode(self::base64decode($parts[1]), true);
        $signature = self::base64decode($parts[2]);
        if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'RS256' || $signature === '') throw new \RuntimeException('Invalid Google identity token.');
        $keys = self::getJson(self::CERTS_ENDPOINT);
        $keyData = null;
        foreach (($keys['keys'] ?? []) as $key) if (($key['kid'] ?? '') === ($header['kid'] ?? '')) { $keyData = $key; break; }
        if (!$keyData || empty($keyData['x5c'][0])) throw new \RuntimeException('Google signing key unavailable.');
        $public = openssl_get_publickey("-----BEGIN CERTIFICATE-----\n" . chunk_split($keyData['x5c'][0], 64, "\n") . "-----END CERTIFICATE-----\n");
        if ($public === false || openssl_verify($parts[0] . '.' . $parts[1], $signature, $public, OPENSSL_ALGO_SHA256) !== 1) throw new \RuntimeException('Google identity verification failed.');
        $iss = (string)($payload['iss'] ?? '');
        $now = time();
        if (!in_array($iss, ['https://accounts.google.com', 'accounts.google.com'], true) || (string)($payload['aud'] ?? '') !== trim((string)getenv('GOOGLE_CLIENT_ID')) || (int)($payload['exp'] ?? 0) < $now || (int)($payload['iat'] ?? 0) > $now + 120 || (isset($payload['nbf']) && (int)$payload['nbf'] > $now + 30) || !hash_equals((string)($payload['nonce'] ?? ''), $expectedNonce)) throw new \RuntimeException('Google identity claims are invalid.');
        if (!filter_var((string)($payload['email'] ?? ''), FILTER_VALIDATE_EMAIL) || !in_array($payload['email_verified'] ?? false, [true, 'true', 1, '1'], true)) throw new \RuntimeException('Google did not provide a verified email.');
        return $payload;
    }

    private static function post(string $url, array $data): array { return self::request($url, ['Content-Type: application/x-www-form-urlencoded'], http_build_query($data, '', '&', PHP_QUERY_RFC3986)); }
    private static function getJson(string $url): array { return self::request($url, ['Accept: application/json']); }
    private static function request(string $url, array $headers, ?string $body = null): array
    {
        if (!function_exists('curl_init')) throw new \RuntimeException('cURL is unavailable.');
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => $headers]);
        if ($body !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
        $raw = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch);
        if ($raw === false || $status < 200 || $status >= 300) throw new \RuntimeException($error ?: 'Google request failed.');
        $json = json_decode((string)$raw, true);
        if (!is_array($json)) throw new \RuntimeException('Invalid Google response.');
        return $json;
    }

    private static function base64url(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
    private static function base64decode(string $value): string { $value = strtr($value, '-_', '+/'); return base64_decode($value . str_repeat('=', (4 - strlen($value) % 4) % 4), true) ?: ''; }
}
