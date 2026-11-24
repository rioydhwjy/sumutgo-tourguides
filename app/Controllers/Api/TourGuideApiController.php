<?php

namespace App\Controllers\Api;

use App\Models\TourGuideModel;
use CodeIgniter\RESTful\ResourceController;

class TourGuideApiController extends ResourceController
{
    protected $format = 'json';

    /**
     * GET /api/tour-guides
     * Mendukung query string: ?search=...&spec=...&status=...&lang=...&max_price=...&sort=...
     */
    public function index()
    {
        $model = new TourGuideModel();
        $filters = [
            'search'    => $this->request->getGet('search') ?? $this->request->getGet('q'),
            'spec'      => $this->request->getGet('spec'),
            'status'    => $this->request->getGet('status'),
            'lang'      => $this->request->getGet('lang'),
            'max_price' => $this->request->getGet('max_price'),
            'sort'      => $this->request->getGet('sort'),
        ];

        $res = $model->getFilteredGuides($filters, 1, 150);

        return $this->respond([
            'status' => 200,
            'count'  => count($res['items']),
            'data'   => $res['items'],
        ]);
    }

    /**
     * GET /api/tour-guides/stats
     */
    public function stats()
    {
        $model = new TourGuideModel();
        return $this->respond([
            'status' => 200,
            'data'   => $model->getSummaryStats(),
        ]);
    }

    /**
     * GET /api/tour-guides/{guide_id}
     * Contoh: GET /api/tour-guides/TG-001
     */
    public function show($id = null)
    {
        if (! $id) {
            return $this->failValidationErrors('Parameter guide_id wajib diisi.');
        }

        $model = new TourGuideModel();
        $guide = $model->getGuideDetail((string) $id);

        if (! $guide) {
            return $this->failNotFound("Pemandu wisata dengan ID {$id} tidak ditemukan.");
        }

        return $this->respond([
            'status' => 200,
            'data'   => $guide,
        ]);
    }

    /**
     * POST /api/tour-guides/{guide_id}/book
     * Mengubah status_ketersediaan di PostgreSQL menjadi 'Booked' dan menghitung estimasi biaya
     */
    public function book($id = null)
    {
        $model = new TourGuideModel();
        $guide = $model->getGuideDetail((string) $id);

        if (! $guide) {
            return $this->failNotFound("Pemandu wisata dengan ID {$id} tidak ditemukan.");
        }

        $payload  = $this->request->getJSON(true) ?? $this->request->getPost();
        $traveler = trim((string) ($payload['traveler_name'] ?? 'Wisatawan'));
        $tourDate = trim((string) ($payload['tour_date'] ?? date('Y-m-d')));
        $days     = max(1, (int) ($payload['duration_days'] ?? 1));

        $model->update($guide['id'], [
            'status_ketersediaan' => 'Booked',
        ]);

        $updated = $model->getGuideDetail($guide['id']);

        return $this->respond([
            'status'  => 200,
            'message' => "Booking berhasil dicatat untuk {$traveler} bersama {$guide['name']} ({$guide['id']}).",
            'booking' => [
                'guide_id'       => $guide['id'],
                'nama_guide'     => $guide['name'],
                'traveler_name'  => $traveler,
                'tour_date'      => $tourDate,
                'duration_days'  => $days,
                'tarif_per_hari' => $guide['price'],
                'total_biaya'    => $guide['price'] * $days,
                'status_baru'    => 'Booked',
            ],
            'data'    => $updated,
        ]);
    }

    /**
     * PUT /api/tour-guides/{guide_id}/status
     * Mengubah status ketersediaan ('Tersedia', 'Sedang Bertugas', 'Booked')
     */
    public function updateStatus($id = null)
    {
        $model = new TourGuideModel();
        $guide = $model->getGuideDetail((string) $id);

        if (! $guide) {
            return $this->failNotFound("Pemandu wisata dengan ID {$id} tidak ditemukan.");
        }

        $payload   = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $newStatus = trim((string) ($payload['status_ketersediaan'] ?? ''));

        $allowed = ['Tersedia', 'Sedang Bertugas', 'Booked'];
        if (! in_array($newStatus, $allowed, true)) {
            return $this->failValidationErrors('Status harus salah satu dari: Tersedia, Sedang Bertugas, atau Booked.');
        }

        $model->update($guide['id'], [
            'status_ketersediaan' => $newStatus,
        ]);

        return $this->respond([
            'status'  => 200,
            'message' => "Status {$guide['id']} berhasil diperbarui menjadi {$newStatus}.",
            'data'    => $model->getGuideDetail($guide['id']),
        ]);
    }
}