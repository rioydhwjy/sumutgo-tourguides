<?php

namespace App\Traits;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

trait ApiResponseTrait
{
    /**
     * Return standardized success JSON response
     */
    protected function respondSuccess($data = null, string $message = 'Operasi berhasil', int $statusCode = 200, ?array $meta = null): ResponseInterface
    {
        $response = [
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ];

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return Services::response()
            ->setStatusCode($statusCode)
            ->setJSON($response);
    }

    /**
     * Return standardized error JSON response
     */
    protected function respondError(string $message, int $statusCode = 400, $errors = null): ResponseInterface
    {
        $response = [
            'status'  => 'error',
            'message' => $message,
            'data'    => null,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return Services::response()
            ->setStatusCode($statusCode)
            ->setJSON($response);
    }
}
