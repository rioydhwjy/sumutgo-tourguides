<?php

namespace App\Controllers\Api\V1;

use App\Libraries\DbTransactionRunner;
use App\Libraries\PgErrorMapper;
use App\Models\MasterDataModel;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use Throwable;

class MasterDataController extends BaseApiController
{
    /**
     * Aturan validasi per resource master data (sinkron dengan constraint DDL PostgreSQL).
     *
     * @var array<string, array<string, array{rule: string, required_on_create: bool}>>
     */
    private const RESOURCE_RULES = [
        'specializations' => [
            'specialization_name' => ['rule' => 'min_length[2]|max_length[100]', 'required_on_create' => true],
            'description'         => ['rule' => 'permit_empty|max_length[1000]', 'required_on_create' => false],
        ],
        'languages' => [
            'language_name' => ['rule' => 'min_length[2]|max_length[50]', 'required_on_create' => true],
        ],
        'destinations' => [
            'destination_code' => ['rule' => 'regex_match[/^WST-[0-9]{2,3}$/]', 'required_on_create' => true],
            'destination_name' => ['rule' => 'min_length[2]|max_length[150]', 'required_on_create' => true],
        ],
        'culinaries' => [
            'culinary_code' => ['rule' => 'regex_match[/^KLN-[0-9]{2,3}$/]', 'required_on_create' => true],
            'culinary_name' => ['rule' => 'min_length[2]|max_length[150]', 'required_on_create' => true],
        ],
        'hotels' => [
            'hotel_code' => ['rule' => 'regex_match[/^HTL-[0-9]{2,3}$/]', 'required_on_create' => true],
            'hotel_name' => ['rule' => 'min_length[2]|max_length[150]', 'required_on_create' => true],
        ],
        'transports' => [
            'transport_code' => ['rule' => 'regex_match[/^TRP-[0-9]{2,3}$/]', 'required_on_create' => true],
            'transport_name' => ['rule' => 'min_length[2]|max_length[150]', 'required_on_create' => true],
        ],
    ];

    /**
     * GET /api/v1/master/all
     */
    public function all(): ResponseInterface
    {
        $model = new MasterDataModel();
        try {
            return $this->respondSuccess($model->getAllMaster(), 'Daftar seluruh master data berhasil dimuat.');
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * GET /api/v1/master/{resource}
     */
    public function index(string $resource): ResponseInterface
    {
        $model = new MasterDataModel();
        try {
            $data = $model->getItems($resource);
            return $this->respondSuccess($data, "Data {$resource} berhasil dimuat.");
        } catch (InvalidArgumentException $e) {
            return $this->respondError($e->getMessage(), 404);
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * GET /api/v1/master/{resource}/{id}
     */
    public function show(string $resource, int $id): ResponseInterface
    {
        $model = new MasterDataModel();
        try {
            $item = $model->getItem($resource, $id);
            if (! $item) {
                return $this->respondError("Data {$resource} dengan ID {$id} tidak ditemukan.", 404);
            }
            return $this->respondSuccess($item, "Detail {$resource} berhasil dimuat.");
        } catch (InvalidArgumentException $e) {
            return $this->respondError($e->getMessage(), 404);
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * POST /api/v1/master/{resource}
     */
    public function create(string $resource): ResponseInterface
    {
        $payload = $this->getJsonPayload();
        if ($err = $this->validateMasterPayload($resource, $payload, false)) {
            return $err;
        }

        $model = new MasterDataModel();
        $userId = $this->getCurrentUserId();

        try {
            $created = DbTransactionRunner::runAsRole('role_sumutgo_admin', $userId, function () use ($model, $resource, $payload) {
                return $model->createItem($resource, $payload);
            });
            return $this->respondSuccess($created, "Berhasil menambahkan {$resource} baru.", 201);
        } catch (InvalidArgumentException $e) {
            return $this->respondError($e->getMessage(), 404);
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * PUT /api/v1/master/{resource}/{id}
     */
    public function update(string $resource, int $id): ResponseInterface
    {
        $payload = $this->getJsonPayload();
        if ($err = $this->validateMasterPayload($resource, $payload, true)) {
            return $err;
        }

        $model = new MasterDataModel();
        $userId = $this->getCurrentUserId();

        try {
            $updated = DbTransactionRunner::runAsRole('role_sumutgo_admin', $userId, function () use ($model, $resource, $id, $payload) {
                return $model->updateItem($resource, $id, $payload);
            });
            if (! $updated) {
                return $this->respondError("Data {$resource} dengan ID {$id} tidak ditemukan.", 404);
            }
            return $this->respondSuccess($updated, "Berhasil memperbarui data {$resource}.");
        } catch (InvalidArgumentException $e) {
            return $this->respondError($e->getMessage(), 404);
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * DELETE /api/v1/master/{resource}/{id}
     */
    public function delete(string $resource, int $id): ResponseInterface
    {
        $model = new MasterDataModel();
        $userId = $this->getCurrentUserId();

        try {
            $deleted = DbTransactionRunner::runAsRole('role_sumutgo_admin', $userId, function () use ($model, $resource, $id) {
                return $model->deleteItem($resource, $id);
            });
            if (! $deleted) {
                return $this->respondError("Data {$resource} dengan ID {$id} tidak ditemukan.", 404);
            }
            return $this->respondSuccess(null, "Data {$resource} dengan ID {$id} berhasil dihapus.");
        } catch (InvalidArgumentException $e) {
            return $this->respondError($e->getMessage(), 404);
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * Validasi payload master data secara seragam sebelum menyentuh transaksi database.
     */
    private function validateMasterPayload(string $resource, array $payload, bool $isUpdate): ?ResponseInterface
    {
        $key = strtolower(trim($resource));
        if (! isset(self::RESOURCE_RULES[$key])) {
            return $this->respondError("Master resource '{$resource}' tidak valid.", 404);
        }

        $schema = self::RESOURCE_RULES[$key];
        $rules = [];
        $hasEditableField = false;

        foreach ($schema as $field => $meta) {
            if (array_key_exists($field, $payload)) {
                $hasEditableField = true;
            }
            $prefix = (! $isUpdate && $meta['required_on_create']) ? 'required|' : 'permit_empty|';
            $rules[$field] = $prefix . $meta['rule'];
        }

        if ($isUpdate && ! $hasEditableField) {
            return $this->respondError("Minimal satu field valid untuk '{$resource}' wajib dikirim saat pembaruan.", 422, [
                'fields' => 'Field yang didukung: ' . implode(', ', array_keys($schema)),
            ]);
        }

        if (! $this->validateData($payload, $rules)) {
            return $this->respondError("Validasi data master '{$resource}' gagal.", 422, $this->getValidationErrors());
        }

        return null;
    }
}
