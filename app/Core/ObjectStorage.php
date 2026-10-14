<?php
namespace App\Core;

/**
 * Small S3-compatible object-storage client for deployments where PHP's local
 * filesystem is ephemeral (including Vercel). It intentionally reads all
 * credentials from environment variables and never persists them.
 */
final class ObjectStorage {
    private static function env(string $key): string { return trim((string)(getenv($key) ?: '')); }

    public static function enabled(): bool {
        return strtolower(self::env('OBJECT_STORAGE_DRIVER')) === 's3'
            && self::env('OBJECT_STORAGE_ENDPOINT') !== ''
            && self::env('OBJECT_STORAGE_BUCKET') !== ''
            && self::env('OBJECT_STORAGE_ACCESS_KEY_ID') !== ''
            && self::env('OBJECT_STORAGE_SECRET_ACCESS_KEY') !== ''
            && self::env('OBJECT_STORAGE_PUBLIC_URL') !== '';
    }

    public static function put(string $source, string $key, string $mime): string {
        if (!self::enabled()) throw new \RuntimeException('لم يُضبط التخزين الكائني للإنتاج.');
        if (!is_file($source)) throw new \RuntimeException('الملف المرفوع غير متاح.');
        self::request('PUT', self::key($key), $source, $mime);
        return self::publicUrl($key);
    }

    public static function delete(string $path): void {
        if (!self::enabled()) return;
        $key = self::pathToKey($path);
        if ($key !== null) self::request('DELETE', $key);
    }

    public static function publicUrl(string $key): string {
        return rtrim(self::env('OBJECT_STORAGE_PUBLIC_URL'), '/') . '/' . self::encodePath(self::key($key));
    }

    private static function key(string $key): string {
        $prefix = trim(self::env('OBJECT_STORAGE_PREFIX'), '/');
        $key = ltrim($key, '/');
        return $prefix === '' ? $key : $prefix . '/' . $key;
    }

    private static function pathToKey(string $path): ?string {
        $public = rtrim(self::env('OBJECT_STORAGE_PUBLIC_URL'), '/');
        if ($public !== '' && str_starts_with($path, $public . '/')) return rawurldecode(substr($path, strlen($public) + 1));
        if (str_starts_with($path, '/uploads/')) return self::key(ltrim($path, '/'));
        return null;
    }

    private static function request(string $method, string $key, ?string $file = null, string $mime = 'application/octet-stream'): void {
        $endpoint = rtrim(self::env('OBJECT_STORAGE_ENDPOINT'), '/');
        $bucket = self::env('OBJECT_STORAGE_BUCKET');
        $region = self::env('OBJECT_STORAGE_REGION') ?: 'auto';
        $access = self::env('OBJECT_STORAGE_ACCESS_KEY_ID');
        $secret = self::env('OBJECT_STORAGE_SECRET_ACCESS_KEY');
        $url = $endpoint . '/' . rawurlencode($bucket) . '/' . self::encodePath($key);
        $parts = parse_url($endpoint);
        $host = (string)($parts['host'] ?? '');
        if (!empty($parts['port'])) $host .= ':' . $parts['port'];
        if ($host === '') throw new \RuntimeException('عنوان التخزين الكائني غير صالح.');
        $payloadHash = $file === null ? hash('sha256', '') : hash_file('sha256', $file);
        $now = gmdate('Ymd\\THis\\Z'); $date = gmdate('Ymd');
        $canonicalUri = '/' . rawurlencode($bucket) . '/' . self::encodePath($key);
        $canonicalHeaders = "host:$host\n" . "x-amz-content-sha256:$payloadHash\n" . "x-amz-date:$now\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        $canonical = "$method\n$canonicalUri\n\n$canonicalHeaders\n$signedHeaders\n$payloadHash";
        $scope = "$date/$region/s3/aws4_request";
        $toSign = "AWS4-HMAC-SHA256\n$now\n$scope\n" . hash('sha256', $canonical);
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $signature = hash_hmac('sha256', $toSign, hash_hmac('sha256', 'aws4_request', $kService, true));
        $headers = [
            'Host: ' . $host,
            'Content-Type: ' . $mime,
            'X-Amz-Content-Sha256: ' . $payloadHash,
            'X-Amz-Date: ' . $now,
            'Authorization: AWS4-HMAC-SHA256 Credential=' . $access . '/' . $scope . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature,
        ];
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 60]);
        $handle = null;
        if ($file !== null) { $handle = fopen($file, 'rb'); curl_setopt($curl, CURLOPT_UPLOAD, true); curl_setopt($curl, CURLOPT_INFILE, $handle); curl_setopt($curl, CURLOPT_INFILESIZE, filesize($file)); }
        $response = curl_exec($curl); $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE); $error = curl_error($curl);
        if (is_resource($handle)) fclose($handle); curl_close($curl);
        if ($response === false || $status < 200 || $status >= 300) throw new \RuntimeException('تعذر الاتصال بالتخزين الكائني (' . ($status ?: 'network') . '). ' . ($error ? 'تحقق من الإعدادات والاتصال.' : ''));
    }

    private static function encodePath(string $path): string { return implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/')))); }
}
