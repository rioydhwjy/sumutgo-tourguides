<?php

namespace App\Libraries;

use Config\Services;
use Throwable;

/**
 * Server-side HTTP client for External Transport Application REST API.
 * Enforces keeping X-API-KEY (public & secret keys) strictly on the CI4 server.
 */
class TransportApiClient
{
    private string $baseUrl;
    private string $publicKey;
    private string $secretKey;
    private int $timeoutSeconds;

    public function __construct()
    {
        $rawBase = (string) (env('TRANSPORT_API_BASE_URL') ?: 'https://transport-aplication.wrldapp.xyz');
        $this->baseUrl = rtrim(trim($rawBase), '/');
        $this->publicKey = trim((string) (env('TRANSPORT_API_PUBLIC_KEY') ?: ''));
        $this->secretKey = trim((string) (env('TRANSPORT_API_SECRET_KEY') ?: ''));
        $rawTimeout = (int) (env('TRANSPORT_API_TIMEOUT') ?: 12);
        $this->timeoutSeconds = max(3, min(30, $rawTimeout));
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function isKeyConfigured(bool $requireSecret = false): bool
    {
        $key = $requireSecret ? $this->secretKey : $this->resolveReadKey();
        return ! $this->isPlaceholder($key);
    }

    /**
     * Perform a read-only GET request using Public Key (or Secret Key if Public Key is absent).
     */
    public function getPublic(string $path, array $query = []): array
    {
        $key = $this->resolveReadKey();
        if ($this->isPlaceholder($key)) {
            return $this->missingKeyResponse(false);
        }

        return $this->sendRequest('GET', $path, $key, $query, null);
    }

    /**
     * Perform a read/write request specifically with Secret Key (or fallback to GET via Public Key if allowed).
     */
    public function requestRecommendation(string $method, array $params): array
    {
        $cleanMethod = strtoupper(trim($method)) === 'POST' ? 'POST' : 'GET';

        if ($cleanMethod === 'POST') {
            if (! $this->isPlaceholder($this->secretKey)) {
                return $this->sendRequest('POST', '/api/transport/rekomendasi-wisata', $this->secretKey, [], $params);
            }
            // If only Public Key (GET-only) is configured, safely route via GET query params
            $readKey = $this->resolveReadKey();
            if ($this->isPlaceholder($readKey)) {
                return $this->missingKeyResponse(false);
            }
            return $this->sendRequest('GET', '/api/transport/rekomendasi-wisata', $readKey, $params, null);
        }

        $readKey = $this->resolveReadKey();
        if ($this->isPlaceholder($readKey)) {
            return $this->missingKeyResponse(false);
        }

        return $this->sendRequest('GET', '/api/transport/rekomendasi-wisata', $readKey, $params, null);
    }

    /**
     * Perform admin request on external transport master resources strictly using Secret Key.
     */
    public function requestAdmin(string $method, string $path, array $query = [], ?array $jsonBody = null): array
    {
        $upper = strtoupper(trim($method));
        $isMutation = in_array($upper, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        if ($isMutation) {
            if ($this->isPlaceholder($this->secretKey)) {
                return $this->missingKeyResponse(true);
            }
            return $this->sendRequest($upper, $path, $this->secretKey, $query, $jsonBody);
        }

        // For Admin GET, prefer Secret Key and allow fallback to Public Key if Secret Key is not yet set
        $key = ! $this->isPlaceholder($this->secretKey) ? $this->secretKey : $this->resolveReadKey();
        if ($this->isPlaceholder($key)) {
            return $this->missingKeyResponse(true);
        }

        return $this->sendRequest('GET', $path, $key, $query, null);
    }

    private function resolveReadKey(): string
    {
        if (! $this->isPlaceholder($this->publicKey)) {
            return $this->publicKey;
        }
        if (! $this->isPlaceholder($this->secretKey)) {
            return $this->secretKey;
        }
        return '';
    }

    private function isPlaceholder(string $value): bool
    {
        $v = trim($value);
        if ($v === '') {
            return true;
        }
        $upper = strtoupper($v);
        return str_starts_with($upper, 'YOUR_')
            || str_contains($upper, 'MASUKKAN_')
            || str_contains($upper, 'PLACEHOLDER');
    }

    private function missingKeyResponse(bool $requireSecret): array
    {
        $keyName = $requireSecret ? 'TRANSPORT_API_SECRET_KEY' : 'TRANSPORT_API_PUBLIC_KEY';
        return [
            'ok'         => false,
            'statusCode' => 503,
            'message'    => "Integrasi API Transportasi belum aktif: {$keyName} belum dikonfigurasi pada file .env server.",
            'data'       => null,
            'errors'     => [
                'code'    => 'TRANSPORT_KEY_NOT_CONFIGURED',
                'missing' => $keyName,
            ],
        ];
    }

    private function sendRequest(string $method, string $path, string $apiKey, array $query = [], ?array $jsonBody = null): array
    {
        $normalizedPath = '/' . ltrim($path, '/');
        $cleanQuery = [];
        foreach ($query as $k => $v) {
            if ($v !== null && $v !== '') {
                $cleanQuery[$k] = $v;
            }
        }

        $options = [
            'timeout'         => $this->timeoutSeconds,
            'connect_timeout' => min(5, $this->timeoutSeconds),
            'http_errors'     => false,
            'verify'          => true,
            'headers'         => [
                'X-API-KEY'    => $apiKey,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];

        if (! empty($cleanQuery)) {
            $options['query'] = $cleanQuery;
        }
        if ($jsonBody !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $options['json'] = $jsonBody;
        }

        try {
            $client = Services::curlrequest([
                'baseURI' => $this->baseUrl,
            ], null, null, false);
            $response = $client->request($method, $this->baseUrl . $normalizedPath, $options);
            $httpCode = (int) $response->getStatusCode();
            $rawBody = (string) $response->getBody();
        } catch (Throwable $e) {
            log_message('error', '[TransportApiClient] Upstream request failed ({method} {path}): {msg}', [
                'method' => $method,
                'path'   => $normalizedPath,
                'msg'    => $e->getMessage(),
            ]);

            return [
                'ok'         => false,
                'statusCode' => 502,
                'message'    => 'Gagal terhubung ke layanan Transport API atau permintaan melebihi batas waktu. Silakan coba lagi.',
                'data'       => null,
                'errors'     => [
                    'code' => 'TRANSPORT_UPSTREAM_UNREACHABLE',
                ],
            ];
        }

        $decoded = null;
        if (trim($rawBody) !== '') {
            $parsed = json_decode($rawBody, true);
            if (is_array($parsed)) {
                $decoded = $parsed;
            }
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            if ($decoded === null) {
                return [
                    'ok'         => false,
                    'statusCode' => 502,
                    'message'    => 'Respons dari layanan Transport API tidak berbentuk JSON yang valid.',
                    'data'       => null,
                    'errors'     => ['code' => 'TRANSPORT_INVALID_JSON'],
                ];
            }

            // Preserve structured payload cleanly without leaking upstream raw headers/keys
            $dataPayload = array_key_exists('data', $decoded) ? $decoded['data'] : $decoded;
            $metaPayload = isset($decoded['meta']) && is_array($decoded['meta']) ? $decoded['meta'] : null;
            $upstreamMsg = isset($decoded['message']) && is_string($decoded['message']) && trim($decoded['message']) !== ''
                ? trim($decoded['message'])
                : 'Data transportasi berhasil dimuat.';

            return [
                'ok'         => true,
                'statusCode' => $httpCode,
                'message'    => $upstreamMsg,
                'data'       => $dataPayload,
                'meta'       => $metaPayload,
                'raw'        => $decoded,
            ];
        }

        // Never forward raw external HTTP 401 straight as 401 so browser JWT interceptor does not log users out
        if ($httpCode === 401 || $httpCode === 403) {
            log_message('warning', '[TransportApiClient] Upstream auth rejected HTTP {code} on {path}', [
                'code' => $httpCode,
                'path' => $normalizedPath,
            ]);
            return [
                'ok'         => false,
                'statusCode' => 502,
                'message'    => 'Autentikasi layanan Transport API ditolak oleh server eksternal. Periksa kunci API pada konfigurasi server.',
                'data'       => null,
                'errors'     => ['code' => 'TRANSPORT_UPSTREAM_AUTH_FAILED', 'upstream_status' => $httpCode],
            ];
        }

        $mappedStatus = ($httpCode >= 400 && $httpCode < 500) ? $httpCode : 502;
        $upstreamErrMsg = 'Layanan Transport API mengembalikan kesalahan.';
        if (is_array($decoded)) {
            if (! empty($decoded['message']) && is_string($decoded['message'])) {
                $upstreamErrMsg = trim($decoded['message']);
            } elseif (! empty($decoded['error']) && is_string($decoded['error'])) {
                $upstreamErrMsg = trim($decoded['error']);
            }
        }

        return [
            'ok'         => false,
            'statusCode' => $mappedStatus,
            'message'    => $upstreamErrMsg,
            'data'       => null,
            'errors'     => is_array($decoded['errors'] ?? null)
                ? $decoded['errors']
                : ['code' => 'TRANSPORT_UPSTREAM_ERROR', 'upstream_status' => $httpCode],
        ];
    }
}
