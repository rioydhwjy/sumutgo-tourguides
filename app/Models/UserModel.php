<?php

namespace App\Models;

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
        'password_hash',
        'role',
        'is_active',
        'last_login_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

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

        if (! (bool) $user['is_active']) {
            return null;
        }

        if (! password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $user;
    }

    /**
     * Register a new user
     */
    public function createUser(array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $hash  = password_hash((string) ($data['password'] ?? ''), PASSWORD_BCRYPT);
        $role  = strtolower(trim((string) ($data['role'] ?? 'wisatawan')));

        if (! in_array($role, ['admin', 'wisatawan', 'guide'], true)) {
            $role = 'wisatawan';
        }

        $row = [
            'full_name'     => trim((string) ($data['full_name'] ?? '')),
            'email'         => $email,
            'password_hash' => $hash,
            'role'          => $role,
            'is_active'     => true,
        ];

        $insertId = $this->insert($row, true);
        return $this->find($insertId);
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
}
