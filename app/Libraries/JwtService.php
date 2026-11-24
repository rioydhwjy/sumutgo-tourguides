<?php

namespace App\Libraries;

use Exception;

class JwtService
{
    private string $secret;
    private int $ttl;

    public function __construct()
    {
        $this->secret = (string) (env('JWT_SECRET') ?: 'default-sumutgo-secret-key-32bytesmin!!');
        $this->ttl = (int) (env('JWT_TTL') ?: 86400);
    }

    /**
     * Generate a new JWT token for a user
     */
    public function generateToken(array $user): string
    {
        $issuedAt = time();
        $expire = $issuedAt + $this->ttl;

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $payload = [
            'sub'       => (int) ($user['user_id'] ?? $user['sub'] ?? 0),
            'email'     => (string) ($user['email'] ?? ''),
            'full_name' => (string) ($user['full_name'] ?? ''),
            'role'      => (string) ($user['role'] ?? 'wisatawan'),
            'iat'       => $issuedAt,
            'exp'       => $expire,
        ];

        $headerEncoded  = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $this->secret, true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    /**
     * Decode and validate a JWT token. Returns payload array on success, or null if invalid/expired.
     */
    public function decodeToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = json_decode($this->base64UrlDecode($headerB64), true);
        if (! is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
            return null;
        }

        $expectedSig = hash_hmac('sha256', "$headerB64.$payloadB64", $this->secret, true);
        $providedSig = $this->base64UrlDecode($signatureB64);

        if (! hash_equals($expectedSig, $providedSig)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        if (! is_array($payload)) {
            return null;
        }

        // Check expiration
        if (! isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padLen = 4 - $remainder;
            $data .= str_repeat('=', $padLen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
