<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

/**
 * CLI Command: php spark db:apply-k2
 * Menerapkan objek database Kriteria K2 (Views, Functions, Triggers, Roles, RLS)
 * ke database Supabase PostgreSQL 16/17 dari database/sql/03_db_objects_k2.sql.
 */
class ApplyK2Objects extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:apply-k2';
    protected $description = 'Menerapkan objek K2 (Views, Functions, Triggers, Roles, RLS) ke Supabase dari 03_db_objects_k2.sql';
    protected $usage       = 'db:apply-k2';

    public function run(array $params): void
    {
        CLI::write('====================================================================', 'cyan');
        CLI::write('  SUMUT GO - APPLY DATABASE SERVER OBJECTS (KRITERIA K2)           ', 'yellow');
        CLI::write('====================================================================', 'cyan');

        $sqlFile = realpath(APPPATH . '../database/sql/03_db_objects_k2.sql');
        if (! $sqlFile || ! file_exists($sqlFile)) {
            CLI::error("[ERROR] File SQL tidak ditemukan: database/sql/03_db_objects_k2.sql");
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

        CLI::write("Mengeksekusi DDL objek database K2 ke Supabase...", 'cyan');
        $startTime = microtime(true);

        $conn = $db->connID;
        if (! $conn) {
            CLI::error("[ERROR] Koneksi native pgsql tidak tersedia.");
            return;
        }

        $res = @pg_query($conn, $rawSql);

        if ($res === false) {
            $err = pg_last_error($conn);
            CLI::error("[GAGAL APLIKASI K2] Terjadi error saat mengeksekusi 03_db_objects_k2.sql:");
            CLI::write($err, 'red');
            return;
        }

        $duration = round(microtime(true) - $startTime, 3);
        CLI::write("[SUKSES] Objek K2 berhasil diterapkan dalam {$duration} detik!", 'green');
        CLI::newLine();

        // Verifikasi Objek
        CLI::write('--- VERIFIKASI OBJEK DATABASE YANG TERPASANG ---', 'yellow');

        // 1. Views
        $views = ['vw_guides_detail', 'vw_bookings_detail'];
        $viewRows = [];
        foreach ($views as $v) {
            $check = $db->query("SELECT table_name FROM information_schema.views WHERE table_schema = 'public' AND table_name = '{$v}'")->getRowArray();
            $viewRows[] = ['View', $v, ! empty($check) ? 'ACTIVE' : 'MISSING'];
        }

        // 2. Functions
        $functions = ['fn_create_booking', 'fn_update_booking_status'];
        foreach ($functions as $fn) {
            $check = $db->query("SELECT routine_name FROM information_schema.routines WHERE routine_schema = 'public' AND routine_name = '{$fn}'")->getRowArray();
            $viewRows[] = ['Function', $fn, ! empty($check) ? 'ACTIVE' : 'MISSING'];
        }

        // 3. Triggers
        $triggers = [
            ['bookings', 'trg_bookings_audit_log'],
            ['guides', 'trg_guides_audit_log'],
            ['reviews', 'trg_reviews_only_completed'],
        ];
        foreach ($triggers as $trg) {
            $check = $db->query("SELECT trigger_name FROM information_schema.triggers WHERE event_object_table = '{$trg[0]}' AND trigger_name = '{$trg[1]}'")->getRowArray();
            $viewRows[] = ['Trigger (' . $trg[0] . ')', $trg[1], ! empty($check) ? 'ACTIVE' : 'MISSING'];
        }

        // 4. Sequence
        $checkSeq = $db->query("SELECT sequence_name FROM information_schema.sequences WHERE sequence_schema = 'public' AND sequence_name = 'seq_booking_code'")->getRowArray();
        $viewRows[] = ['Sequence', 'seq_booking_code', ! empty($checkSeq) ? 'ACTIVE' : 'MISSING'];

        // 5. Roles
        $roles = ['role_sumutgo_admin', 'role_sumutgo_mobile'];
        foreach ($roles as $r) {
            $checkRole = $db->query("SELECT rolname FROM pg_roles WHERE rolname = '{$r}'")->getRowArray();
            $viewRows[] = ['Role Database', $r, ! empty($checkRole) ? 'ACTIVE' : 'MISSING'];
        }

        // 6. RLS Policies
        $polCount = $db->query("SELECT COUNT(*) AS total FROM pg_policy WHERE polname LIKE 'rls_%'")->getRowArray();
        $viewRows[] = ['RLS Policies', 'rls_* (Admin & Mobile)', ($polCount['total'] ?? '0') . ' Policies Active'];

        CLI::table($viewRows, ['Tipe Objek', 'Nama Objek', 'Status']);

        CLI::write('====================================================================', 'cyan');
        CLI::write('Seluruh objek database server Kriteria K2 siap digunakan.', 'green');
        CLI::write('====================================================================', 'cyan');
    }
}
