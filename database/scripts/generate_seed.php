<?php

declare(strict_types=1);

/**
 * database/scripts/generate_seed.php
 * Membaca tour_guides_medan_150.xlsx dan menghasilkan database/sql/02_seed_data.sql
 */

$xlsxPaths = [
    __DIR__ . '/../../../tour_guides_medan_150.xlsx',
    'D:/Kuliah/SEMESTER 5/Manajemen Basis Data Client Server TRPL-5A 2024/tour_guides_medan_150.xlsx',
];

$xlsxPath = null;
foreach ($xlsxPaths as $path) {
    if (file_exists($path)) {
        $xlsxPath = realpath($path);
        break;
    }
}

if (! $xlsxPath) {
    fwrite(STDERR, "Error: tour_guides_medan_150.xlsx tidak ditemukan!\n");
    exit(1);
}

echo "Membaca file dataset: {$xlsxPath}\n";

$zip = new ZipArchive();
if ($zip->open($xlsxPath) !== true) {
    fwrite(STDERR, "Error: Gagal membuka file zip xlsx!\n");
    exit(1);
}

$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
$zip->close();

if (! $sheetXml) {
    fwrite(STDERR, "Error: xl/worksheets/sheet1.xml tidak ditemukan di dalam xlsx!\n");
    exit(1);
}

$xml = simplexml_load_string($sheetXml);
$xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
$rows = $xml->xpath('//m:sheetData/m:row');

$totalRows = count($rows);
echo "Total baris di sheet1: {$totalRows}\n";

$specs = [];
$languages = [];
$destinations = [];
$culinaries = [];
$hotels = [];
$transports = [];
$guides = [];

// Skip header (index 0)
for ($i = 1; $i < $totalRows; $i++) {
    $row = $rows[$i];
    $cells = [];
    foreach ($row->c as $c) {
        if (isset($c->is->t)) {
            $cells[] = (string) $c->is->t;
        } elseif (isset($c->v)) {
            $cells[] = (string) $c->v;
        } else {
            $cells[] = '';
        }
    }

    if (count($cells) < 18) {
        continue;
    }

    $guideCode  = trim($cells[0]);
    $guideName  = trim($cells[1]);
    $agencyName = trim($cells[2]);
    $rating     = (float) $cells[3];
    $reviews    = (int) $cells[4];
    $address    = trim($cells[5]);
    $coords     = trim($cells[6]);
    $phone      = trim($cells[7]);
    $langsStr   = trim($cells[8]);
    $spec       = trim($cells[9]);
    $rate       = (int) $cells[10];
    $status     = trim($cells[11]);
    $destCode   = trim($cells[12]);
    $destName   = trim($cells[13]);
    $culCode    = trim($cells[14]);
    $culName    = trim($cells[15]);
    $hotelRaw   = trim($cells[16]);
    $transRaw   = trim($cells[17]);

    // Parse koordinat lat long
    $coordParts = explode(',', $coords);
    $lat = isset($coordParts[0]) ? (float) trim($coordParts[0]) : 3.5952;
    $lng = isset($coordParts[1]) ? (float) trim($coordParts[1]) : 98.6722;

    // Normalisasi nomor telepon (^62[0-9]{9,12}$)
    $cleanPhone = preg_replace('/[\s\-]/', '', $phone);
    if (str_starts_with($cleanPhone, '+62')) {
        $cleanPhone = substr($cleanPhone, 1);
    } elseif (str_starts_with($cleanPhone, '08')) {
        $cleanPhone = '628' . substr($cleanPhone, 2);
    }

    // Parse hotel
    if (preg_match('/^(HTL-[0-9]{2,3})\s*\((.+)\)$/', $hotelRaw, $mH)) {
        $hotelCode = $mH[1];
        $hotelName = trim($mH[2]);
    } else {
        $hotelCode = 'HTL-01';
        $hotelName = $hotelRaw;
    }

    // Parse transport
    if (preg_match('/^(TRP-[0-9]{2,3})\s*\((.+)\)$/', $transRaw, $mT)) {
        $transCode = $mT[1];
        $transName = trim($mT[2]);
    } else {
        $transCode = 'TRP-01';
        $transName = $transRaw;
    }

    // Collect masters
    $specs[$spec] = true;

    $langsList = array_values(array_filter(array_map('trim', explode(',', $langsStr))));
    foreach ($langsList as $l) {
        $languages[$l] = true;
    }

    $destinations[$destCode] = $destName;
    $culinaries[$culCode] = $culName;
    $hotels[$hotelCode] = $hotelName;
    $transports[$transCode] = $transName;

    $guides[] = [
        'code'         => $guideCode,
        'name'         => $guideName,
        'agency'       => $agencyName,
        'rating'       => $rating,
        'reviews'      => $reviews,
        'address'      => $address,
        'lat'          => $lat,
        'lng'          => $lng,
        'phone'        => $cleanPhone,
        'spec'         => $spec,
        'rate'         => $rate,
        'status'       => $status,
        'dest_code'    => $destCode,
        'cul_code'     => $culCode,
        'hotel_code'   => $hotelCode,
        'trans_code'   => $transCode,
        'languages'    => $langsList,
    ];
}

