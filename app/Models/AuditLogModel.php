<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table            = 'audit_logs';
    protected $primaryKey       = 'audit_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    /**
     * Retrieve paginated audit logs with optional filters
     */
    public function getLogs(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $builder = $this->db->table('audit_logs al')
            ->select('al.*, u.full_name as user_name, u.email as user_email, u.role as user_role')
            ->join('users u', 'u.user_id = al.user_id', 'left');

        if (! empty($filters['entity_type'])) {
            $builder->where('al.entity_type', trim($filters['entity_type']));
        }
        if (! empty($filters['action'])) {
            $builder->where('al.action', strtoupper(trim($filters['action'])));
        }
        if (! empty($filters['entity_id'])) {
            $builder->where('al.entity_id', (int) $filters['entity_id']);
        }

        $totalBuilder = clone $builder;
        $total = $totalBuilder->countAllResults(false);

        $rows = $builder->orderBy('al.audit_id', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        // Decode JSON fields for clean consumption
        $items = array_map(static function (array $r): array {
            if (isset($r['old_values']) && is_string($r['old_values'])) {
                $r['old_values'] = json_decode($r['old_values'], true);
            }
            if (isset($r['new_values']) && is_string($r['new_values'])) {
                $r['new_values'] = json_decode($r['new_values'], true);
            }
            return $r;
        }, $rows);

        return [
            'items' => $items,
            'meta'  => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total_items'  => $total,
                'total_pages'  => (int) ceil($total / $perPage),
            ],
        ];
    }
}
