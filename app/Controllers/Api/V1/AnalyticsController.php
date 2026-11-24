<?php

namespace App\Controllers\Api\V1;

use App\Libraries\PgErrorMapper;
use App\Models\AuditLogModel;
use App\Models\TourGuideModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class AnalyticsController extends BaseApiController
{
    /**
     * GET /api/v1/audit-logs
     */
    public function auditLogs(): ResponseInterface
    {
        $model = new AuditLogModel();

        $page = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);

        $filters = [
            'entity_type' => $this->request->getGet('entity_type'),
            'action'      => $this->request->getGet('action'),
            'entity_id'   => $this->request->getGet('entity_id'),
        ];

        try {
            $result = $model->getLogs($filters, $page, $perPage);
            return $this->respondSuccess($result['items'], 'Daftar log audit berhasil dimuat.', 200, $result['meta']);
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * GET /api/v1/stats
     */
    public function stats(): ResponseInterface
    {
        $model = new TourGuideModel();
        try {
            $stats = $model->getSummaryStats();
            return $this->respondSuccess($stats, 'Statistik ringkasan platform berhasil dimuat.');
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }
}