ksort($specs);
ksort($languages);
ksort($destinations);
ksort($culinaries);
ksort($hotels);
ksort($transports);

echo "Statistik Ekstraksi:\n";
echo "- Spesialisasi : " . count($specs) . "\n";
echo "- Bahasa        : " . count($languages) . "\n";
echo "- Destinasi     : " . count($destinations) . "\n";
echo "- Kuliner       : " . count($culinaries) . "\n";
echo "- Hotel         : " . count($hotels) . "\n";
echo "- Transportasi  : " . count($transports) . "\n";
echo "- Total Guides  : " . count($guides) . "\n";

// Password hash untuk demo users: Password123!
$bcryptPassword = password_hash('Password123!', PASSWORD_BCRYPT);

function escSql(string $str): string {
    return str_replace("'", "''", $str);
}

$sql = [];

$sql[] = "-- =====================================================================";
$sql[] = "-- SUMUT GO - SEED DATA 150 TOUR GUIDES & OPERATIONAL DEMO";
$sql[] = "-- File: 02_seed_data.sql";
$sql[] = "-- Sifat: Idempoten (ON CONFLICT DO NOTHING) & 100% Dinamis Foreign Key";
$sql[] = "--";
$sql[] = "-- KREDENSIAL AKUN DEMO (Password Plaintext: Password123!):";
$sql[] = "-- 1. Admin      : admin@sumutgo.id       (Role: admin)";
$sql[] = "-- 2. Wisatawan 1: budi@wisatawan.id      (Role: wisatawan)";
$sql[] = "-- 3. Wisatawan 2: siti@wisatawan.id      (Role: wisatawan)";
$sql[] = "-- 4. Guide 1    : horas@guide.id         (Role: guide - TG-001)";
$sql[] = "-- 5. Guide 2    : kelvin@guide.id        (Role: guide - TG-002)";
$sql[] = "-- =====================================================================";
$sql[] = "";
$sql[] = "BEGIN;";
$sql[] = "";

// 1. Users
$sql[] = "-- ---------------------------------------------------------------------";
$sql[] = "-- 1. SEED TABEL users (5 AKUN DEMO)";
$sql[] = "-- ---------------------------------------------------------------------";
$users = [
    ['admin@sumutgo.id', 'Administrator SUMUT GO', 'admin'],
    ['budi@wisatawan.id', 'Budi Santoso', 'wisatawan'],
    ['siti@wisatawan.id', 'Siti Rahmawati', 'wisatawan'],
    ['horas@guide.id', 'Horas Parlindungan Siahaan', 'guide'],
    ['kelvin@guide.id', 'Kelvin Wijaya Tan', 'guide'],
];

