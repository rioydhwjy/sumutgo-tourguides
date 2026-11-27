<?php

namespace App\Controllers\Api\V1;

use App\Libraries\DbTransactionRunner;
use App\Libraries\PgErrorMapper;
use App\Libraries\PhoneNormalizer;
use App\Models\BookingContactModel;
use App\Models\BookingModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class BookingContactController extends BaseApiController
{
    /**
     * GET /api/v1/booking-contacts
     */
    public function index(): ResponseInterface
    {
        $user   = $this->getCurrentUser();
        $role   = strtolower((string) ($user['role'] ?? 'wisatawan'));
        $userId = (int) ($user['sub'] ?? 0);

        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 30);

        $filters = [
            'booking_id'          => $this->request->getGet('booking_id'),
            'confirmation_status' => $this->request->getGet('confirmation_status'),
            'q'                   => $this->request->getGet('q'),
        ];

        $bookingModel = new BookingModel();
        if ($role === 'wisatawan') {
            $filters['tourist_id'] = $userId;
        } elseif ($role === 'guide') {
            $guideId = $bookingModel->getGuideIdByUserId($userId);
            $filters['guide_id'] = $guideId ?? -1;
        }

        try {
            $model = new BookingContactModel();
            $result = $model->getContacts($filters, $page, $perPage);
            return $this->respondSuccess($result['items'], 'Daftar kontak komunikasi booking berhasil dimuat.', 200, $result['meta']);
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * POST /api/v1/booking-contacts
     */
    public function create(): ResponseInterface
    {
        $payload = $this->getJsonPayload();
        $user    = $this->getCurrentUser();
        $role    = strtolower((string) ($user['role'] ?? 'wisatawan'));
        $userId  = (int) ($user['sub'] ?? 0);

        $rules = [
            'booking_id'        => 'required|numeric',
            'inquiry_message'   => 'required|min_length[3]|max_length[1000]',
            'preferred_channel' => 'permit_empty|in_list[whatsapp,phone,email]',
            'contact_phone'     => 'permit_empty|max_length[25]',
            'contact_email'     => 'permit_empty|valid_email|max_length[100]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->respondError('Validasi data kontak/pesan booking gagal.', 422, $this->getValidationErrors());
        }

        $bookingId = (int) $payload['booking_id'];
        $bookingModel = new BookingModel();
        $booking = $bookingModel->getBookingDetail($bookingId);
        if (! $booking) {
            return $this->respondError("Reservasi dengan ID {$bookingId} tidak ditemukan.", 404);
        }

        // Authorization check
        if ($role === 'wisatawan' && (int) $booking['tourist_id'] !== $userId) {
            return $this->respondError('Akses ditolak: Anda hanya dapat mengirim kontak untuk reservasi milik Anda.', 403);
        }
        if ($role === 'guide') {
            $guideId = $bookingModel->getGuideIdByUserId($userId);
            if (! $guideId || (int) $booking['guide_id'] !== $guideId) {
                return $this->respondError('Akses ditolak: Reservasi ini bukan milik profil pemandu Anda.', 403);
            }
        }

        $userModel = new UserModel();
        $currentUserRow = $userModel->find($userId);
        $rawPhone = trim((string) ($payload['contact_phone'] ?? ($currentUserRow['phone_number'] ?? '')));
        $normalizedPhone = PhoneNormalizer::normalize($rawPhone);

        if (! PhoneNormalizer::isValid($normalizedPhone)) {
            return $this->respondError('Nomor HP kontak tidak valid.', 422, [
                'contact_phone' => 'Masukkan nomor HP yang valid (misal 081234567890 atau +6281234567890).',
            ]);
        }

        $contactEmail = trim((string) ($payload['contact_email'] ?? ($currentUserRow['email'] ?? '')));
        $channel = strtolower(trim((string) ($payload['preferred_channel'] ?? 'whatsapp')));
        if ($channel === '') {
            $channel = 'whatsapp';
        }

        $insertPayload = [
            'booking_id'        => $bookingId,
            'tourist_id'        => (int) $booking['tourist_id'],
            'guide_id'          => (int) $booking['guide_id'],
            'contact_phone'     => $normalizedPhone,
            'contact_email'     => $contactEmail !== '' ? $contactEmail : null,
            'preferred_channel' => $channel,
            'sender_role'       => in_array($role, ['wisatawan', 'guide', 'admin'], true) ? $role : 'wisatawan',
            'inquiry_message'   => trim((string) $payload['inquiry_message']),
        ];

        $contactModel = new BookingContactModel();
        $dbRole = ($role === 'admin') ? 'role_sumutgo_admin' : 'role_sumutgo_mobile';

        try {
            $created = DbTransactionRunner::runAsRole($dbRole, $userId, function () use ($contactModel, $insertPayload) {
                return $contactModel->createContact($insertPayload);
            });

            return $this->respondSuccess($created, 'Pesan & informasi kontak berhasil dikirim.', 201);
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * PATCH /api/v1/booking-contacts/{id}/confirm
     */
    public function confirm(int $id): ResponseInterface
    {
        $payload = $this->getJsonPayload();
        $adminId = $this->getCurrentUserId();

        $rules = [
            'confirmation_status' => 'required|in_list[unverified,contacted,confirmed]',
            'admin_note'          => 'permit_empty|max_length[1000]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->respondError('Validasi status konfirmasi admin gagal.', 422, $this->getValidationErrors());
        }

        $status    = strtolower(trim((string) $payload['confirmation_status']));
        $adminNote = isset($payload['admin_note']) ? trim((string) $payload['admin_note']) : null;
        $model     = new BookingContactModel();

        try {
            $updated = DbTransactionRunner::runAsRole('role_sumutgo_admin', $adminId, function () use ($model, $id, $status, $adminId, $adminNote) {
                return $model->confirmContact($id, $status, (int) $adminId, $adminNote);
            });

            if (! $updated) {
                return $this->respondError("Data kontak booking dengan ID {$id} tidak ditemukan.", 404);
            }

            return $this->respondSuccess($updated, "Status kontak booking berhasil diperbarui menjadi '{$status}'.");
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }
}
