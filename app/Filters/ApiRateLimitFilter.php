<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class ApiRateLimitFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $throttler = Services::throttler();
        $ip = $request->getIPAddress();
        $uri = $request->getUri()->getPath();

        $capacity = 60;
        $seconds = 60;

        if (! empty($arguments)) {
            if (isset($arguments[0])) {
                $capacity = (int) $arguments[0];
            }
            if (isset($arguments[1])) {
                $seconds = (int) $arguments[1];
            }
        } elseif (str_contains($uri, 'auth/login') || str_contains($uri, 'auth/register')) {
            $capacity = 15;
            $seconds = 60;
        }

        $key = md5("ratelimit_{$ip}_{$uri}");
        if ($throttler->check($key, $capacity, $seconds) === false) {
            $retryAfter = $throttler->getTokenTime();
            return Services::response()
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string) $retryAfter)
                ->setJSON([
                    'status'  => 'error',
                    'message' => "Terlalu banyak permintaan (Rate limit terlampaui). Silakan coba lagi dalam {$retryAfter} detik.",
                    'data'    => null,
                ]);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
