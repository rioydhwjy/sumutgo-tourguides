<?php

namespace App\Controllers\Api\V1;

use App\Libraries\TransportApiClient;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Proxy REST Controller untuk menjembatani frontend SumutGO ke
 * layanan eksternal Transport Application API tanpa mengekspos X-API-KEY ke browser.
 */
class TransportProxyController extends BaseApiController
{
    private const ADMIN_MASTER_RESOURCES = ['moda', 'lokasi', 'penyedia', 'jadwal'];

    /**
     * GET /api/v1/transport/status
     * Memberikan status kesiapan konfigurasi tanpa membocorkan nilai kunci API.
     */
    public function status(): ResponseInterface
    {
        $client = new TransportApiClient();

        return $this->respondSuccess([
            'configured'        => $client->isKeyConfigured(false),
            'admin_configured'  => $client->isKeyConfigured(true),
            'base_url'          => $client->getBaseUrl(),
        ], 'Status integrasi layanan Transport API.');
    }

    /**
     * GET /api/v1/transport/transports
     */
    public function transports(): ResponseInterface
    {
        $client = new TransportApiClient();
        $res = $client->getPublic('/api/transport/transports');

        return $this->forwardClientResult($res);
    }

    /**
     * GET /api/v1/transport/lokasi?q=...&tipe=...
     */
    public function lokasi(): ResponseInterface
    {
        $q = trim((string) ($this->request->getGet('q') ?? ''));
        $tipe = trim((string) ($this->request->getGet('tipe') ?? ''));

        if (mb_strlen($q) > 120) {
            return $this->respondError('Kata kunci pencarian lokasi maksimal 120 karakter.', 422, [
                'q' => 'Kata kunci terlalu panjang.',
            ]);
        }

        if ($tipe !== '' && ! preg_match('/^[a-zA-Z0-9_\-\s]{1,40}$/', $tipe)) {
            return $this->respondError('Parameter tipe lokasi tidak valid.', 422, [
                'tipe' => 'Gunakan tipe lokasi yang valid (mis. bandara, stasiun, terminal, halte, pelabuhan).',
            ]);
        }

        $query = [];
        if ($q !== '') {
            $query['q'] = $q;
        }
        if ($tipe !== '' && strtoupper($tipe) !== 'ALL') {
            $query['tipe'] = strtolower($tipe);
        }

        $client = new TransportApiClient();
        $res = $client->getPublic('/api/transport/lokasi', $query);

        return $this->forwardClientResult($res);
    }

    /**
     * GET /api/v1/transport/tarif?id_asal=...&id_tujuan=...&asal=...&tujuan=...&jam=HH:MM
     */
    public function tarif(): ResponseInterface
    {
        $idAsal   = trim((string) ($this->request->getGet('id_asal') ?? ''));
        $idTujuan = trim((string) ($this->request->getGet('id_tujuan') ?? ''));
        $asal     = trim((string) ($this->request->getGet('asal') ?? ''));
        $tujuan   = trim((string) ($this->request->getGet('tujuan') ?? ''));
        $jam      = trim((string) ($this->request->getGet('jam') ?? ''));

        if ($idAsal === '' && $asal === '') {
            return $this->respondError('Titik asal (id_asal atau asal) wajib diisi.', 422, [
                'asal' => 'Pilih atau ketik lokasi asal keberangkatan.',
            ]);
        }

        if ($idTujuan === '' && $tujuan === '') {
            return $this->respondError('Titik tujuan (id_tujuan atau tujuan) wajib diisi.', 422, [
                'tujuan' => 'Pilih atau ketik lokasi tujuan perjalanan.',
            ]);
        }

        if ($idAsal !== '' && ! ctype_digit($idAsal)) {
            return $this->respondError('Parameter id_asal harus berupa angka ID lokasi.', 422, [
                'id_asal' => 'id_asal tidak valid.',
            ]);
        }

        if ($idTujuan !== '' && ! ctype_digit($idTujuan)) {
            return $this->respondError('Parameter id_tujuan harus berupa angka ID lokasi.', 422, [
                'id_tujuan' => 'id_tujuan tidak valid.',
            ]);
        }

        if ($jam !== '' && ! preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $jam)) {
            return $this->respondError('Format jam keberangkatan harus HH:MM (contoh: 08:30).', 422, [
                'jam' => 'Format jam tidak valid (gunakan HH:MM).',
            ]);
        }

