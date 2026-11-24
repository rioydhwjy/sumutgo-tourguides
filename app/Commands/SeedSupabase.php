<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

/**
 * CLI Command: php spark db:seed-supabase
 * Mengeksekusi script database/sql/02_seed_data.sql ke Supabase PostgreSQL 16
 * secara transaksional, aman, dan idempoten.
 */
class SeedSupabase extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:seed-supabase';
    protected $description = 'Menjalankan seeding 150 Tour Guide & data demo ke Supabase dari database/sql/02_seed_data.sql';
    protected $usage       = 'db:seed-supabase';

    public function run(array $params): void
    {
        CLI::write('====================================================================', 'cyan');
        CLI::write('  SUMUT GO - SUPABASE POSTGRESQL 16 SEEDER RUNNER                   ', 'yellow');
        CLI::write('====================================================================', 'cyan');

        $sqlFile = realpath(APPPATH . '../database/sql/02_seed_data.sql');
        if (! $sqlFile || ! file_exists($sqlFile)) {
            CLI::error("[ERROR] File seed tidak ditemukan: database/sql/02_seed_data.sql");
            return;
        }

        CLI::write("Membaca file SQL: {$sqlFile}", 'white');
        $rawSql = file_get_contents($sqlFile);
        if ($rawSql === false || trim($rawSql) === '') {
            CLI::error("[ERROR] File SQL kosong atau gagal dibaca.");
            return;
        }

        CLI::write("Menghubungkan ke database Supabase...", 'white');
        try {
            $db = Database::connect('default', false);
            $db->initialize();
            CLI::write("[OK] Koneksi database berhasil.", 'green');
        } catch (Throwable $e) {
            CLI::error("[ERROR] Gagal terhubung ke database: " . $e->getMessage());
            return;
        }

        CLI::write("Memulai eksekusi SQL transaksional...", 'cyan');
        $startTime = microtime(true);

        $conn = $db->connID;
        if (! $conn) {
            CLI::error("[ERROR] Koneksi native pgsql tidak tersedia.");
            return;
        }

        // Jalankan seluruh script SQL secara atomik melalui Simple Query Protocol
        $res = @pg_query($conn, $rawSql);

        if ($res === false) {
            $err = pg_last_error($conn);
            CLI::error("[GAGAL SEEDING] Error saat eksekusi batch SQL: " . trim($err));
            CLI::write("Mencoba eksekusi isolasi per statement untuk diagnosa baris...", 'yellow');

            // Fallback diagnostik per baris
            @pg_query($conn, 'BEGIN;');
            $lines = explode("\n", $rawSql);
            $currentStmt = '';
            foreach ($lines as $idx => $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                    continue;
                }
                $currentStmt .= $line . "\n";
                if (str_ends_with($trimmed, ';')) {
                    $stmtToExec = trim($currentStmt);
                    $currentStmt = '';
                    if (strcasecmp($stmtToExec, 'BEGIN;') === 0 || strcasecmp($stmtToExec, 'COMMIT;') === 0) {
                        continue;
                    }
                    $subRes = @pg_query($conn, $stmtToExec);
                    if ($subRes === false) {
                        $subErr = pg_last_error($conn);
                        @pg_query($conn, 'ROLLBACK;');
                        CLI::error("[LOKASI DETAIL ERROR pada baris " . ($idx + 1) . "]:");
                        CLI::write("Error: " . trim($subErr), 'red');
                        CLI::write("Statement: " . substr($stmtToExec, 0, 200) . "...", 'yellow');
                        return;
                    }
                }
            }
            @pg_query($conn, 'ROLLBACK;');
            return;
        }

        $duration = round(microtime(true) - $startTime, 3);
        CLI::write("[SUKSES] Seeding selesai dieksekusi dalam {$duration} detik!", 'green');
        CLI::newLine();

        // Tampilkan ringkasan data saat ini
        CLI::write('--- RINGKASAN JUMLAH BARIS SETELAH SEEDING ---', 'yellow');
        $tables = [
            'users',
            'tour_specializations',
            'languages',
            'destinations',
            'culinaries',
            'hotels',
            'transports',
            'guides',
            'guide_languages',
            'bookings',
            'reviews',
            'audit_logs',
        ];

        $summary = [];
        $no = 1;
        foreach ($tables as $t) {
            try {
                $row = $db->query("SELECT COUNT(*) AS total FROM public.{$t}")->getRowArray();
                $count = (string) ($row['total'] ?? '0');
            } catch (Throwable) {
                $count = 'Err';
            }
            $summary[] = [(string) $no++, $t, $count];
        }

        CLI::table($summary, ['No', 'Nama Tabel', 'Total Baris']);

        CLI::write('====================================================================', 'cyan');
        CLI::write('Seeder Supabase berhasil dijalankan.', 'green');
        CLI::write('====================================================================', 'cyan');
    }
}