foreach ($users as $u) {
    $eEmail = escSql(strtolower(trim($u[0])));
    $eName  = escSql($u[1]);
    $eRole  = escSql($u[2]);
    $sql[] = "INSERT INTO users (full_name, email, password_hash, role, is_active) " .
             "VALUES ('{$eName}', '{$eEmail}', '{$bcryptPassword}', '{$eRole}', TRUE) " .
             "ON CONFLICT (email) DO NOTHING;";
}
$sql[] = "";

// 2. tour_specializations
$sql[] = "-- ---------------------------------------------------------------------";
$sql[] = "-- 2. SEED TABEL tour_specializations (" . count($specs) . " SPESIALISASI)";
$sql[] = "-- ---------------------------------------------------------------------";
$specDesc = [
    'City Tour & Belanja' => 'Eksplorasi pusat kota, pusat perbelanjaan, landmark modern dan pasar tradisional Medan.',
    'Eco-Tourism & Alam' => 'Wisata alam liar, konservasi satwa, trekking hutan hujan tropis dan geopark Sumatera Utara.',
    'Heritage & Sejarah Medan' => 'Penelusuran warisan arsitektur kolonial, Kesultanan Deli, dan situs budaya bersejarah.',
    'Kuliner Malam & Street Food' => 'Petualangan rasa jajanan legendaris, pasar malam, dan aneka kuliner otentik multi-etnis.',
    'Photography & Budaya' => 'Hunting foto spot lanskap terbaik, upacara adat, serta keragaman seni budaya lokal.',
];

foreach (array_keys($specs) as $s) {
    $eName = escSql($s);
    $eDesc = escSql($specDesc[$s] ?? 'Layanan pemandu wisata spesialisasi ' . $s);
    $sql[] = "INSERT INTO tour_specializations (specialization_name, description) " .
             "VALUES ('{$eName}', '{$eDesc}') " .
             "ON CONFLICT (specialization_name) DO NOTHING;";
}
$sql[] = "";

// 3. languages
$sql[] = "-- ---------------------------------------------------------------------";
$sql[] = "-- 3. SEED TABEL languages (" . count($languages) . " BAHASA)";
$sql[] = "-- ---------------------------------------------------------------------";
foreach (array_keys($languages) as $l) {
    $eLang = escSql($l);
    $sql[] = "INSERT INTO languages (language_name) " .
             "VALUES ('{$eLang}') " .
             "ON CONFLICT (language_name) DO NOTHING;";
}
$sql[] = "";

// 4. destinations
$sql[] = "-- ---------------------------------------------------------------------";
$sql[] = "-- 4. SEED TABEL destinations (" . count($destinations) . " DESTINASI)";
$sql[] = "-- ---------------------------------------------------------------------";
foreach ($destinations as $code => $name) {
    $eCode = escSql($code);
    $eName = escSql($name);
    $sql[] = "INSERT INTO destinations (destination_code, destination_name) " .
             "VALUES ('{$eCode}', '{$eName}') " .
             "ON CONFLICT (destination_code) DO NOTHING;";
}
$sql[] = "";

// 5. culinaries
$sql[] = "-- ---------------------------------------------------------------------";
$sql[] = "-- 5. SEED TABEL culinaries (" . count($culinaries) . " KULINER)";
$sql[] = "-- ---------------------------------------------------------------------";
foreach ($culinaries as $code => $name) {
    $eCode = escSql($code);
    $eName = escSql($name);
    $sql[] = "INSERT INTO culinaries (culinary_code, culinary_name) " .
             "VALUES ('{$eCode}', '{$eName}') " .
             "ON CONFLICT (culinary_code) DO NOTHING;";
}
$sql[] = "";

