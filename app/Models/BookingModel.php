<?php

namespace App\Models;

use CodeIgniter\Model;

class BookingModel extends Model
{
    protected $table            = 'bookings';
    protected $primaryKey       = 'booking_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    /**
     * Retrieve paginated bookings from vw_bookings_detail
     */
    public function getBookings(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $builder = $this->db->table('vw_bookings_detail');

        if (! empty($filters['status']) && $filters['status'] !== 'ALL') {
            $builder->where('status', trim($filters['status']));
        }
        if (! empty($filters['tourist_id'])) {
            $builder->where('tourist_id', (int) $filters['tourist_id']);
        }
        if (! empty($filters['guide_id'])) {
            $builder->where('guide_id', (int) $filters['guide_id']);
        }
        if (! empty($filters['destination_id'])) {
            $builder->where('destination_id', (int) $filters['destination_id']);
        }

        $totalBuilder = clone $builder;
        $total = $totalBuilder->countAllResults(false);

        $items = $builder->orderBy('booking_id', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $items = array_map([$this, 'formatBookingRow'], $items);

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

    /**
     * Retrieve single booking detail
     */
    public function getBookingDetail(int $bookingId): ?array
    {
        $row = $this->db->table('vw_bookings_detail')
            ->where('booking_id', $bookingId)
            ->get()
            ->getRowArray();

        return is_array($row) ? $this->formatBookingRow($row) : null;
    }

    /**
     * Normalize PostgreSQL types on booking detail view rows
     */
    private function formatBookingRow(array $row): array
    {
        foreach (['booking_id', 'tourist_id', 'guide_id', 'destination_id', 'transport_id', 'duration_days', 'daily_rate_idr', 'total_price_idr', 'number_of_guests', 'version', 'contact_count'] as $intKey) {
            if (array_key_exists($intKey, $row) && $row[$intKey] !== null) {
                $row[$intKey] = (int) $row[$intKey];
            }
        }
        if (array_key_exists('tourist_verified', $row)) {
            $row['tourist_verified'] = UserModel::toPgBool($row['tourist_verified']);
        }
        return $row;
    }

    /**
     * Find guide_id owned by a user account (role = guide)
     */
    public function getGuideIdByUserId(int $userId): ?int
    {
        $row = $this->db->table('guides')
            ->select('guide_id')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        return $row ? (int) $row['guide_id'] : null;
    }

    /**
     * Create booking using ACID Stored Function fn_create_booking
     */
    public function createBooking(array $data): array
    {
        $touristId    = (int) ($data['tourist_id'] ?? 0);
        $guideId      = (int) ($data['guide_id'] ?? 0);
        $startDate    = (string) ($data['start_date'] ?? '');
        $endDate      = (string) ($data['end_date'] ?? '');
        $guests       = (int) ($data['number_of_guests'] ?? 1);
        $destId       = ! empty($data['destination_id']) ? (int) $data['destination_id'] : null;
        $transId      = ! empty($data['transport_id']) ? (int) $data['transport_id'] : null;
        $meetingPoint = isset($data['meeting_point']) && trim((string) $data['meeting_point']) !== ''
            ? trim((string) $data['meeting_point'])
            : null;
        $notes        = isset($data['notes']) && trim((string) $data['notes']) !== ''
            ? trim((string) $data['notes'])
            : null;

        $sql = 'SELECT * FROM fn_create_booking(?, ?, ?::DATE, ?::DATE, ?::SMALLINT, ?, ?, ?, ?)';
        $query = $this->db->query($sql, [
            $touristId,
            $guideId,
            $startDate,
            $endDate,
            $guests,
            $destId,
            $transId,
            $meetingPoint,
            $notes,
        ]);

        $created = $query->getRowArray();
        $bookingId = (int) ($created['booking_id'] ?? 0);

        return $this->getBookingDetail($bookingId) ?? $created;
    }

    /**
     * Update booking status with Optimistic Locking using fn_update_booking_status
     */
    public function updateBookingStatus(
        int $bookingId,
        string $newStatus,
        int $expectedVersion,
        ?int $changedBy = null,
        ?string $cancelReason = null
    ): array {
        $reason = ($cancelReason !== null && trim($cancelReason) !== '') ? trim($cancelReason) : null;
        $sql = 'SELECT * FROM fn_update_booking_status(?, ?, ?, ?, ?)';
        $query = $this->db->query($sql, [$bookingId, trim($newStatus), $expectedVersion, $changedBy ?? 1, $reason]);
        $updated = $query->getRowArray();

        return $this->getBookingDetail($bookingId) ?? $updated;
    }
}
