<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Throwable;

class DbTransactionRunner
{
    /**
     * Run a callback inside an ACID transaction with PostgreSQL role & session context
     *
     * @param string $dbRole 'role_sumutgo_admin' | 'role_sumutgo_mobile'
     * @param int|null $userId User ID for audit logs context
     * @param callable $callback function(BaseConnection $db): mixed
     * @return mixed
     * @throws Throwable
     */
    public static function runAsRole(string $dbRole, ?int $userId, callable $callback)
    {
        $db = Database::connect();

        // 1. Begin transaction
        $db->query('BEGIN');

        try {
            // 2. Set transaction-local role
            $roleName = ($dbRole === 'role_sumutgo_admin') ? 'role_sumutgo_admin' : 'role_sumutgo_mobile';
            $db->query("SET LOCAL ROLE {$roleName}");

            // 3. Set transaction-local current_user_id if available
            if ($userId !== null && $userId > 0) {
                $db->query("SELECT set_config('app.current_user_id', ?, true)", [(string) $userId]);
            }

            // 4. Execute callback
            $result = $callback($db);

            // 5. Commit transaction
            $db->query('COMMIT');

            return $result;
        } catch (Throwable $e) {
            // Rollback on any failure
            try {
                $db->query('ROLLBACK');
            } catch (Throwable $ignored) {
                // Ignore rollback failures if transaction already aborted
            }
            throw $e;
        }
    }
}