// 6. hotels
$sql[] = "-- ---------------------------------------------------------------------";
$sql[] = "-- 6. SEED TABEL hotels (" . count($hotels) . " HOTEL)";
$sql[] = "-- ---------------------------------------------------------------------";
foreach ($hotels as $code => $name) {
    $eCode = escSql($code);
    $eName = escSql($name);
    $sql[] = "INSERT INTO hotels (hotel_code, hotel_name) " .
             "VALUES ('{$eCode}', '{$eName}') " .
             "ON CONFLICT (hotel_code) DO NOTHING;";
}
$sql[] = "";

// 7. transports
$sql[] = "-- ---------------------------------------------------------------------";
$sql[] = "-- 7. SEED TABEL transports (" . count($transports) . " TRANSPORTASI)";
$sql[] = "-- ---------------------------------------------------------------------";
foreach ($transports as $code => $name) {
    $eCode = escSql($code);
    $eName = escSql($name);
    $sql[] = "INSERT INTO transports (transport_code, transport_name) " .
             "VALUES ('{$eCode}', '{$eName}') " .
             "ON CONFLICT (transport_code) DO NOTHING;";
}
$sql[] = "";

// 8. guides
$sql[] = "-- ---------------------------------------------------------------------";
$sql[] = "-- 8. SEED TABEL guides (150 PEMANDU WISATA DENGAN RESOLUSI FK DINAMIS)";
$sql[] = "-- ---------------------------------------------------------------------";
foreach ($guides as $g) {
    $eCode     = escSql($g['code']);
    $eName     = escSql($g['name']);
    $eAgency   = escSql($g['agency']);
    $rating    = number_format($g['rating'], 1, '.', '');
    $reviews   = (int) $g['reviews'];
    $eAddress  = escSql($g['address']);
    $lat       = number_format($g['lat'], 6, '.', '');
    $lng       = number_format($g['lng'], 6, '.', '');
    $ePhone    = escSql($g['phone']);
    $rate      = (int) $g['rate'];
    $eStatus   = escSql($g['status']);
    $eSpec     = escSql($g['spec']);
    $eDestCode = escSql($g['dest_code']);
    $eCulCode  = escSql($g['cul_code']);
    $eHtlCode  = escSql($g['hotel_code']);
    $eTrpCode  = escSql($g['trans_code']);

    $sql[] = "INSERT INTO guides (" .
             "guide_code, guide_name, agency_name, gmaps_rating, gmaps_review_count, " .
             "basecamp_address, latitude, longitude, phone_number, daily_rate_idr, availability_status, " .
             "specialization_id, primary_destination_id, recommended_culinary_id, partner_hotel_id, default_transport_id, version" .
             ") VALUES (" .
             "'{$eCode}', '{$eName}', '{$eAgency}', {$rating}, {$reviews}, " .
             "'{$eAddress}', {$lat}, {$lng}, '{$ePhone}', {$rate}, '{$eStatus}', " .
             "(SELECT specialization_id FROM tour_specializations WHERE specialization_name = '{$eSpec}'), " .
             "(SELECT destination_id FROM destinations WHERE destination_code = '{$eDestCode}'), " .
             "(SELECT culinary_id FROM culinaries WHERE culinary_code = '{$eCulCode}'), " .
             "(SELECT hotel_id FROM hotels WHERE hotel_code = '{$eHtlCode}'), " .
             "(SELECT transport_id FROM transports WHERE transport_code = '{$eTrpCode}'), " .
             "1" .
             ") ON CONFLICT (guide_code) DO NOTHING;";
}
$sql[] = "";

// Hubungkan user_id akun demo guide ke TG-001 dan TG-002
$sql[] = "-- Hubungkan user_id akun demo guide";
$sql[] = "UPDATE guides SET user_id = (SELECT user_id FROM users WHERE email = 'horas@guide.id') WHERE guide_code = 'TG-001';";
$sql[] = "UPDATE guides SET user_id = (SELECT user_id FROM users WHERE email = 'kelvin@guide.id') WHERE guide_code = 'TG-002';";
$sql[] = "";

