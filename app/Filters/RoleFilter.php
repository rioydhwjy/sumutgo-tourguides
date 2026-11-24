<?php

namespace App\Filters;

use App\Libraries\AuthContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $allowed = [];
        if (! empty($arguments)) {
            foreach ($arguments as $arg) {
                foreach (explode(',', (string) $arg) as $r) {
                    $r = strtolower(trim($r));
                    if ($r !== '') {
                        $allowed[] = $r;
                    }
                }
            }
        }

        $user = AuthContext::getUser() ?? ($request->user ?? null);
        $currentRole = strtolower(trim((string) ($user['role'] ?? '')));

        if (! in_array($currentRole, $allowed, true)) {
            return Services::response()
                ->setStatusCode(403)
                ->setJSON([
                    'status'  => 'error',
                    'message' => "Akses ditolak: role '{$currentRole}' tidak memiliki izin untuk mengakses resource ini.",
                    'data'    => null,
                ]);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
