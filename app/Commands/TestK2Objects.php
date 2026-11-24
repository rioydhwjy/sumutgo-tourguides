<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

/**
 * CLI Command: php spark db:test-k2
 * Menjalankan 6 skenario pengujian otomatis terhadap objek database server Kriteria K2
 * di dalam transaksi atomik BEGIN ... ROLLBACK sehingga data demo tetap bersih.
 */
class TestK2Objects extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:test-k2';
    protected $description = 'Menjalankan 6 pengujian otomatis objek K2 (View, SP, Overlap, Locking, Trigger, RBAC) dalam transaksi ROLLBACK';
    protected $usage       = 'db:test-k2';

    public function run(array $params): void
    {
        CLI::write('====================================================================', 'cyan');
        CLI::write('  SUMUT GO - AUTOMATED VERIFICATION OF K2 SERVER OBJECTS            ', 'yellow');
        CLI::write('====================================================================', 'cyan');

        $db = Database::connect('default', false);
        $db->initialize();
        $conn = $db->connID;

        if (! $conn) {
            CLI::error("[ERROR] Koneksi database native tidak tersedia.");
            return;
        }

        // Buka transaksi pengujian (wajib ROLLBACK di akhir)
        pg_query($conn, 'BEGIN;');
        CLI::write("Transaksi pengujian dibuka (Seluruh perubahan akan di-ROLLBACK di akhir).", 'white');
        CLI::newLine();

        $testResults = [];

        try {
            // ---------------------------------------------------------
            // UJI 1: VIEWS (vw_guides_detail & vw_bookings_detail)
            // ---------------------------------------------------------
            CLI::write('[UJI 1] Memeriksa Views: vw_guides_detail & vw_bookings_detail...', 'cyan');
            $resG = pg_query($conn, "SELECT guide_code, guide_name, specialization_name, spoken_languages, languages_json, internal_avg_rating FROM vw_guides_detail LIMIT 1");
            $resB = pg_query($conn, "SELECT booking_code, tourist_name, guide_name, duration_days, total_price_idr FROM vw_bookings_detail LIMIT 1");

            if ($resG && $resB && pg_num_rows($resG) > 0 && pg_num_rows($resB) > 0) {
                $gRow = pg_fetch_assoc($resG);
                $bRow = pg_fetch_assoc($resB);
                CLI::write("  - Guide: {$gRow['guide_code']} ({$gRow['guide_name']}) | Spesialisasi: {$gRow['specialization_name']} | Bahasa: {$gRow['spoken_languages']}", 'white');
                CLI::write("  - Booking: {$bRow['booking_code']} | Wisatawan: {$bRow['tourist_name']} | Durasi: {$bRow['duration_days']} hari | Total: Rp " . number_format((float)$bRow['total_price_idr'], 0, ',', '.'), 'white');
                $testResults[] = ['Uji 1: Views', 'Query vw_guides_detail & vw_bookings_detail berhasil', 'PASS'];
            } else {
                $testResults[] = ['Uji 1: Views', 'Gagal membaca salah satu View', 'FAIL'];
            }

            // ---------------------------------------------------------
            // UJI 2: STORED FUNCTION fn_create_booking & AUTO AUDIT LOG
            // ---------------------------------------------------------
            CLI::write('[UJI 2] Menguji fn_create_booking & Trigger trg_bookings_audit_log...', 'cyan');
            // Ambil tourist_id budi & guide_id TG-001
            $qInfo = pg_query($conn, "SELECT (SELECT user_id FROM users WHERE email = 'budi@wisatawan.id') AS tourist_id, (SELECT guide_id FROM guides WHERE guide_code = 'TG-001') AS guide_id");
            $info = pg_fetch_assoc($qInfo);
            $touristId = $info['tourist_id'];
            $guideId   = $info['guide_id'];

            $resCreate = pg_query($conn, "SELECT * FROM fn_create_booking({$touristId}, {$guideId}, '2027-01-10', '2027-01-12', 2::smallint, NULL, NULL, 'Lobby Hotel Test', 'Test Uji 2')");
            if ($resCreate && pg_num_rows($resCreate) > 0) {
                $createdBooking = pg_fetch_assoc($resCreate);
                $createdBookingId = $createdBooking['booking_id'];
                $duration = $createdBooking['duration_days'];
                $totalPrice = $createdBooking['total_price_idr'];

                // Cek apakah audit log otomatis tercatat
                $resAudit = pg_query($conn, "SELECT audit_id, action, entity_type, new_data FROM audit_logs WHERE entity_type = 'booking' AND entity_id = {$createdBookingId} AND action = 'INSERT'");
                $auditRow = pg_fetch_assoc($resAudit);

                if ($duration == 3 && $totalPrice == 1650000 && ! empty($auditRow)) {
                    CLI::write("  - Booking baru: {$createdBooking['booking_code']} | Durasi: {$duration} hari | Total: Rp {$totalPrice} (Auto Generated)", 'white');
                    CLI::write("  - Audit Log tercatat otomatis: ID {$auditRow['audit_id']} | Action: {$auditRow['action']}", 'white');
                    $testResults[] = ['Uji 2: SP Create & Audit', 'Booking & Auto-Audit Log berhasil tercatat', 'PASS'];
                } else {
                    $testResults[] = ['Uji 2: SP Create & Audit', 'Nilai durasi/harga atau audit log tidak cocok', 'FAIL'];
                }
            } else {
                $testResults[] = ['Uji 2: SP Create & Audit', 'fn_create_booking gagal dieksekusi: ' . pg_last_error($conn), 'FAIL'];
            }

            // ---------------------------------------------------------
            // UJI 3: ANTI DOUBLE-BOOKING & EXCLUSION CONSTRAINT (23P01)
            // ---------------------------------------------------------
            CLI::write('[UJI 3] Menguji Penolakan Double-Booking (Constraint Overlap 23P01)...', 'cyan');
            pg_query($conn, 'SAVEPOINT sp_overlap;');
            // Coba booking guide yang sama (TG-001) pada tanggal tumpang tindih ('2027-01-11' s.d. '2027-01-13')
            $resOverlap = @pg_query($conn, "SELECT * FROM fn_create_booking({$touristId}, {$guideId}, '2027-01-11', '2027-01-13', 2::smallint, NULL, NULL, 'Lobby', 'Bentrok')");
            if ($resOverlap === false) {
                $errOverlap = pg_last_error($conn);
                pg_query($conn, 'ROLLBACK TO SAVEPOINT sp_overlap;');

                if (str_contains($errOverlap, 'ex_bookings_guide_no_overlap') || str_contains($errOverlap, '23P01') || str_contains($errOverlap, 'conflicting key')) {
                    CLI::write("  - Sukses mendeteksi konflik jadwal! Error terdeteksi: ex_bookings_guide_no_overlap", 'white');
                    $testResults[] = ['Uji 3: Anti Double-Booking', 'Double-booking berhasil ditolak oleh constraint 23P01', 'PASS'];
                } else {
                    $testResults[] = ['Uji 3: Anti Double-Booking', 'Error muncul namun bukan constraint overlap: ' . $errOverlap, 'FAIL'];
                }
            } else {
                pg_query($conn, 'ROLLBACK TO SAVEPOINT sp_overlap;');
                $testResults[] = ['Uji 3: Anti Double-Booking', 'Double-booking lolos (Seharusnya ditolak!)', 'FAIL'];
            }

            // ---------------------------------------------------------
            // UJI 4: OPTIMISTIC LOCKING & STATUS TRANSITION
            // ---------------------------------------------------------
            CLI::write('[UJI 4] Menguji Optimistic Locking (fn_update_booking_status)...', 'cyan');
            // Coba update dengan expected_version yang salah (999)
            pg_query($conn, 'SAVEPOINT sp_optimistic;');
            $resOptFail = @pg_query($conn, "SELECT * FROM fn_update_booking_status({$createdBookingId}, 'confirmed', 999, {$touristId})");
            if ($resOptFail === false) {
                $errOpt = pg_last_error($conn);
                pg_query($conn, 'ROLLBACK TO SAVEPOINT sp_optimistic;');

                if (str_contains($errOpt, 'OPTIMISTIC_LOCK_CONFLICT') || str_contains($errOpt, '40001')) {
                    CLI::write("  - Versi usang ditolak dengan benar: OPTIMISTIC_LOCK_CONFLICT (40001)", 'white');

                    // Coba update dengan expected_version yang benar (1)
                    $resOptOk = pg_query($conn, "SELECT * FROM fn_update_booking_status({$createdBookingId}, 'confirmed', 1, {$touristId})");
                    $updRow = pg_fetch_assoc($resOptOk);
                    if ($updRow && $updRow['status'] === 'confirmed' && $updRow['version'] == 2) {
                        CLI::write("  - Versi benar (1) berhasil diupdate menjadi confirmed (Version naik: {$updRow['version']})", 'white');
                        $testResults[] = ['Uji 4: Optimistic Locking', 'Konflik versi usang ditolak & versi benar naik +1', 'PASS'];
                    } else {
                        $testResults[] = ['Uji 4: Optimistic Locking', 'Gagal memperbarui status dengan versi benar', 'FAIL'];
                    }
                } else {
                    $testResults[] = ['Uji 4: Optimistic Locking', 'Pesan error tidak memuat OPTIMISTIC_LOCK_CONFLICT: ' . $errOpt, 'FAIL'];
                }
            } else {
                pg_query($conn, 'ROLLBACK TO SAVEPOINT sp_optimistic;');
                $testResults[] = ['Uji 4: Optimistic Locking', 'Update versi usang tidak ditolak (Seharusnya conflict!)', 'FAIL'];
            }

            // ---------------------------------------------------------
            // UJI 5: TRIGGER REVIEWS ONLY COMPLETED
            // ---------------------------------------------------------
            CLI::write('[UJI 5] Menguji Trigger trg_reviews_only_completed...', 'cyan');
            // Coba beri ulasan pada booking yang statusnya saat ini 'confirmed' (belum completed)
            pg_query($conn, 'SAVEPOINT sp_review;');
            $resRevFail = @pg_query($conn, "INSERT INTO reviews (booking_id, rating, review_text) VALUES ({$createdBookingId}, 5, 'Ulasan prematur')");
            if ($resRevFail === false) {
                $errRev = pg_last_error($conn);
                pg_query($conn, 'ROLLBACK TO SAVEPOINT sp_review;');

                if (str_contains($errRev, 'completed') || str_contains($errRev, 'P0001')) {
                    CLI::write("  - Sukses ditolak oleh trigger: Ulasan hanya dapat diberikan pada booking completed.", 'white');
                    $testResults[] = ['Uji 5: Trigger Review', 'Review prematur (non-completed) berhasil ditolak', 'PASS'];
                } else {
                    $testResults[] = ['Uji 5: Trigger Review', 'Error lain: ' . $errRev, 'FAIL'];
                }
            } else {
                pg_query($conn, 'ROLLBACK TO SAVEPOINT sp_review;');
                $testResults[] = ['Uji 5: Trigger Review', 'Review pada booking non-completed berhasil disimpan (Seharusnya ditolak!)', 'FAIL'];
            }

            // ---------------------------------------------------------
            // UJI 6: LEAST PRIVILEGE ROLES & RLS
            // ---------------------------------------------------------
            CLI::write('[UJI 6] Menguji Hak Akses Least Privilege (Mobile & Admin Roles)...', 'cyan');

            // 6A. Uji role_sumutgo_mobile: DILARANG DELETE pada guides & DILARANG akses audit_logs
            pg_query($conn, 'SET LOCAL ROLE role_sumutgo_mobile;');

            pg_query($conn, 'SAVEPOINT sp_mob_del;');
            $resMobDel = @pg_query($conn, "DELETE FROM guides WHERE guide_id = 99999");
            $errMobDel = pg_last_error($conn);
            pg_query($conn, 'ROLLBACK TO SAVEPOINT sp_mob_del;');

            pg_query($conn, 'SAVEPOINT sp_mob_audit;');
            $resMobAudit = @pg_query($conn, "SELECT COUNT(*) FROM audit_logs");
            $errMobAudit = pg_last_error($conn);
            pg_query($conn, 'ROLLBACK TO SAVEPOINT sp_mob_audit;');

            pg_query($conn, 'RESET ROLE;');

            $mobilePass = (str_contains($errMobDel, 'permission denied') && str_contains($errMobAudit, 'permission denied'));
            if ($mobilePass) {
                CLI::write("  - role_sumutgo_mobile: DELETE guides ditolak (42501) & akses audit_logs ditolak (42501)", 'white');
            } else {
                CLI::write("  - role_sumutgo_mobile debug: errMobDel = {$errMobDel} | errMobAudit = {$errMobAudit}", 'red');
            }

            // 6B. Uji role_sumutgo_admin: Boleh SELECT audit_logs, tapi DILARANG DELETE audit_logs
            pg_query($conn, 'SET LOCAL ROLE role_sumutgo_admin;');

            $resAdmSel = @pg_query($conn, "SELECT COUNT(*) FROM audit_logs");
            $admSelOk = ($resAdmSel !== false);

            pg_query($conn, 'SAVEPOINT sp_adm_del;');
            $resAdmDel = @pg_query($conn, "DELETE FROM audit_logs WHERE audit_id = 99999");
            $errAdmDel = pg_last_error($conn);
            pg_query($conn, 'ROLLBACK TO SAVEPOINT sp_adm_del;');

            pg_query($conn, 'RESET ROLE;');

            $adminPass = ($admSelOk && str_contains($errAdmDel, 'permission denied'));
            if ($adminPass) {
                CLI::write("  - role_sumutgo_admin: SELECT audit_logs sukses & DELETE audit_logs ditolak (42501)", 'white');
            } else {
                CLI::write("  - role_sumutgo_admin debug: admSelOk = " . ($admSelOk ? 'true' : 'false') . " | errAdmDel = {$errAdmDel}", 'red');
            }

            if ($mobilePass && $adminPass) {
                $testResults[] = ['Uji 6: Least Privilege', 'Pembatasan hak role mobile & admin terbukti presisi', 'PASS'];
            } else {
                $testResults[] = ['Uji 6: Least Privilege', 'Salah satu pembatasan hak tidak berfungsi sebagaimana mestinya', 'FAIL'];
            }

        } catch (Throwable $e) {
            CLI::error("Exception selama pengujian: " . $e->getMessage());
        } finally {
            // Wajib ROLLBACK seluruh transaksi pengujian
            pg_query($conn, 'ROLLBACK;');
            CLI::newLine();
            CLI::write("Transaksi pengujian selesai di-ROLLBACK (Database tetap bersih).", 'green');
        }

        // Tampilkan Tabel Hasil Pengujian
        CLI::newLine();
        CLI::write('====================================================================', 'cyan');
        CLI::write('  HASIL AKHIR 6 SKENARIO PENGUJIAN OBJEK DATABASE SERVER (K2)       ', 'yellow');
        CLI::write('====================================================================', 'cyan');

        CLI::table($testResults, ['Skenario Uji', 'Deskripsi Hasil', 'Status']);

        $allPass = true;
        foreach ($testResults as $t) {
            if ($t[2] !== 'PASS') {
                $allPass = false;
                break;
            }
        }

        if ($allPass) {
            CLI::write('[VERIFIKASI SUKSES] SELURUH 6 SKENARIO PENGUJIAN MEMPEROLEH STATUS PASS!', 'green');
        } else {
            CLI::write('[VERIFIKASI GAGAL] Ada skenario yang tidak memperoleh status PASS.', 'red');
        }
        CLI::write('====================================================================', 'cyan');
    }
}