// 9. guide_languages (M:N)
$sql[] = "-- ---------------------------------------------------------------------";
$sql[] = "-- 9. SEED TABEL guide_languages (RELASI M:N 150 GUIDES & BAHASA)";
$sql[] = "-- ---------------------------------------------------------------------";
foreach ($guides as $g) {
    $eCode = escSql($g['code']);
    foreach ($g['languages'] as $langName) {
        $eLang = escSql($langName);
        $sql[] = "INSERT INTO guide_languages (guide_id, language_id) VALUES (" .
                 "(SELECT guide_id FROM guides WHERE guide_code = '{$eCode}'), " .
                 "(SELECT language_id FROM languages WHERE language_name = '{$eLang}')" .
                 ") ON CONFLICT (guide_id, language_id) DO NOTHING;";
    }
}
$sql[] = "";

// 10. bookings (5 transaksi dengan 5 status lengkap, tanpa duration_days & total_price_idr)
$sql[] = "-- ---------------------------------------------------------------------";
$sql[] = "-- 10. SEED TABEL bookings (5 TRANSAKSI DENGAN 5 STATUS LENGKAP)";
$sql[] = "-- KETENTUAN: Tanpa duration_days & total_price_idr (STORED GENERATED)";
$sql[] = "-- ---------------------------------------------------------------------";
$demoBookings = [
    [
        'code'        => 'BK-20261010-0001',
        'tourist'     => 'budi@wisatawan.id',
        'guide'       => 'TG-001',
        'dest'        => 'WST-02',
        'trans'       => 'TRP-03',
        'start_date'  => '2026-10-10',
        'end_date'    => '2026-10-12',
        'rate'        => 550000,
        'guests'      => 2,
        'point'       => 'Lobby Grand CityHall Hotel Medan',
        'notes'       => 'Trip sejarah Medan Heritage dan Tjong A Fie Mansion.',
        'status'      => 'completed',
        'reason'      => null,
        'cancelled_at'=> null,
    ],
    [
        'code'        => 'BK-20261120-0002',
        'tourist'     => 'siti@wisatawan.id',
        'guide'       => 'TG-002',
        'dest'        => 'WST-07',
        'trans'       => 'TRP-04',
        'start_date'  => '2026-11-20',
        'end_date'    => '2026-11-21',
        'rate'        => 500000,
        'guests'      => 3,
        'point'       => 'Depan Vihara Maitreya Cemara Asri',
        'notes'       => 'Eksplorasi kuliner malam Selat Panjang.',
        'status'      => 'ongoing',
        'reason'      => null,
        'cancelled_at'=> null,
    ],
    [
        'code'        => 'BK-20261201-0003',
        'tourist'     => 'budi@wisatawan.id',
        'guide'       => 'TG-003',
        'dest'        => 'WST-13',
        'trans'       => 'TRP-16',
        'start_date'  => '2026-12-01',
        'end_date'    => '2026-12-03',
        'rate'        => 650000,
        'guests'      => 4,
        'point'       => 'Bandara Kualanamu Gate Kedatangan',
        'notes'       => 'Trekking Bukit Lawang & Taman Nasional Gunung Leuser.',
        'status'      => 'confirmed',
        'reason'      => null,
        'cancelled_at'=> null,
    ],
    [
        'code'        => 'BK-20261210-0004',
        'tourist'     => 'siti@wisatawan.id',
        'guide'       => 'TG-004',
        'dest'        => 'WST-01',
        'trans'       => 'TRP-01',
        'start_date'  => '2026-12-10',
        'end_date'    => '2026-12-11',
        'rate'        => 400000,
        'guests'      => 2,
        'point'       => 'Halaman Parkir Istana Maimun',
        'notes'       => 'Tur edukasi budaya Kesultanan Deli.',
        'status'      => 'pending',
        'reason'      => null,
        'cancelled_at'=> null,
    ],
    [
        'code'        => 'BK-20261105-0005',
        'tourist'     => 'budi@wisatawan.id',
        'guide'       => 'TG-005',
        'dest'        => 'WST-19',
        'trans'       => 'TRP-05',
        'start_date'  => '2026-11-05',
        'end_date'    => '2026-11-07',
        'rate'        => 600000,
        'guests'      => 2,
        'point'       => 'Hotel Santika Premiere Dyandra',
        'notes'       => 'Wisata Danau Toba via Parapat.',
        'status'      => 'cancelled',
        'reason'      => 'Perubahan jadwal mendadak oleh wisatawan',
        'cancelled_at'=> '2026-11-04 14:30:00+07',
    ],
];

