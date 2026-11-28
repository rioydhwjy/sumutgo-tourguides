<?php

namespace App\Controllers\Api\V1;

use App\Libraries\DbTransactionRunner;
use App\Libraries\PgErrorMapper;
use App\Libraries\PhoneNormalizer;
use App\Models\TourGuideModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class GuideController extends BaseApiController
{
    /**
     * GET /api/v1/guides
     */
    public function index(): ResponseInterface
    {
        $model = new TourGuideModel();

        $page = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 15);

        $filters = [
            'status'            => $this->request->getGet('status'),
            'specialization_id' => $this->request->getGet('specialization_id'),
            'destination_id'    => $this->request->getGet('destination_id'),
            'language_id'       => $this->request->getGet('language_id'),
            'min_rate'          => $this->request->getGet('min_rate'),
            'max_rate'          => $this->request->getGet('max_rate'),
            'min_rating'        => $this->request->getGet('min_rating'),
            'q'                 => $this->request->getGet('q'),
            'sort'              => $this->request->getGet('sort'),
        ];

        try {
            $result = $model->getFilteredGuides($filters, $page, $perPage);
            return $this->respondSuccess($result['items'], 'Daftar pemandu wisata berhasil dimuat.', 200, $result['meta']);
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * GET /api/v1/guides/{id}
     */
    public function show(string $id): ResponseInterface
    {
        $model = new TourGuideModel();
        try {
            $guide = $model->getGuideDetail($id);
            if (! $guide) {
                return $this->respondError("Pemandu wisata '{$id}' tidak ditemukan.", 404);
            }
            return $this->respondSuccess($guide, 'Detail pemandu wisata berhasil dimuat.');
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * POST /api/v1/guides
     */
    public function create(): ResponseInterface
    {
        $payload = $this->getJsonPayload();

        $rules = [
            'guide_name'              => 'required|min_length[3]|max_length[150]',
            'phone_number'            => 'required|min_length[8]|max_length[25]',
            'daily_rate_idr'          => 'required|numeric|greater_than[0]',
            'specialization_id'       => 'required|numeric',
            'primary_destination_id'  => 'required|numeric',
            'recommended_culinary_id' => 'required|numeric',
            'partner_hotel_id'        => 'required|numeric',
            'default_transport_id'    => 'required|numeric',
            'availability_status'     => 'permit_empty|in_list[Tersedia,Sedang Bertugas,Booked]',
            'gmaps_rating'            => 'permit_empty|numeric|greater_than_equal_to[0]|less_than_equal_to[5]',
            'latitude'                => 'permit_empty|numeric',
            'longitude'               => 'permit_empty|numeric',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->respondError('Validasi data pemandu wisata gagal.', 422, $this->getValidationErrors());
        }

        $normalizedPhone = PhoneNormalizer::normalize((string) ($payload['phone_number'] ?? ''));
        if (! PhoneNormalizer::isValid($normalizedPhone)) {
            return $this->respondError('Format nomor telepon pemandu tidak valid.', 422, [
                'phone_number' => 'Gunakan nomor HP Indonesia/internasional yang valid, contoh: 081234567890 atau +6281234567890.',
            ]);
        }

        $languageIds = $payload['language_ids'] ?? [];
        if (! is_array($languageIds)) {
            $languageIds = [];
        }

        $userId = $this->getCurrentUserId();
        $model = new TourGuideModel();

        try {
            $created = DbTransactionRunner::runAsRole('role_sumutgo_admin', $userId, function () use ($model, $payload, $languageIds) {
                return $model->createGuide($payload, $languageIds);
            });

            return $this->respondSuccess($created, 'Pemandu wisata baru berhasil ditambahkan.', 201);
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * PUT /api/v1/guides/{id}
     */
    public function update(int $id): ResponseInterface
    {
        $payload = $this->getJsonPayload();

        $rules = [
            'version'                 => 'required|integer',
            'guide_name'              => 'permit_empty|min_length[3]|max_length[150]',
            'phone_number'            => 'permit_empty|min_length[8]|max_length[25]',
            'daily_rate_idr'          => 'permit_empty|numeric|greater_than[0]',
            'availability_status'     => 'permit_empty|in_list[Tersedia,Sedang Bertugas,Booked]',
            'specialization_id'       => 'permit_empty|numeric',
            'primary_destination_id'  => 'permit_empty|numeric',
            'recommended_culinary_id' => 'permit_empty|numeric',
            'partner_hotel_id'        => 'permit_empty|numeric',
            'default_transport_id'    => 'permit_empty|numeric',
            'latitude'                => 'permit_empty|numeric',
            'longitude'               => 'permit_empty|numeric',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->respondError('Validasi data pembaruan pemandu gagal.', 422, $this->getValidationErrors());
        }

        if (isset($payload['phone_number'])) {
            $normalizedPhone = PhoneNormalizer::normalize((string) $payload['phone_number']);
            if (! PhoneNormalizer::isValid($normalizedPhone)) {
                return $this->respondError('Format nomor telepon pemandu tidak valid.', 422, [
                    'phone_number' => 'Gunakan nomor HP Indonesia/internasional yang valid, contoh: 081234567890 atau +6281234567890.',
                ]);
            }
        }

        $expectedVersion = (int) $payload['version'];
        $languageIds = isset($payload['language_ids']) && is_array($payload['language_ids']) ? $payload['language_ids'] : null;

        $userId = $this->getCurrentUserId();
        $model = new TourGuideModel();

        try {
            $updated = DbTransactionRunner::runAsRole('role_sumutgo_admin', $userId, function () use ($model, $id, $payload, $languageIds, $expectedVersion) {
                return $model->updateGuide($id, $payload, $languageIds, $expectedVersion);
            });

            return $this->respondSuccess($updated, 'Data pemandu wisata berhasil diperbarui.');
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * DELETE /api/v1/guides/{id}
     */
    public function delete(int $id): ResponseInterface
    {
        $userId = $this->getCurrentUserId();
        $model = new TourGuideModel();

        try {
            DbTransactionRunner::runAsRole('role_sumutgo_admin', $userId, function () use ($model, $id) {
                return $model->deleteGuide($id);
            });

            return $this->respondSuccess(null, "Pemandu wisata dengan ID {$id} berhasil dihapus.");
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }
}
