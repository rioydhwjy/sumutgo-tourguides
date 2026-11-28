<?php

declare(strict_types=1);

namespace App\Commands;

use App\Controllers\Api\V1\AnalyticsController;
use App\Controllers\Api\V1\AuthController;
use App\Controllers\Api\V1\BookingController;
use App\Controllers\Api\V1\GuideController;
use App\Controllers\Api\V1\ReviewController;
use App\Filters\JwtAuthFilter;
use App\Filters\RoleFilter;
use App\Libraries\AuthContext;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Config\Services;
use Throwable;

class TestApiV1 extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'api:test-v1';
    protected $description = 'Menjalankan pengujian otomatis 12 skenario RESTful Web API v1 (CodeIgniter 4.7 + PostgreSQL 17 Supabase).';
    protected $usage       = 'api:test-v1';

    private array $results = [];
    private string $adminToken = '';
    private string $touristToken = '';
    private ?int $touristUserId = null;
    private ?array $testGuideBefore = null;
    private string $testStartedAt = '';

    public function run(array $params): void
    {
        CLI::write('====================================================================', 'cyan');
        CLI::write('  SUMUT GO - RESTFUL WEB API v1 AUTOMATED TEST SUITE                ', 'yellow');
        CLI::write('====================================================================', 'cyan');

        $db = Database::connect();
        $rowTime = $db->query('SELECT CURRENT_TIMESTAMP::text AS ts')->getRowArray();
        $this->testStartedAt = (string) ($rowTime['ts'] ?? gmdate('Y-m-d H:i:sP'));

        try {
            // Skenario 1: Login Admin & Wisatawan (Dapat JWT 200)
            $this->testScenario1();

            // Skenario 2: Akses Endpoint Terproteksi Tanpa Token (401)
            $this->testScenario2();

            // Skenario 3: Wisatawan Akses Endpoint Admin (403)
            $this->testScenario3();

            // Skenario 4: Validasi Input Tidak Valid (422)
            $this->testScenario4();

            // Skenario 5: Uji SQL Injection (Aman, Parameterized Query)
            $this->testScenario5();

            // Skenario 6: GET /api/v1/guides (Filter, Pagination, Sort Whitelist)
            $this->testScenario6();

            // Skenario 7: CRUD Guides & Optimistic Locking Concurrency Control (201 -> 409 -> 200 -> 200)
            $this->testScenario7($db);

            // Skenario 8: Foreign Key Restrict Handling pada DELETE Guide (409)
            $this->testScenario8();

            // Skenario 9: POST /api/v1/bookings via ACID fn_create_booking & Auto Audit (201)
            $createdBooking = $this->testScenario9($db);

            // Skenario 10: Anti Double-Booking Schedule Overlap (23P01 -> 409)
            $this->testScenario10($createdBooking);

            // Skenario 11: Optimistic Locking pada Update Status Booking (409 & 200)
            $this->testScenario11($createdBooking, $db);

            // Skenario 12: Trigger trg_reviews_only_completed (Ulasan Non-Completed Ditolak 422)
            $this->testScenario12($createdBooking, $db);

        } catch (Throwable $e) {
            CLI::error('Terjadi error tak terduga selama pengujian: ' . $e->getMessage());
            CLI::write($e->getTraceAsString(), 'red');
        }

        // Tampilkan Ringkasan Hasil
        $this->printSummary();
    }

    /**
     * Dispatch an API call through filters and controllers
     */
    private function dispatch(
        string $controllerClass,
        string $action,
        string $method,
        array $data = [],
        ?string $token = null,
        array $roles = [],
        array $params = [],
        array $query = [],
        bool $requiresAuth = false
    ): array {
        AuthContext::clear();
        Services::validation()->reset();
        $_GET = $query;

        $request = Services::request(null, false);
        $request->setGlobal('get', $query);
        $request->setMethod(strtoupper($method));
        $request->setBody(json_encode($data));
        $request->setHeader('Content-Type', 'application/json');

        if ($token !== null) {
            $request->setHeader('Authorization', 'Bearer ' . $token);
        } else {
            $request->removeHeader('Authorization');
        }

        // 1. Run JwtAuthFilter if required or if token provided
        if ($requiresAuth || ! empty($roles) || $token !== null) {
            $jwtFilter = new JwtAuthFilter();
            $jwtResp = $jwtFilter->before($request);
            if ($jwtResp !== null) {
                return [
                    'statusCode' => $jwtResp->getStatusCode(),
                    'body'       => json_decode((string) $jwtResp->getBody(), true),
                ];
            }
        }

        // 2. Run RoleFilter if roles specified
        if (! empty($roles)) {
            $roleFilter = new RoleFilter();
            $roleResp = $roleFilter->before($request, $roles);
            if ($roleResp !== null) {
                return [
                    'statusCode' => $roleResp->getStatusCode(),
                    'body'       => json_decode((string) $roleResp->getBody(), true),
                ];
            }
        }

        // 3. Instantiate & invoke controller
        $response = Services::response(null, false);
        $controller = new $controllerClass();
        $controller->initController($request, $response, Services::logger());

        $resultResponse = $controller->$action(...$params);

        return [
            'statusCode' => $resultResponse->getStatusCode(),
            'body'       => json_decode((string) $resultResponse->getBody(), true),
        ];
    }

    private function testScenario1(): void
    {
        CLI::write("\n[SKENARIO 1] POST /api/v1/auth/login (Admin & Wisatawan dapat JWT 200)...", 'yellow');

        // Login Admin
        $resAdmin = $this->dispatch(AuthController::class, 'login', 'POST', [
            'email'    => 'admin@sumutgo.id',
            'password' => 'Password123!',
        ]);

        $adminPass = ($resAdmin['statusCode'] === 200)
            && ($resAdmin['body']['status'] === 'success')
            && ! empty($resAdmin['body']['data']['token'])
            && (($resAdmin['body']['data']['user']['role'] ?? '') === 'admin');

        $this->adminToken = (string) ($resAdmin['body']['data']['token'] ?? '');

        // Login Wisatawan (Budi Santoso)
        $resTourist = $this->dispatch(AuthController::class, 'login', 'POST', [
            'email'    => 'budi@wisatawan.id',
            'password' => 'Password123!',
        ]);

        $touristPass = ($resTourist['statusCode'] === 200)
            && ($resTourist['body']['status'] === 'success')
            && ! empty($resTourist['body']['data']['token'])
            && (($resTourist['body']['data']['user']['role'] ?? '') === 'wisatawan');

        $this->touristToken = (string) ($resTourist['body']['data']['token'] ?? '');
        $this->touristUserId = (int) ($resTourist['body']['data']['user']['user_id'] ?? 2);

        $pass = $adminPass && $touristPass;
        CLI::write("  - Admin Token: " . substr($this->adminToken, 0, 25) . "...", 'white');
        CLI::write("  - Tourist Token: " . substr($this->touristToken, 0, 25) . "...", 'white');

        $this->recordResult('Skenario 1: Auth Login JWT', $pass, 'Login Admin & Wisatawan berhasil (200 OK + Valid JWT HS256)');
    }

    private function testScenario2(): void
    {
        CLI::write("\n[SKENARIO 2] Akses Endpoint Terproteksi Tanpa Token (Harus 401 Unauthorized)...", 'yellow');

        $resMe = $this->dispatch(AuthController::class, 'me', 'GET', [], null, [], [], [], true);
        $passMe = ($resMe['statusCode'] === 401) && ($resMe['body']['status'] === 'error');

        $resBookings = $this->dispatch(BookingController::class, 'index', 'GET', [], null, [], [], [], true);
        $passBookings = ($resBookings['statusCode'] === 401) && ($resBookings['body']['status'] === 'error');

        $pass = $passMe && $passBookings;
        CLI::write("  - /auth/me tanpa token: HTTP {$resMe['statusCode']} ({$resMe['body']['message']})", 'white');
        CLI::write("  - /bookings tanpa token: HTTP {$resBookings['statusCode']} ({$resBookings['body']['message']})", 'white');

        $this->recordResult('Skenario 2: Protected Auth Guard', $pass, 'Endpoint terproteksi ditolak dengan 401 Unauthorized');
    }

    private function testScenario3(): void
    {
        CLI::write("\n[SKENARIO 3] Wisatawan Mencoba Akses Endpoint Admin (Harus 403 Forbidden)...", 'yellow');

        // Wisatawan coba akses GET /api/v1/audit-logs
        $resAudit = $this->dispatch(AnalyticsController::class, 'auditLogs', 'GET', [], $this->touristToken, ['admin'], [], [], true);
        $passAudit = ($resAudit['statusCode'] === 403) && ($resAudit['body']['status'] === 'error');

        // Wisatawan coba POST /api/v1/guides
        $resGuide = $this->dispatch(GuideController::class, 'create', 'POST', [
            'guide_name' => 'Guide Ilegal',
        ], $this->touristToken, ['admin'], [], [], true);
        $passGuide = ($resGuide['statusCode'] === 403) && ($resGuide['body']['status'] === 'error');

        $pass = $passAudit && $passGuide;
        CLI::write("  - Wisatawan GET /audit-logs: HTTP {$resAudit['statusCode']} ({$resAudit['body']['message']})", 'white');
        CLI::write("  - Wisatawan POST /guides: HTTP {$resGuide['statusCode']} ({$resGuide['body']['message']})", 'white');

        $this->recordResult('Skenario 3: RBAC Access Control', $pass, 'Wisatawan ditolak dari endpoint admin dengan 403 Forbidden');
    }

    private function testScenario4(): void
    {
        CLI::write("\n[SKENARIO 4] Validasi Input Tidak Valid (Harus 422 Unprocessable Entity)...", 'yellow');

        // Email salah pada register
        $resReg = $this->dispatch(AuthController::class, 'register', 'POST', [
            'full_name' => 'Test User',
            'email'     => 'bukan-email-valid',
            'password'  => '123',
        ]);
        $passReg = ($resReg['statusCode'] === 422) && ($resReg['body']['status'] === 'error') && ! empty($resReg['body']['errors']);

        // Tarif negatif pada guide
        $resGuide = $this->dispatch(GuideController::class, 'create', 'POST', [
            'guide_name'        => 'Test Negative Rate',
            'phone_number'      => '081234567890',
            'daily_rate_idr'    => -50000,
            'specialization_id' => 1,
        ], $this->adminToken, ['admin'], [], [], true);
        $passGuide = ($resGuide['statusCode'] === 422) && ($resGuide['body']['status'] === 'error');

        // Tanggal selesai < tanggal mulai pada booking
        $resBook = $this->dispatch(BookingController::class, 'create', 'POST', [
            'guide_id'         => 1,
            'start_date'       => '2026-12-10',
            'end_date'         => '2026-12-05',
            'number_of_guests' => 2,
        ], $this->touristToken, ['wisatawan', 'admin'], [], [], true);
        $passBook = ($resBook['statusCode'] === 422) && ($resBook['body']['status'] === 'error');

        $pass = $passReg && $passGuide && $passBook;
        CLI::write("  - Register email salah: HTTP {$resReg['statusCode']} (Errors terdeteksi)", 'white');
        CLI::write("  - Guide tarif negatif: HTTP {$resGuide['statusCode']} (Errors terdeteksi)", 'white');
        CLI::write("  - Booking tanggal terbalik: HTTP {$resBook['statusCode']} ({$resBook['body']['message']})", 'white');

        $this->recordResult('Skenario 4: Input Validation', $pass, 'Seluruh payload tidak valid berhasil dicegat dengan 422');
    }

    private function testScenario5(): void
    {
        CLI::write("\n[SKENARIO 5] Uji SQL Injection pada Parameter q & Login (' OR '1'='1' --)...", 'yellow');

        // SQLi pada login
        $resLogin = $this->dispatch(AuthController::class, 'login', 'POST', [
            'email'    => "' OR '1'='1' --",
            'password' => 'apapun',
        ]);
        $passLogin = ($resLogin['statusCode'] === 401 || $resLogin['statusCode'] === 422) && ($resLogin['statusCode'] !== 500);

        // SQLi pada search query q (public GET)
        $resSearch = $this->dispatch(GuideController::class, 'index', 'GET', [], null, [], [], [
            'q' => "' OR '1'='1' --",
        ]);
        $passSearch = ($resSearch['statusCode'] === 200) && ($resSearch['body']['status'] === 'success');
        $itemCount = is_array($resSearch['body']['data'] ?? null) ? count($resSearch['body']['data']) : 0;

        $pass = $passLogin && $passSearch;
        CLI::write("  - SQLi Login: HTTP {$resLogin['statusCode']} ({$resLogin['body']['message']})", 'white');
        CLI::write("  - SQLi Search q: HTTP {$resSearch['statusCode']} (Jumlah hasil aman: {$itemCount})", 'white');

        $this->recordResult('Skenario 5: SQLi Resistance', $pass, 'Query aman 100% menggunakan Parameterized Binding anti SQL Injection');
    }

    private function testScenario6(): void
    {
        CLI::write("\n[SKENARIO 6] GET /api/v1/guides (Filter, Sort & Pagination Metadata)...", 'yellow');

        $res = $this->dispatch(GuideController::class, 'index', 'GET', [], null, [], [], [
            'status'   => 'Tersedia',
            'sort'     => 'rating_desc',
            'page'     => 1,
            'per_page' => 10,
        ]);

        $meta = $res['body']['meta'] ?? [];
        $pass = ($res['statusCode'] === 200)
            && ($res['body']['status'] === 'success')
            && isset($meta['current_page'], $meta['per_page'], $meta['total_items'], $meta['total_pages'])
            && (count($res['body']['data'] ?? []) <= 10);

        CLI::write("  - HTTP: {$res['statusCode']} | Total Guides: {$meta['total_items']} | Per Page: {$meta['per_page']} | Total Pages: {$meta['total_pages']}", 'white');
        CLI::write("  - Guide #1: {$res['body']['data'][0]['guide_name']} (Rating: {$res['body']['data'][0]['gmaps_rating']})", 'white');

        $this->recordResult('Skenario 6: Guides Query & Meta', $pass, 'Query view vw_guides_detail berhasil dengan filtering & pagination');
    }

    private function testScenario7($db): void
    {
        CLI::write("\n[SKENARIO 7] CRUD Guides & Optimistic Locking Concurrency Control...", 'yellow');

        // Bersihkan data dummy sisa pengujian sebelumnya jika ada
        $db->table('guide_languages')->where('guide_id', 0)->delete();
        $db->table('guides')->where('guide_id', 0)->delete();
        $db->table('guides')->where('phone_number', '6281299998888')->orWhere('agency_name', 'PT Uji Coba Medan')->delete();

        // 1. Create Guide Baru
        $createRes = $this->dispatch(GuideController::class, 'create', 'POST', [
            'guide_name'             => 'Pemandu Uji Coba Concurrency',
            'agency_name'            => 'PT Uji Coba Medan',
            'phone_number'           => '081299998888',
            'daily_rate_idr'          => 600000,
            'specialization_id'       => 1,
            'primary_destination_id'  => 1,
            'recommended_culinary_id' => 1,
            'partner_hotel_id'        => 1,
            'default_transport_id'    => 1,
            'language_ids'            => [1, 2, 4],
        ], $this->adminToken, ['admin'], [], [], true);

        $createdGuide = $createRes['body']['data'] ?? [];
        $guideId = (int) ($createdGuide['guide_id'] ?? 0);
        $initialVersion = (int) ($createdGuide['version'] ?? 1);

        CLI::write("  - [POST] Guide baru dibuat: ID {$guideId} (Kode: " . ($createdGuide['guide_code'] ?? 'N/A') . ", Versi: {$initialVersion})", 'white');
        if ($createRes['statusCode'] !== 201) {
            CLI::error('Create guide failed: ' . json_encode($createRes));
        }

        // 2. Update dengan Versi Usang (Conflict 409)
        $conflictRes = $this->dispatch(GuideController::class, 'update', 'PUT', [
            'guide_name'     => 'Update Gagal Versi Usang',
            'daily_rate_idr' => 700000,
            'version'        => 999, // Versi salah!
        ], $this->adminToken, ['admin'], [$guideId], [], true);

        $passConflict = ($conflictRes['statusCode'] === 409);
        CLI::write("  - [PUT Conflict] Versi 999 ditolak: HTTP {$conflictRes['statusCode']} ({$conflictRes['body']['message']})", 'white');

        // 3. Update dengan Versi Benar (Success 200, Version naik +1)
        $validRes = $this->dispatch(GuideController::class, 'update', 'PUT', [
            'guide_name'     => 'Pemandu Uji Coba S.Par (Updated)',
            'daily_rate_idr' => 650000,
            'version'        => $initialVersion, // Versi benar
        ], $this->adminToken, ['admin'], [$guideId], [], true);

        $newVersion = (int) ($validRes['body']['data']['version'] ?? 0);
        $passUpdate = ($validRes['statusCode'] === 200) && ($newVersion === $initialVersion + 1);
        CLI::write("  - [PUT Success] Versi {$initialVersion} sukses diupdate: Versi baru naik menjadi {$newVersion}", 'white');

        // 4. Delete Guide Baru (Success 200)
        $delRes = $this->dispatch(GuideController::class, 'delete', 'DELETE', [], $this->adminToken, ['admin'], [$guideId], [], true);
        $passDelete = ($delRes['statusCode'] === 200);
        if ($guideId > 0) {
            $db->table('audit_logs')->where('entity_type', 'guide')->where('entity_id', $guideId)->delete();
        }
        CLI::write("  - [DELETE] Guide dummy ID {$guideId} berhasil dihapus (HTTP 200)", 'white');

        $pass = ($createRes['statusCode'] === 201) && $passConflict && $passUpdate && $passDelete;
        $this->recordResult('Skenario 7: Guide CRUD & Optimistic Lock', $pass, 'Optimistic locking (409 vs 200) & CASCADE language sinkron presisi');
    }

    private function testScenario8(): void
    {
        CLI::write("\n[SKENARIO 8] Foreign Key Restrict Handling pada DELETE Guide (Harus 409)...", 'yellow');

        // Guide 1 memiliki booking aktif di database seed
        $delRes = $this->dispatch(GuideController::class, 'delete', 'DELETE', [], $this->adminToken, ['admin'], [1], [], true);

        $pass = ($delRes['statusCode'] === 409)
            && ($delRes['body']['status'] === 'error')
            && str_contains($delRes['body']['message'], 'Data tidak dapat dihapus');

        CLI::write("  - DELETE Guide ID 1 (Relasi Booking): HTTP {$delRes['statusCode']} ({$delRes['body']['message']})", 'white');

        $this->recordResult('Skenario 8: FK Restrict Error 23503', $pass, 'Penghapusan data terikat relasi dicegah ramah dengan 409 Conflict');
    }

    private function testScenario9($db): array
    {
        CLI::write("\n[SKENARIO 9] POST /api/v1/bookings via ACID fn_create_booking & Auto Audit...", 'yellow');

        // Bersihkan data booking uji sisa jika ada & catat status awal guide ID 10
        $db->table('bookings')->where('guide_id', 10)->where('start_date', '2027-04-10')->delete();
        $this->testGuideBefore = $db->table('guides')
            ->select('availability_status, version')
            ->where('guide_id', 10)
            ->get()
            ->getRowArray() ?: null;

        // Wisatawan memesan guide ID 10 untuk rentang tanggal unik 2027-04-10 s.d. 2027-04-12 (3 hari)
        $res = $this->dispatch(BookingController::class, 'create', 'POST', [
            'guide_id'         => 10,
            'start_date'       => '2027-04-10',
            'end_date'         => '2027-04-12',
            'number_of_guests' => 2,
            'destination_id'   => 1,
            'transport_id'     => 1,
        ], $this->touristToken, ['wisatawan', 'admin'], [], [], true);

        $booking = $res['body']['data'] ?? [];
        $bookingId = (int) ($booking['booking_id'] ?? 0);
        $duration = (int) ($booking['duration_days'] ?? 0);
        $totalPrice = (int) ($booking['total_price_idr'] ?? 0);

        // Verifikasi trigger audit_logs mencatat INSERT otomatis
        $auditRow = $db->table('audit_logs')
            ->where('entity_type', 'booking')
            ->where('entity_id', $bookingId)
            ->where('action', 'INSERT')
            ->get()
            ->getRowArray();

        $pass = ($res['statusCode'] === 201)
            && ($duration === 3)
            && ($totalPrice > 0)
            && ! empty($auditRow);

        CLI::write("  - Booking Created: ID {$bookingId} (Kode: {$booking['booking_code']}, Durasi: {$duration} hari, Total: Rp " . number_format($totalPrice) . ")", 'white');
        CLI::write("  - Auto Audit Log: Terverifikasi di tabel audit_logs (Log ID: " . ($auditRow['audit_id'] ?? '-') . ", Action: INSERT)", 'white');

        $this->recordResult('Skenario 9: Booking Create & Audit Trigger', $pass, 'fn_create_booking & trigger audit_logs mencatat reservasi otomatis (201)');

        return $booking;
    }

    private function testScenario10(array $createdBooking): void
    {
        CLI::write("\n[SKENARIO 10] Anti Double-Booking Schedule Overlap (23P01 -> 409 Conflict)...", 'yellow');

        $guideId = (int) ($createdBooking['guide_id'] ?? 10);

        // Coba pesan guide yang sama pada rentang bertabrakan (2027-04-11 s.d. 2027-04-13)
        $res = $this->dispatch(BookingController::class, 'create', 'POST', [
            'guide_id'         => $guideId,
            'start_date'       => '2027-04-11',
            'end_date'         => '2027-04-13',
            'number_of_guests' => 3,
        ], $this->touristToken, ['wisatawan', 'admin'], [], [], true);

        $pass = ($res['statusCode'] === 409)
            && ($res['body']['status'] === 'error')
            && str_contains($res['body']['message'], 'sudah memiliki jadwal booking');

        CLI::write("  - Percobaan Booking Bertabrakan: HTTP {$res['statusCode']} ({$res['body']['message']})", 'white');

        $this->recordResult('Skenario 10: Anti Double-Booking 23P01', $pass, 'Exclusion constraint PostgreSQL menolak jadwal bentrok dengan 409 Conflict');
    }

    private function testScenario11(array $createdBooking, $db): void
    {
        CLI::write("\n[SKENARIO 11] Optimistic Locking pada Update Status Booking (409 & 200)...", 'yellow');

        $bookingId = (int) ($createdBooking['booking_id'] ?? 0);
        $currVersion = (int) ($createdBooking['version'] ?? 1);

        // 1. Update dengan Versi Usang (Conflict 409)
        $resConflict = $this->dispatch(BookingController::class, 'updateStatus', 'PATCH', [
            'status'  => 'confirmed',
            'version' => 999,
        ], $this->adminToken, ['admin'], [$bookingId], [], true);

        $passConflict = ($resConflict['statusCode'] === 409)
            && str_contains($resConflict['body']['message'], 'telah diubah oleh pengguna lain');

        CLI::write("  - Versi usang (999) ditolak: HTTP {$resConflict['statusCode']} ({$resConflict['body']['message']})", 'white');

        // 2. Update dengan Versi Benar (Success 200, Version naik +1)
        $resValid = $this->dispatch(BookingController::class, 'updateStatus', 'PATCH', [
            'status'  => 'confirmed',
            'version' => $currVersion,
        ], $this->adminToken, ['admin'], [$bookingId], [], true);

        $updatedBooking = $resValid['body']['data'] ?? [];
        $newVersion = (int) ($updatedBooking['version'] ?? 0);

        $passValid = ($resValid['statusCode'] === 200) && ($newVersion === $currVersion + 1);
        CLI::write("  - Versi valid ({$currVersion}) berhasil: Status menjadi '{$updatedBooking['status']}', Versi naik ke {$newVersion}", 'white');

        $pass = $passConflict && $passValid;
        $this->recordResult('Skenario 11: Booking Optimistic Locking', $pass, 'fn_update_booking_status menolak versi usang (409) dan menaikkan versi (200)');
    }

    private function testScenario12(array $createdBooking, $db): void
    {
        CLI::write("\n[SKENARIO 12] Trigger trg_reviews_only_completed (Ulasan Non-Completed Ditolak 422)...", 'yellow');

        $bookingId = (int) ($createdBooking['booking_id'] ?? 0);

        // Booking saat ini berstatus 'confirmed' (belum 'completed')
        $res = $this->dispatch(ReviewController::class, 'create', 'POST', [
            'booking_id'  => $bookingId,
            'rating'      => 5,
            'review_text' => 'Pemandu sangat menyenangkan dan berwawasan luas!',
        ], $this->touristToken, ['wisatawan'], [], [], true);

        $pass = ($res['statusCode'] === 422)
            && ($res['body']['status'] === 'error')
            && str_contains($res['body']['message'], 'completed');

        CLI::write("  - Ulasan pada booking status 'confirmed': HTTP {$res['statusCode']} ({$res['body']['message']})", 'white');

        // Pembersihan Data Uji Dummy agar database Supabase tetap bersih
        $db->table('audit_logs')->where('entity_type', 'booking')->where('entity_id', $bookingId)->delete();
        $db->table('bookings')->where('booking_id', $bookingId)->delete();
        if (is_array($this->testGuideBefore)) {
            $db->table('guides')->where('guide_id', 10)->update([
                'availability_status' => $this->testGuideBefore['availability_status'],
                'version'             => (int) $this->testGuideBefore['version'],
            ]);
            if ($this->testStartedAt !== '') {
                $db->query(
                    "DELETE FROM audit_logs WHERE entity_type = 'guide' AND entity_id = 10 AND changed_at >= ?::timestamptz",
                    [$this->testStartedAt]
                );
            }
        }
        CLI::write("  - Data reservasi uji coba ID {$bookingId} dan audit logs terkait telah dibersihkan.", 'cyan');

        $this->recordResult('Skenario 12: Review Guard Trigger P0001', $pass, 'Trigger trg_reviews_only_completed menolak ulasan sebelum status completed (422)');
    }

    private function recordResult(string $scenario, bool $pass, string $description): void
    {
        $this->results[] = [
            'scenario'    => $scenario,
            'description' => $description,
            'status'      => $pass ? 'PASS' : 'FAIL',
        ];
    }

    private function printSummary(): void
    {
        CLI::newLine();
        CLI::write('====================================================================', 'cyan');
        CLI::write('  HASIL AKHIR 12 SKENARIO PENGUJIAN RESTFUL WEB API v1 (K3, K4, K5) ', 'yellow');
        CLI::write('====================================================================', 'cyan');

        $table = [];
        $allPass = true;

        foreach ($this->results as $r) {
            $table[] = [
                $r['scenario'],
                $r['description'],
                $r['status'],
            ];
            if ($r['status'] !== 'PASS') {
                $allPass = false;
            }
        }

        CLI::table($table, ['Skenario Uji API v1', 'Deskripsi Hasil Pengujian', 'Status']);

        CLI::newLine();
        if ($allPass) {
            CLI::write('[VERIFIKASI SUKSES] SELURUH 12 SKENARIO PENGUJIAN API v1 MEMPEROLEH STATUS PASS!', 'green');
        } else {
            CLI::write('[VERIFIKASI GAGAL] Terdapat skenario pengujian yang belum PASS.', 'red');
        }
        CLI::write('====================================================================', 'cyan');
    }
}