foreach ($demoBookings as $b) {
    $eCode   = escSql($b['code']);
    $eTour   = escSql($b['tourist']);
    $eGuide  = escSql($b['guide']);
    $eDest   = escSql($b['dest']);
    $eTrans  = escSql($b['trans']);
    $start   = escSql($b['start_date']);
    $end     = escSql($b['end_date']);
    $rate    = (int) $b['rate'];
    $guests  = (int) $b['guests'];
    $ePoint  = escSql($b['point']);
    $eNotes  = escSql($b['notes']);
    $eStatus = escSql($b['status']);
    $eReason = $b['reason'] !== null ? "'" . escSql($b['reason']) . "'" : "NULL";
    $eCancAt = $b['cancelled_at'] !== null ? "'" . escSql($b['cancelled_at']) . "'" : "NULL";

    $sql[] = "INSERT INTO bookings (" .
             "booking_code, tourist_id, guide_id, destination_id, transport_id, " .
             "start_date, end_date, daily_rate_idr, number_of_guests, meeting_point, " .
             "notes, status, cancel_reason, cancelled_at, version" .
             ") VALUES (" .
             "'{$eCode}', " .
             "(SELECT user_id FROM users WHERE email = '{$eTour}'), " .
             "(SELECT guide_id FROM guides WHERE guide_code = '{$eGuide}'), " .
             "(SELECT destination_id FROM destinations WHERE destination_code = '{$eDest}'), " .
             "(SELECT transport_id FROM transports WHERE transport_code = '{$eTrans}'), " .
             "'{$start}', '{$end}', {$rate}, {$guests}, '{$ePoint}', " .
             "'{$eNotes}', '{$eStatus}', {$eReason}, {$eCancAt}, 1" .
             ") ON CONFLICT (booking_code) DO NOTHING;";
}
$sql[] = "";

// 11. reviews (untuk booking completed)
$sql[] = "-- ---------------------------------------------------------------------";
$sql[] = "-- 11. SEED TABEL reviews (ULASAN UNTUK BOOKING STATUS COMPLETED)";
$sql[] = "-- ---------------------------------------------------------------------";
$demoReviews = [
    [
        'booking_code' => 'BK-20261010-0001',
        'rating'       => 5,
        'review_text'  => 'Pelayanan Pak Horas sangat luar biasa! Sangat menguasai sejarah Kesawan dan Tjong A Fie Mansion. Ramah, tepat waktu, dan kendaraannya bersih prima.',
        'created_at'   => '2026-10-13 10:00:00+07',
    ],
];

foreach ($demoReviews as $rv) {
    $eBCode = escSql($rv['booking_code']);
    $rating = (int) $rv['rating'];
    $eText  = escSql($rv['review_text']);
    $eDate  = escSql($rv['created_at']);

    $sql[] = "INSERT INTO reviews (booking_id, rating, review_text, created_at) VALUES (" .
             "(SELECT booking_id FROM bookings WHERE booking_code = '{$eBCode}'), " .
             "{$rating}, '{$eText}', '{$eDate}'" .
             ") ON CONFLICT (booking_id) DO NOTHING;";
}
$sql[] = "";

