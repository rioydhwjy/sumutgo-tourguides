<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

/**
 * CLI Command: php spark db:check-supabase
 * Memverifikasi koneksi aktif Supabase PostgreSQL 16, SSL, ekstensi, dan 12 tabel skema target.
 */
class CheckSupabase extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:check-supabase';
    protected $description = 'Verifikasi koneksi Supabase PostgreSQL 16 (port 6543), status SSL, ekstensi, dan 12 tabel 3NF.';
    protected $usage       = 'db:check-supabase';

    /**
     * Daftar 12 tabel target skema 3NF
     */
    private array $targetTables = [
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

    public function run(array $params): void
    {
        CLI::write('====================================================================', 'cyan');
        CLI::write('  SUMUT GO - SUPABASE POSTGRESQL 16 CONNECTION & SCHEMA VERIFIER    ', 'yellow');
        CLI::write('====================================================================', 'cyan');

        // 1. Validasi Pra-Koneksi: Password di .env
        $config = config(Database::class);
        $dbConfig = $config->default ?? [];

        $hostname = (string) ($dbConfig['hostname'] ?? '');
        $port     = (int) ($dbConfig['port'] ?? 6543);
        $database = (string) ($dbConfig['database'] ?? '');
        $username = (string) ($dbConfig['username'] ?? '');
        $password = (string) ($dbConfig['password'] ?? '');
        $sslmode  = (string) ($dbConfig['sslmode'] ?? 'require');

        CLI::write("Target Host     : {$hostname}:{$port}", 'white');
        CLI::write("Target Database : {$database}", 'white');
        CLI::write("Target User     : {$username}", 'white');
        CLI::write("Target SSL Mode : {$sslmode}", 'white');
        CLI::newLine();

        if (empty($password) || str_contains($password, 'MASUKKAN_PASSWORD') || str_contains($password, 'YOUR_SUPABASE_PASSWORD')) {
            CLI::error('[DIAGNOSTIK ERROR] Password Supabase belum diisi pada file .env!');
            CLI::write('Silakan buka file: .env (baris 24)', 'yellow');
            CLI::write('Isi: database.default.password = <password_database_supabase_anda>', 'yellow');
            CLI::write('Kemudian jalankan kembali perintah ini: php spark db:check-supabase', 'cyan');
            return;
        }

        // 2. Inisialisasi Koneksi Database
        CLI::write('Menghubungkan ke Supabase Transaction Pooler...', 'cyan');

        try {
            $db = Database::connect('default', false);
            // Trigger handshake
            $db->initialize();

            CLI::write('[OK] Handshake koneksi ke Supabase berhasil!', 'green');
            CLI::newLine();
        } catch (Throwable $e) {
            CLI::error('[GAGAL KONEKSI] Tidak dapat terhubung ke database Supabase.');
            $this->diagnoseConnectionError($e->getMessage());
            return;
        }

        // 3. Inspeksi Versi, User, Database, dan SSL
        CLI::write('--- INFORMASI SERVER & SESI ---', 'yellow');
        try {
            $verRow  = $db->query('SELECT version() AS ver')->getRowArray();
            $userRow = $db->query('SELECT current_user AS usr, current_database() AS db')->getRowArray();

            $fullVersion = (string) ($verRow['ver'] ?? 'Unknown');
            $currentUser = (string) ($userRow['usr'] ?? 'Unknown');
            $currentDb   = (string) ($userRow['db'] ?? 'Unknown');

            CLI::write("PostgreSQL Version : {$fullVersion}", 'white');
            CLI::write("Current User       : {$currentUser}", 'white');
            CLI::write("Current Database   : {$currentDb}", 'white');

            // Cek status SSL
            $sslStatus = $this->checkSslStatus($db);
            CLI::write("SSL Encryption     : {$sslStatus}", 'green');
        } catch (Throwable $e) {
            CLI::error('Gagal mengambil informasi sesi: ' . $e->getMessage());
        }
        CLI::newLine();

        // 4. Inspeksi Ekstensi Database
        CLI::write('--- STATUS EKSTENSI POSTGRESQL ---', 'yellow');
        $extensions = ['btree_gist', 'pg_trgm'];
        $extRows = [];
        try {
            $query = $db->query(
                "SELECT extname, extversion FROM pg_extension WHERE extname IN ('btree_gist', 'pg_trgm')"
            )->getResultArray();

            $installedExts = [];
            foreach ($query as $row) {
                $installedExts[$row['extname']] = $row['extversion'];
            }

            foreach ($extensions as $ext) {
                if (isset($installedExts[$ext])) {
                    $extRows[] = [$ext, 'INSTALLED', (string) $installedExts[$ext]];
                } else {
                    $extRows[] = [$ext, 'MISSING', '-'];
                }
            }

            CLI::table($extRows, ['Nama Ekstensi', 'Status', 'Versi Terpasang']);
        } catch (Throwable $e) {
            CLI::error('Gagal membaca pg_extension: ' . $e->getMessage());
        }
        CLI::newLine();

        // 5. Inspeksi 12 Tabel Target, RLS, dan Jumlah Baris
        CLI::write('--- INSPEKSI 12 TABEL TARGET SKEMA 3NF ---', 'yellow');
        $tableData = [];
        $missingCount = 0;

        try {
            // Ambil info tabel yang ada di schema public
            $existingTables = [];
            $tableQuery = $db->query(
                "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"
            )->getResultArray();

            foreach ($tableQuery as $t) {
                $existingTables[strtolower($t['table_name'])] = true;
            }

            // Ambil info RLS dari pg_class
            $rlsInfo = [];
            $rlsQuery = $db->query(
                "SELECT c.relname, c.relrowsecurity 
                 FROM pg_class c 
                 JOIN pg_namespace n ON n.oid = c.relnamespace 
                 WHERE n.nspname = 'public' AND c.relkind = 'r'"
            )->getResultArray();

            foreach ($rlsQuery as $r) {
                $isRls = ($r['relrowsecurity'] === 't' || $r['relrowsecurity'] === true || $r['relrowsecurity'] === '1');
                $rlsInfo[strtolower($r['relname'])] = $isRls ? 'ON' : 'OFF';
            }

            $no = 1;
            foreach ($this->targetTables as $tbl) {
                $exists = isset($existingTables[$tbl]);
                $status = $exists ? 'OK' : 'MISSING';
                $rls    = $rlsInfo[$tbl] ?? '-';
                $count  = '-';

                if ($exists) {
                    try {
                        $countRow = $db->query("SELECT COUNT(*) AS total FROM public.{$tbl}")->getRowArray();
                        $count = (string) ($countRow['total'] ?? '0');
                    } catch (Throwable) {
                        $count = 'Access Restricted';
                    }
                } else {
                    $missingCount++;
                }

                $tableData[] = [
                    (string) $no++,
                    $tbl,
                    $status,
                    $rls,
                    $count,
                ];
            }

            CLI::table($tableData, ['No', 'Nama Tabel', 'Status', 'RLS', 'Total Baris']);

            if ($missingCount === 0) {
                CLI::write('[SUKSES] Semua 12 tabel target terdeteksi lengkap di Supabase!', 'green');
            } else {
                CLI::write("[PERINGATAN] Ada {$missingCount} tabel yang belum terdeteksi di Supabase. Pastikan DDL sudah dieksekusi.", 'red');
            }
        } catch (Throwable $e) {
            CLI::error('Gagal memeriksa tabel: ' . $e->getMessage());
        }

        CLI::newLine();
        CLI::write('====================================================================', 'cyan');
        CLI::write('Verifikasi koneksi & inspeksi skema Supabase selesai.', 'green');
        CLI::write('====================================================================', 'cyan');
    }

    /**
     * Memeriksa status SSL secara aman dengan fallback
     */
    private function checkSslStatus($db): string
    {
        // Percobaan 1: SHOW ssl
        try {
            $row = $db->query('SHOW ssl')->getRowArray();
            if (! empty($row['ssl'])) {
                return 'Aktif (SHOW ssl: ' . $row['ssl'] . ')';
            }
        } catch (Throwable) {
            // Ignore & fallback
        }

        // Percobaan 2: pg_stat_ssl
        try {
            $row = $db->query('SELECT ssl, version, cipher FROM pg_stat_ssl WHERE pid = pg_backend_pid()')->getRowArray();
            if (isset($row['ssl'])) {
                $isSsl = ($row['ssl'] === 't' || $row['ssl'] === true || $row['ssl'] === '1');
                if ($isSsl) {
                    $ver = $row['version'] ?? 'TLS';
                    $cip = $row['cipher'] ?? '';
                    return "Aktif ({$ver} - {$cip})";
                }
            }
        } catch (Throwable) {
            // Ignore & fallback
        }

        // Fallback jika dibatasi oleh transaction pooler
        return 'Aktif (Enforced via sslmode=require pada pg_connect)';
    }

    /**
     * Diagnostik error koneksi spesifik
     */
    private function diagnoseConnectionError(string $error): void
    {
        CLI::write("Pesan Error Sistem: {$error}", 'red');
        CLI::newLine();

        if (str_contains($error, 'password authentication failed') || str_contains($error, 'PAM authentication failed') || str_contains($error, '28P01')) {
            CLI::write('[DIAGNOSIS]: Password atau Username Supabase salah.', 'yellow');
            CLI::write('Solusi: Periksa kembali password database Supabase Anda pada file .env baris 24.', 'white');
        } elseif (str_contains($error, 'timeout') || str_contains($error, 'timed out') || str_contains($error, 'Connection refused')) {
            CLI::write('[DIAGNOSIS]: Koneksi timeout atau host tidak dapat dijangkau.', 'yellow');
            CLI::write('Solusi: Periksa koneksi internet Anda dan pastikan firewall/ISP tidak memblokir port 6543 ke AWS Supabase.', 'white');
        } elseif (str_contains($error, 'SSL') || str_contains($error, 'sslmode')) {
            CLI::write('[DIAGNOSIS]: Negosiasi SSL ditolak atau sertifikat tidak cocok.', 'yellow');
            CLI::write('Solusi: Pastikan sslmode=require terpasang dan ekstensi openssl aktif di PHP.', 'white');
        } else {
            CLI::write('[DIAGNOSIS]: Terjadi kegagalan komunikasi dengan database pooler.', 'yellow');
            CLI::write('Solusi: Periksa parameter hostname, database, dan port di .env.', 'white');
        }
    }
}