        $query = [];
        if ($idAsal !== '') {
            $query['id_asal'] = $idAsal;
        }
        if ($idTujuan !== '') {
            $query['id_tujuan'] = $idTujuan;
        }
        if ($asal !== '') {
            $query['asal'] = $asal;
        }
        if ($tujuan !== '') {
            $query['tujuan'] = $tujuan;
        }
        if ($jam !== '') {
            $query['jam'] = $jam;
        }

        $client = new TransportApiClient();
        $res = $client->getPublic('/api/transport/tarif', $query);

        return $this->forwardClientResult($res);
    }

    /**
     * GET / POST /api/v1/transport/rekomendasi-wisata
     * Parameter: user_lat, user_lng, wisata_lat, wisata_lng, nama_wisata, trip_type (roundtrip|oneway)
     */
    public function rekomendasiWisata(): ResponseInterface
    {
        $isPost = strtoupper($this->request->getMethod()) === 'POST';
        $payload = $isPost ? $this->getJsonPayload() : [];
        $queryGet = $this->request->getGet() ?? [];
        $input = array_merge($queryGet, is_array($payload) ? $payload : []);

        $rules = [
            'user_lat'    => 'required|numeric|greater_than_equal_to[-90]|less_than_equal_to[90]',
            'user_lng'    => 'required|numeric|greater_than_equal_to[-180]|less_than_equal_to[180]',
            'wisata_lat'  => 'required|numeric|greater_than_equal_to[-90]|less_than_equal_to[90]',
            'wisata_lng'  => 'required|numeric|greater_than_equal_to[-180]|less_than_equal_to[180]',
            'nama_wisata' => 'permit_empty|max_length[150]',
            'trip_type'   => 'permit_empty|in_list[roundtrip,oneway]',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->respondError('Parameter koordinat atau jenis perjalanan rekomendasi wisata tidak valid.', 422, $this->getValidationErrors());
        }

        $tripType = strtolower(trim((string) ($input['trip_type'] ?? 'roundtrip')));
        if ($tripType === '') {
            $tripType = 'roundtrip';
        }

        $params = [
            'user_lat'    => (float) $input['user_lat'],
            'user_lng'    => (float) $input['user_lng'],
            'wisata_lat'  => (float) $input['wisata_lat'],
            'wisata_lng'  => (float) $input['wisata_lng'],
            'nama_wisata' => trim((string) ($input['nama_wisata'] ?? 'Destinasi Wisata Sumatera Utara')),
            'trip_type'   => $tripType,
        ];

        $client = new TransportApiClient();
        $res = $client->requestRecommendation($isPost ? 'POST' : 'GET', $params);

        return $this->forwardClientResult($res);
    }

    /**
     * GET /api/v1/transport/master/{resource}
     * Referensi baca master data transportasi eksternal (moda|lokasi|penyedia|jadwal) via Public Key.
     */
    public function masterIndex(string $resource): ResponseInterface
    {
        $cleanRes = $this->validateAdminResource($resource);
        if ($cleanRes === null) {
            return $this->respondError("Resource master transportasi '{$resource}' tidak dikenali.", 404);
        }

        $client = new TransportApiClient();
        $res = $client->getPublic("/api/{$cleanRes}", $this->request->getGet() ?? []);

        return $this->forwardClientResult($res);
    }

    /**
     * GET /api/v1/transport/admin/{resource} (Admin Only)
     */
    public function adminIndex(string $resource): ResponseInterface
    {
        $cleanRes = $this->validateAdminResource($resource);
        if ($cleanRes === null) {
            return $this->respondError("Resource master transportasi '{$resource}' tidak dikenali.", 404);
        }

        $client = new TransportApiClient();
        $res = $client->requestAdmin('GET', "/api/{$cleanRes}", $this->request->getGet() ?? []);

        return $this->forwardClientResult($res);
    }

    /**
     * GET /api/v1/transport/admin/{resource}/{id} (Admin Only)
     */
    public function adminShow(string $resource, int $id): ResponseInterface
    {
        $cleanRes = $this->validateAdminResource($resource);
        if ($cleanRes === null) {
            return $this->respondError("Resource master transportasi '{$resource}' tidak dikenali.", 404);
        }

        $client = new TransportApiClient();
        $res = $client->requestAdmin('GET', "/api/{$cleanRes}/{$id}");

        return $this->forwardClientResult($res);
    }

    /**
     * POST /api/v1/transport/admin/{resource} (Admin Only + Secret Key)
     */
    public function adminCreate(string $resource): ResponseInterface
    {
        $cleanRes = $this->validateAdminResource($resource);
        if ($cleanRes === null) {
            return $this->respondError("Resource master transportasi '{$resource}' tidak dikenali.", 404);
        }

        $payload = $this->getJsonPayload();
        if (empty($payload)) {
            return $this->respondError('Payload JSON wajib diisi untuk menambahkan data transportasi.', 422);
        }

        $client = new TransportApiClient();
        $res = $client->requestAdmin('POST', "/api/{$cleanRes}", [], $payload);

        return $this->forwardClientResult($res);
    }

    /**
     * PUT|PATCH /api/v1/transport/admin/{resource}/{id} (Admin Only + Secret Key)
     */
    public function adminUpdate(string $resource, int $id): ResponseInterface
    {
        $cleanRes = $this->validateAdminResource($resource);
        if ($cleanRes === null) {
            return $this->respondError("Resource master transportasi '{$resource}' tidak dikenali.", 404);
        }

        $payload = $this->getJsonPayload();
        if (empty($payload)) {
            return $this->respondError('Payload JSON wajib diisi untuk memperbarui data transportasi.', 422);
        }

        $method = strtoupper($this->request->getMethod()) === 'PATCH' ? 'PATCH' : 'PUT';
        $client = new TransportApiClient();
        $res = $client->requestAdmin($method, "/api/{$cleanRes}/{$id}", [], $payload);

        return $this->forwardClientResult($res);
    }

    /**
     * DELETE /api/v1/transport/admin/{resource}/{id} (Admin Only + Secret Key)
     */
    public function adminDelete(string $resource, int $id): ResponseInterface
    {
        $cleanRes = $this->validateAdminResource($resource);
        if ($cleanRes === null) {
            return $this->respondError("Resource master transportasi '{$resource}' tidak dikenali.", 404);
        }

        $client = new TransportApiClient();
        $res = $client->requestAdmin('DELETE', "/api/{$cleanRes}/{$id}");

        return $this->forwardClientResult($res);
    }

    private function validateAdminResource(string $resource): ?string
    {
        $norm = strtolower(trim($resource));
        return in_array($norm, self::ADMIN_MASTER_RESOURCES, true) ? $norm : null;
    }

    private function forwardClientResult(array $res): ResponseInterface
    {
        if (! empty($res['ok'])) {
            return $this->respondSuccess(
                $res['data'] ?? null,
                (string) ($res['message'] ?? 'Operasi berhasil.'),
                (int) ($res['statusCode'] ?? 200),
                $res['meta'] ?? null
            );
        }

        return $this->respondError(
            (string) ($res['message'] ?? 'Layanan Transport API gagal merespons.'),
            (int) ($res['statusCode'] ?? 502),
            $res['errors'] ?? null
        );
    }
}
