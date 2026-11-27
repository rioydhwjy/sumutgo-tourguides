<?php

namespace App\Controllers\Api\V1;

use App\Libraries\DbTransactionRunner;
use App\Libraries\PgErrorMapper;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class UserController extends BaseApiController
{
    /**
     * GET /api/v1/users (Admin only)
     */
    public function index(): ResponseInterface
    {
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 30);

        $filters = [
            'role'        => $this->request->getGet('role'),
            'is_verified' => $this->request->getGet('is_verified'),
            'q'           => $this->request->getGet('q'),
        ];

        try {
            $model = new UserModel();
            $result = $model->getUsersForAdmin($filters, $page, $perPage);
            return $this->respondSuccess($result['items'], 'Daftar pengguna berhasil dimuat.', 200, $result['meta']);
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * PATCH /api/v1/users/{id}/verify (Admin only)
     */
    public function verify(int $id): ResponseInterface
    {
        $payload = $this->getJsonPayload();
        $adminId = $this->getCurrentUserId();

        $isVerifiedRaw = $payload['is_verified'] ?? true;
        $isVerified = filter_var($isVerifiedRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isVerified === null) {
            return $this->respondError("Field 'is_verified' harus bernilai boolean (true/false).", 422, [
                'is_verified' => 'Nilai verifikasi tidak valid.',
            ]);
        }

        $note = isset($payload['verification_note']) ? trim((string) $payload['verification_note']) : null;
        if ($note !== null && mb_strlen($note) > 1000) {
            return $this->respondError('Catatan verifikasi maksimal 1000 karakter.', 422, [
                'verification_note' => 'Catatan terlalu panjang.',
            ]);
        }

        $model = new UserModel();

        try {
            $updated = DbTransactionRunner::runAsRole('role_sumutgo_admin', $adminId, function () use ($model, $id, $adminId, $isVerified, $note) {
                return $model->confirmVerification($id, (int) $adminId, $isVerified, $note);
            });

            if (! $updated) {
                return $this->respondError("Pengguna dengan ID {$id} tidak ditemukan.", 404);
            }

            $label = $isVerified ? 'terkonfirmasi' : 'belum terkonfirmasi';
            return $this->respondSuccess($updated, "Akun pengguna {$updated['full_name']} berhasil ditandai {$label}.");
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }
}
