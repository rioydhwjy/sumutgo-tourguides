<?php

namespace App\Filters;

use App\Libraries\AuthContext;
use App\Libraries\JwtService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (! $authHeader || ! preg_match('/^Bearer\s+(.*?)$/i', $authHeader, $matches)) {
            return Services::response()
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Token otorisasi (Bearer JWT) tidak ditemukan atau format salah.',
                    'data'    => null,
                ]);
        }

        $token = trim($matches[1]);
        $jwtService = new JwtService();
        $payload = $jwtService->decodeToken($token);

        if (! $payload) {
            return Services::response()
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Token otorisasi tidak valid atau telah kedaluwarsa.',
                    'data'    => null,
                ]);
        }

        AuthContext::setUser($payload);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No cleanup needed to allow response post-processing
    }
}
