<?php

namespace App\Models;

use CodeIgniter\Model;
use InvalidArgumentException;

class MasterDataModel extends Model
{
    /**
     * Whitelist configuration for master tables
     */
    private const MASTER_MAP = [
        'specializations' => [
            'table'      => 'tour_specializations',
            'pk'         => 'specialization_id',
            'order'      => 'specialization_id ASC',
            'code_col'   => null,
            'name_col'   => 'specialization_name',
            'fields'     => ['specialization_name', 'description'],
        ],
        'languages' => [
            'table'      => 'languages',
            'pk'         => 'language_id',
            'order'      => 'language_id ASC',
            'code_col'   => null,
            'name_col'   => 'language_name',
            'fields'     => ['language_name'],
        ],
        'destinations' => [
            'table'      => 'destinations',
            'pk'         => 'destination_id',
            'order'      => 'destination_code ASC',
            'code_col'   => 'destination_code',
            'name_col'   => 'destination_name',
            'fields'     => ['destination_code', 'destination_name'],
        ],
        'culinaries' => [
            'table'      => 'culinaries',
            'pk'         => 'culinary_id',
            'order'      => 'culinary_code ASC',
            'code_col'   => 'culinary_code',
            'name_col'   => 'culinary_name',
            'fields'     => ['culinary_code', 'culinary_name'],
        ],
        'hotels' => [
            'table'      => 'hotels',
            'pk'         => 'hotel_id',
            'order'      => 'hotel_code ASC',
            'code_col'   => 'hotel_code',
            'name_col'   => 'hotel_name',
            'fields'     => ['hotel_code', 'hotel_name'],
        ],
        'transports' => [
            'table'      => 'transports',
            'pk'         => 'transport_id',
            'order'      => 'transport_code ASC',
            'code_col'   => 'transport_code',
            'name_col'   => 'transport_name',
            'fields'     => ['transport_code', 'transport_name'],
        ],
    ];

    private function getMeta(string $resource): array
    {
        $res = strtolower(trim($resource));
        if (! isset(self::MASTER_MAP[$res])) {
            throw new InvalidArgumentException("Master resource '{$resource}' tidak valid.");
        }
        return self::MASTER_MAP[$res];
    }

    /**
     * Retrieve all 6 master datasets simultaneously
     */
    public function getAllMaster(): array
    {
        $result = [];
        foreach (array_keys(self::MASTER_MAP) as $res) {
            $result[$res] = $this->getItems($res);
        }
        return $result;
    }

    public function getItems(string $resource): array
    {
        $meta = $this->getMeta($resource);
        return $this->db->table($meta['table'])
            ->orderBy($meta['order'])
            ->get()
            ->getResultArray();
    }

    public function getItem(string $resource, int $id): ?array
    {
        $meta = $this->getMeta($resource);
        return $this->db->table($meta['table'])
            ->where($meta['pk'], $id)
            ->get()
            ->getRowArray();
    }

    public function createItem(string $resource, array $data): array
    {
        $meta = $this->getMeta($resource);
        $insertData = [];
        foreach ($meta['fields'] as $f) {
            if (isset($data[$f])) {
                $insertData[$f] = trim((string) $data[$f]);
            }
        }

        $builder = $this->db->table($meta['table']);
        $builder->insert($insertData);
        $insertId = $this->db->insertID();

        return $this->getItem($resource, (int) $insertId);
    }

    public function updateItem(string $resource, int $id, array $data): ?array
    {
        $meta = $this->getMeta($resource);
        $updateData = [];
        foreach ($meta['fields'] as $f) {
            if (isset($data[$f])) {
                $updateData[$f] = trim((string) $data[$f]);
            }
        }

        if (! empty($updateData)) {
            $this->db->table($meta['table'])
                ->where($meta['pk'], $id)
                ->update($updateData);
        }

        return $this->getItem($resource, $id);
    }

    public function deleteItem(string $resource, int $id): bool
    {
        $meta = $this->getMeta($resource);
        $this->db->table($meta['table'])
            ->where($meta['pk'], $id)
            ->delete();

        return $this->db->affectedRows() > 0;
    }
}
