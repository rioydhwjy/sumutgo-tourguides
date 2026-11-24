<?php

namespace App\Controllers\Api\V1;

use App\Libraries\DbTransactionRunner;
use App\Libraries\PgErrorMapper;
use App\Models\MasterDataModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class MasterDataController extends BaseApiController
{
    /**
     * GET /api/v1/master/all
     */
    public function all(): ResponseInterface
    {
        $model = new MasterDataModel();
        return $this->respondSuccess($model->getAllMaster(), 'Daftar seluruh master data berhasil dimuat.');
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
        } catch (Throwable $e) {
            return $this->respondError($e->getMessage(), 404);
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
        } catch (Throwable $e) {
            return $this->respondError($e->getMessage(), 404);
        }
    }

    /**
     * POST /api/v1/master/{resource}
     */
    public function create(string $resource): ResponseInterface
    {
        $payload = $this->getJsonPayload();
        $model = new MasterDataModel();
        $userId = $this->getCurrentUserId();

        try {
            $created = DbTransactionRunner::runAsRole('role_sumutgo_admin', $userId, function () use ($model, $resource, $payload) {
                return $model->createItem($resource, $payload);
            });
            return $this->respondSuccess($created, "Berhasil menambahkan {$resource} baru.", 201);
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
            DbTransactionRunner::runAsRole('role_sumutgo_admin', $userId, function () use ($model, $resource, $id) {
                return $model->deleteItem($resource, $id);
            });
            return $this->respondSuccess(null, "Data {$resource} dengan ID {$id} berhasil dihapus.");
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }
}
