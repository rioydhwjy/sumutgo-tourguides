<?php

namespace App\Models;

use App\Libraries\PhoneNormalizer;
use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'user_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'full_name',
        'email',
        'phone_number',
        'password_hash',
        'role',
        'is_active',
        'is_verified',
        'verified_at',
        'verified_by',
        'verification_note',
        'last_login_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Convert PostgreSQL boolean literal ('t'/'f'/bool/int) into strict PHP bool
     */
    public static function toPgBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $norm = strtolower(trim((string) $value));
        return in_array($norm, ['1', 't', 'true', 'y', 'yes', 'on'], true);
    }

    /**
     * Strip secret columns and cast scalar/boolean fields for safe JSON responses
     */
    public function toPublicUser(array $row): array
    {
        unset($row['password_hash']);
        if (isset($row['user_id'])) {
            $row['user_id'] = (int) $row['user_id'];
        }
        $row['is_active']   = self::toPgBool($row['is_active'] ?? true);
        $row['is_verified'] = self::toPgBool($row['is_verified'] ?? false);
        return $row;
    }

    /**
     * Find user by ID returning strict public fields (no password_hash, strict bools)
     */
    public function findPublicById(int $userId): ?array
    {
        $row = $this->find($userId);
        return is_array($row) ? $this->toPublicUser($row) : null;
    }

    /**
     * Find user by normalized lowercase email
     */
    public function findByEmail(string $email): ?array
    {
        $normalized = strtolower(trim($email));
        return $this->where('email', $normalized)->first();
    }

    /**
     * Authenticate email and password
     */
    public function verifyCredentials(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);
        if (! $user) {
            return null;
        }

        if (! self::toPgBool($user['is_active'] ?? false)) {
            return null;
        }

        if (! password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            return null;
        }

        return $this->toPublicUser($user);
    }

    /**
     * Register a new user
     */
    public function createUser(array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $hash  = password_hash((string) ($data['password'] ?? ''), PASSWORD_BCRYPT);
        $role  = strtolower(trim((string) ($data['role'] ?? 'wisatawan')));
        $phone = isset($data['phone_number']) ? PhoneNormalizer::normalize((string) $data['phone_number']) : '';

        if (! in_array($role, ['admin', 'wisatawan', 'guide'], true)) {
            $role = 'wisatawan';
        }

        $row = [
            'full_name'     => trim((string) ($data['full_name'] ?? '')),
            'email'         => $email,
            'phone_number'  => $phone !== '' ? $phone : null,
            'password_hash' => $hash,
            'role'          => $role,
            'is_active'     => true,
            'is_verified'   => false,
        ];

        $insertId = (int) $this->insert($row, true);
        $created = $this->findPublicById($insertId);

        return $created ?? [];
    }

    /**
     * Update last_login_at timestamp
     */
    public function updateLastLogin(int $userId): void
    {
        $this->update($userId, [
            'last_login_at' => gmdate('Y-m-d H:i:sP'),
        ]);
    }

    /**
     * List users safely (never returning password_hash) for Admin verification panel
     */
    public function getUsersForAdmin(array $filters = [], int $page = 1, int $perPage = 30): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;

        $builder = $this->db->table('users u')
            ->select(
                'u.user_id, u.full_name, u.email, u.phone_number, u.role, u.is_active,
                 u.is_verified, u.verified_at, u.verified_by, u.verification_note,
                 u.last_login_at, u.created_at, u.updated_at,
                 v.full_name AS verified_by_name,
                 (SELECT COUNT(*) FROM bookings b WHERE b.tourist_id = u.user_id)::int AS booking_count'
            )
            ->join('users v', 'v.user_id = u.verified_by', 'left');

        if (! empty($filters['role']) && $filters['role'] !== 'ALL') {
            $builder->where('u.role', strtolower(trim((string) $filters['role'])));
        }

        if (isset($filters['is_verified']) && $filters['is_verified'] !== '' && $filters['is_verified'] !== 'ALL') {
            $isVer = filter_var($filters['is_verified'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isVer !== null) {
                $builder->where('u.is_verified', $isVer);
            }
        }

        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $builder->groupStart()
                ->like('u.full_name', $q, 'both', null, true)
                ->orLike('u.email', $q, 'both', null, true)
                ->orLike('u.phone_number', $q, 'both', null, true)
                ->groupEnd();
        }

        $total = (clone $builder)->countAllResults(false);
        $items = $builder->orderBy('u.is_verified', 'ASC')
            ->orderBy('u.user_id', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $items = array_map(static function (array $row): array {
            $row['user_id']       = (int) $row['user_id'];
            $row['is_active']     = self::toPgBool($row['is_active'] ?? true);
            $row['is_verified']   = self::toPgBool($row['is_verified'] ?? false);
            $row['booking_count'] = (int) ($row['booking_count'] ?? 0);
            return $row;
        }, $items);

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
     * Confirm / verify or unverify a user account by Admin
     */
    public function confirmVerification(int $targetUserId, int $adminUserId, bool $isVerified = true, ?string $note = null): ?array
    {
        $exists = $this->find($targetUserId);
        if (! $exists) {
            return null;
        }

        $cleanNote = ($note !== null && trim($note) !== '') ? trim($note) : null;
        $sql = 'UPDATE users
                SET is_verified = ?,
                    verified_at = CASE WHEN ? THEN CURRENT_TIMESTAMP ELSE NULL END,
                    verified_by = CASE WHEN ? THEN ?::INT ELSE NULL END,
                    verification_note = ?
                WHERE user_id = ?';

        $this->db->query($sql, [
            $isVerified,
            $isVerified,
            $isVerified,
            $adminUserId,
            $cleanNote,
            $targetUserId,
        ]);

        $row = $this->db->table('users u')
            ->select(
                'u.user_id, u.full_name, u.email, u.phone_number, u.role, u.is_active,
                 u.is_verified, u.verified_at, u.verified_by, u.verification_note,
                 u.last_login_at, u.created_at, u.updated_at,
                 v.full_name AS verified_by_name'
            )
            ->join('users v', 'v.user_id = u.verified_by', 'left')
            ->where('u.user_id', $targetUserId)
            ->get()
            ->getRowArray();

        if (! $row) {
            return null;
        }

        $row['user_id']     = (int) $row['user_id'];
        $row['is_active']   = self::toPgBool($row['is_active'] ?? true);
        $row['is_verified'] = self::toPgBool($row['is_verified'] ?? false);

        return $row;
    }
}
