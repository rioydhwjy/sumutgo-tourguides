<?php

namespace App\Controllers\Api\V1;

use App\Libraries\DbTransactionRunner;
use App\Libraries\PgErrorMapper;
use App\Models\BookingContactModel;
use App\Models\BookingModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class BookingController extends BaseApiController
{
    /**
     * GET /api/v1/bookings
     */
    public function index(): ResponseInterface
    {
        $model = new BookingModel();
        $user = $this->getCurrentUser();
        $role = $user['role'] ?? 'wisatawan';
        $userId = (int) ($user['sub'] ?? 0);

        $page = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 15);

        $filters = [
            'status'         => $this->request->getGet('status'),
            'destination_id' => $this->request->getGet('destination_id'),
        ];

        // Scoping based on role
        if ($role === 'wisatawan') {
            $filters['tourist_id'] = $userId;
        } elseif ($role === 'guide') {
            // Find guide record for this user
            $guideId = $model->getGuideIdByUserId($userId);
            $filters['guide_id'] = $guideId ?? -1;
        } else {
            // Admin can filter by tourist_id or guide_id optionally
            if ($this->request->getGet('tourist_id')) {
                $filters['tourist_id'] = (int) $this->request->getGet('tourist_id');
            }
            if ($this->request->getGet('guide_id')) {
                $filters['guide_id'] = (int) $this->request->getGet('guide_id');
            }
        }

        try {
            $result = $model->getBookings($filters, $page, $perPage);
            return $this->respondSuccess($result['items'], 'Daftar reservasi berhasil dimuat.', 200, $result['meta']);
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * GET /api/v1/bookings/{id}
     */
    public function show(int $id): ResponseInterface
    {
        $model = new BookingModel();
        $user = $this->getCurrentUser();
        $role = $user['role'] ?? 'wisatawan';
        $userId = (int) ($user['sub'] ?? 0);

        try {
            $booking = $model->getBookingDetail($id);
            if (! $booking) {
                return $this->respondError("Reservasi dengan ID {$id} tidak ditemukan.", 404);
            }

            // Check authorization
            if ($role === 'wisatawan' && (int) $booking['tourist_id'] !== $userId) {
                return $this->respondError('Akses ditolak: Anda tidak memiliki izin untuk melihat reservasi ini.', 403);
            }
            if ($role === 'guide') {
                $guideId = $model->getGuideIdByUserId($userId);
                if (! $guideId || (int) $booking['guide_id'] !== $guideId) {
                    return $this->respondError('Akses ditolak: Anda tidak memiliki izin untuk melihat reservasi pemandu lain.', 403);
                }
            }

            $contactModel = new BookingContactModel();
            $contactsRes = $contactModel->getContacts(['booking_id' => $id], 1, 20);
            $booking['contacts'] = $contactsRes['items'];

            return $this->respondSuccess($booking, 'Detail reservasi berhasil dimuat.');
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * POST /api/v1/bookings
     */
    public function create(): ResponseInterface
    {
        $payload = $this->getJsonPayload();
        $user = $this->getCurrentUser();
        $role = $user['role'] ?? 'wisatawan';
        $userId = (int) ($user['sub'] ?? 0);

        $rules = [
            'guide_id'         => 'required|numeric',
            'start_date'       => 'required|valid_date[Y-m-d]',
            'end_date'         => 'required|valid_date[Y-m-d]',
            'number_of_guests' => 'required|numeric|greater_than[0]',
            'meeting_point'    => 'permit_empty|max_length[500]',
            'notes'            => 'permit_empty|max_length[1000]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->respondError('Validasi data reservasi gagal.', 422, $this->validator->getErrors());
        }

        // Validate date order
        if (strtotime($payload['end_date']) < strtotime($payload['start_date'])) {
            return $this->respondError('Tanggal selesai tur tidak boleh lebih awal dari tanggal mulai.', 422, [
                'end_date' => 'Tanggal selesai tur harus >= tanggal mulai.',
            ]);
        }

        // Assign tourist_id
        if ($role === 'wisatawan' || empty($payload['tourist_id'])) {
            $payload['tourist_id'] = $userId;
        }

        $model = new BookingModel();
        $dbRole = ($role === 'admin') ? 'role_sumutgo_admin' : 'role_sumutgo_mobile';

        try {
            $created = DbTransactionRunner::runAsRole($dbRole, $userId, function () use ($model, $payload) {
                return $model->createBooking($payload);
            });

            return $this->respondSuccess($created, 'Reservasi pemandu wisata berhasil dibuat.', 201);
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * PATCH /api/v1/bookings/{id}/status
     */
    public function updateStatus(int $id): ResponseInterface
    {
        $payload = $this->getJsonPayload();
        $user = $this->getCurrentUser();
        $role = $user['role'] ?? 'wisatawan';
        $userId = (int) ($user['sub'] ?? 0);

        if (! isset($payload['version'])) {
            return $this->respondError(
                "Field 'version' wajib disertakan untuk mekanisme concurrency control (Optimistic Locking).",
                422,
                ['version' => 'Versi booking saat ini wajib dikirim.']
            );
        }

        $newStatus = trim((string) ($payload['status'] ?? ''));
        $allowedStatuses = ['pending', 'confirmed', 'ongoing', 'completed', 'cancelled'];

        if (! in_array($newStatus, $allowedStatuses, true)) {
            return $this->respondError('Status yang diminta tidak valid.', 422, [
                'status' => 'Status harus salah satu dari: ' . implode(', ', $allowedStatuses),
            ]);
        }

        $model = new BookingModel();
        $booking = $model->getBookingDetail($id);
        if (! $booking) {
            return $this->respondError("Reservasi dengan ID {$id} tidak ditemukan.", 404);
        }

        // Role-based transition check
        if ($role === 'wisatawan') {
            if ((int) $booking['tourist_id'] !== $userId) {
                return $this->respondError('Akses ditolak: Anda bukan pemilik reservasi ini.', 403);
            }
            if ($newStatus !== 'cancelled') {
                return $this->respondError("Wisatawan hanya diizinkan untuk mengubah status menjadi 'cancelled' (membatalkan).", 403);
            }
        } elseif ($role === 'guide') {
            $guideId = $model->getGuideIdByUserId($userId);
            if (! $guideId || (int) $booking['guide_id'] !== $guideId) {
                return $this->respondError('Akses ditolak: Anda hanya dapat mengelola reservasi untuk profil pemandu Anda.', 403);
            }
        }

        $expectedVersion = (int) $payload['version'];
        $cancelReason = isset($payload['cancel_reason']) ? trim((string) $payload['cancel_reason']) : null;
        $dbRole = ($role === 'admin') ? 'role_sumutgo_admin' : 'role_sumutgo_mobile';

        try {
            $updated = DbTransactionRunner::runAsRole($dbRole, $userId, function () use ($model, $id, $newStatus, $expectedVersion, $userId, $cancelReason) {
                return $model->updateBookingStatus($id, $newStatus, $expectedVersion, $userId, $cancelReason);
            });

            return $this->respondSuccess($updated, "Status reservasi {$booking['booking_code']} berhasil diperbarui menjadi {$newStatus}.");
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }
}