// 12. audit_logs (3 catatan awal demo)
$sql[] = "-- ---------------------------------------------------------------------";
$sql[] = "-- 12. SEED TABEL audit_logs (3 CATATAN LOG AWAL)";
$sql[] = "-- ---------------------------------------------------------------------";
$demoLogs = [
    [
        'entity_type' => 'booking',
        'entity_subq' => "(SELECT booking_id FROM bookings WHERE booking_code = 'BK-20261010-0001')",
        'action'      => 'UPDATE',
        'old_status'  => 'confirmed',
        'new_status'  => 'completed',
        'old_data'    => '{"status": "confirmed", "booking_code": "BK-20261010-0001"}',
        'new_data'    => '{"status": "completed", "booking_code": "BK-20261010-0001"}',
        'user_email'  => 'admin@sumutgo.id',
        'changed_at'  => '2026-10-12 18:00:00+07',
    ],
    [
        'entity_type' => 'booking',
        'entity_subq' => "(SELECT booking_id FROM bookings WHERE booking_code = 'BK-20261105-0005')",
        'action'      => 'UPDATE',
        'old_status'  => 'pending',
        'new_status'  => 'cancelled',
        'old_data'    => '{"status": "pending", "cancel_reason": null}',
        'new_data'    => '{"status": "cancelled", "cancel_reason": "Perubahan jadwal mendadak oleh wisatawan"}',
        'user_email'  => 'budi@wisatawan.id',
        'changed_at'  => '2026-11-04 14:30:00+07',
    ],
    [
        'entity_type' => 'guide',
        'entity_subq' => "(SELECT guide_id FROM guides WHERE guide_code = 'TG-001')",
        'action'      => 'UPDATE',
        'old_status'  => 'Tersedia',
        'new_status'  => 'Tersedia',
        'old_data'    => '{"availability_status": "Tersedia", "version": 1}',
        'new_data'    => '{"availability_status": "Tersedia", "version": 1, "note": "Kemitraan agensi terverifikasi"}',
        'user_email'  => 'admin@sumutgo.id',
        'changed_at'  => '2026-11-01 09:00:00+07',
    ],
];

foreach ($demoLogs as $log) {
    $eType    = escSql($log['entity_type']);
    $eSubq    = $log['entity_subq'];
    $eAction  = escSql($log['action']);
    $eOldSt   = escSql($log['old_status']);
    $eNewSt   = escSql($log['new_status']);
    $eOldData = escSql($log['old_data']);
    $eNewData = escSql($log['new_data']);
    $eEmail   = escSql($log['user_email']);
    $eDate    = escSql($log['changed_at']);

    $sql[] = "INSERT INTO audit_logs (" .
             "entity_type, entity_id, action, old_status, new_status, old_data, new_data, changed_by, changed_at" .
             ") SELECT " .
             "'{$eType}', {$eSubq}, '{$eAction}', '{$eOldSt}', '{$eNewSt}', " .
             "'{$eOldData}'::jsonb, '{$eNewData}'::jsonb, " .
             "(SELECT user_id FROM users WHERE email = '{$eEmail}'), '{$eDate}' " .
             "WHERE NOT EXISTS (" .
             "  SELECT 1 FROM audit_logs WHERE entity_type = '{$eType}' AND entity_id = {$eSubq} AND action = '{$eAction}' AND changed_at = '{$eDate}'" .
             ");";
}

$sql[] = "";
$sql[] = "COMMIT;";
$sql[] = "";

$outputSqlFile = realpath(__DIR__ . '/../..') . '/database/sql/02_seed_data.sql';
file_put_contents($outputSqlFile, implode("\n", $sql));

echo "\nSukses membuat file seed: {$outputSqlFile}\n";
echo "Total baris SQL: " . count($sql) . "\n";
