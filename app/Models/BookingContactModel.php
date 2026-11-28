<?php

namespace App\Models;

use App\Libraries\PhoneNormalizer;
use CodeIgniter\Model;

class BookingContactModel extends Model
{
    protected $table            = 'booking_contacts';
    protected $primaryKey       = 'contact_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'booking_id',
        'tourist_id',
        'guide_id',
        'contact_phone',
        'contact_email',
        'preferred_channel',
        'sender_role',
        'inquiry_message',
        'confirmation_status',
        'confirmed_by',
        'confirmed_at',
        'admin_note',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Retrieve paginated booking contacts joined with booking, tourist, guide, and verifier details
     */
    public function getContacts(array $filters = [], int $page = 1, int $perPage = 30): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;

        $builder = $this->baseJoinedBuilder();

        if (! empty($filters['booking_id'])) {
            $builder->where('bc.booking_id', (int) $filters['booking_id']);
        }
        if (! empty($filters['tourist_id'])) {
            $builder->where('bc.tourist_id', (int) $filters['tourist_id']);
        }
        if (! empty($filters['guide_id'])) {
            $builder->where('bc.guide_id', (int) $filters['guide_id']);
        }
        if (! empty($filters['confirmation_status']) && $filters['confirmation_status'] !== 'ALL') {
            $builder->where('bc.confirmation_status', strtolower(trim((string) $filters['confirmation_status'])));
        }
        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $builder->groupStart()
                ->like('b.booking_code', $q, 'both', null, true)
                ->orLike('tu.full_name', $q, 'both', null, true)
                ->orLike('g.guide_name', $q, 'both', null, true)
                ->orLike('bc.contact_phone', $q, 'both', null, true)
                ->orLike('bc.inquiry_message', $q, 'both', null, true)
                ->groupEnd();
        }

        $total = (clone $builder)->countAllResults(false);
        $items = $builder->orderBy('bc.contact_id', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $items = array_map([$this, 'formatContactRow'], $items);

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
     * Retrieve single booking contact detail
     */
    public function getContactDetail(int $contactId): ?array
    {
        $row = $this->baseJoinedBuilder()
            ->where('bc.contact_id', $contactId)
            ->get()
            ->getRowArray();

        return $row ? $this->formatContactRow($row) : null;
    }

    /**
     * Create a contact/inquiry message between pemesan and guide/pemboking
     */
    public function createContact(array $data): array
    {
        $phone = PhoneNormalizer::normalize((string) ($data['contact_phone'] ?? ''));

        $sql = "INSERT INTO booking_contacts (
                    booking_id, tourist_id, guide_id, contact_phone, contact_email,
                    preferred_channel, sender_role, inquiry_message, confirmation_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unverified')
                RETURNING contact_id";

        $res = $this->db->query($sql, [
            (int) $data['booking_id'],
            (int) $data['tourist_id'],
            (int) $data['guide_id'],
            $phone,
            ! empty($data['contact_email']) ? strtolower(trim((string) $data['contact_email'])) : null,
            strtolower(trim((string) ($data['preferred_channel'] ?? 'whatsapp'))),
            strtolower(trim((string) ($data['sender_role'] ?? 'wisatawan'))),
            trim((string) $data['inquiry_message']),
        ]);

        $row = $res->getRowArray();
        $contactId = (int) ($row['contact_id'] ?? 0);

        return $this->getContactDetail($contactId) ?? $row;
    }

    /**
     * Confirm or update contact inquiry status by Admin
     */
    public function confirmContact(int $contactId, string $status, int $adminUserId, ?string $adminNote = null): ?array
    {
        $existing = $this->find($contactId);
        if (! $existing) {
            return null;
        }

        $cleanStatus = strtolower(trim($status));
        $cleanNote   = ($adminNote !== null && trim($adminNote) !== '') ? trim($adminNote) : null;

        $sql = "UPDATE booking_contacts
                SET confirmation_status = ?,
                    confirmed_by = CASE WHEN ? IN ('contacted', 'confirmed') THEN ?::INT ELSE NULL END,
                    confirmed_at = CASE WHEN ? = 'confirmed' THEN CURRENT_TIMESTAMP ELSE NULL END,
                    admin_note = COALESCE(?, admin_note)
                WHERE contact_id = ?";

        $this->db->query($sql, [
            $cleanStatus,
            $cleanStatus,
            $adminUserId,
            $cleanStatus,
            $cleanNote,
            $contactId,
        ]);

        return $this->getContactDetail($contactId);
    }

    private function baseJoinedBuilder()
    {
        return $this->db->table('booking_contacts bc')
            ->select(
                'bc.*,
                 b.booking_code, b.start_date, b.end_date, b.duration_days, b.status AS booking_status, b.total_price_idr,
                 tu.full_name AS tourist_name, tu.email AS tourist_email, tu.phone_number AS tourist_phone, tu.is_verified AS tourist_verified,
                 g.guide_code, g.guide_name, g.agency_name, g.phone_number AS guide_phone,
                 au.full_name AS confirmed_by_name'
            )
            ->join('bookings b', 'b.booking_id = bc.booking_id', 'inner')
            ->join('users tu', 'tu.user_id = bc.tourist_id', 'inner')
            ->join('guides g', 'g.guide_id = bc.guide_id', 'inner')
            ->join('users au', 'au.user_id = bc.confirmed_by', 'left');
    }

    private function formatContactRow(array $row): array
    {
        $row['contact_id']       = (int) $row['contact_id'];
        $row['booking_id']       = (int) $row['booking_id'];
        $row['tourist_id']       = (int) $row['tourist_id'];
        $row['guide_id']         = (int) $row['guide_id'];
        $row['confirmed_by']     = $row['confirmed_by'] !== null ? (int) $row['confirmed_by'] : null;
        $row['tourist_verified'] = UserModel::toPgBool($row['tourist_verified'] ?? false);
        return $row;
    }
}
