<?php

declare(strict_types=1);

require 'vendor/autoload.php';

$conn = pg_connect("host=aws-0-ap-south-1.pooler.supabase.com port=6543 user=postgres.xffqsryvsdmfxnxwspjx password='meckypriscilla.gmail.com' dbname=postgres sslmode='require'");
if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

echo "====================================================================\n";
echo "SAMPEL 1: QUERY JOIN DETAIL TOUR GUIDE (TG-001) + ARRAY BAHASA\n";
echo "====================================================================\n";

$sqlGuide = <<<SQL
SELECT 
    g.guide_code,
    g.guide_name,
    g.agency_name,
    g.gmaps_rating,
    g.gmaps_review_count,
    g.basecamp_address,
    g.latitude,
    g.longitude,
    g.phone_number,
    g.daily_rate_idr,
    g.availability_status,
    g.version,
    u.email AS user_email,
    ts.specialization_name,
    d.destination_code || ' - ' || d.destination_name AS primary_destination,
    c.culinary_code || ' - ' || c.culinary_name AS recommended_culinary,
    h.hotel_code || ' - ' || h.hotel_name AS partner_hotel,
    t.transport_code || ' - ' || t.transport_name AS default_transport,
    COALESCE(ARRAY_TO_STRING(ARRAY_AGG(l.language_name ORDER BY l.language_name), ', '), '-') AS spoken_languages
FROM guides g
LEFT JOIN users u ON u.user_id = g.user_id
JOIN tour_specializations ts ON ts.specialization_id = g.specialization_id
JOIN destinations d ON d.destination_id = g.primary_destination_id
JOIN culinaries c ON c.culinary_id = g.recommended_culinary_id
JOIN hotels h ON h.hotel_id = g.partner_hotel_id
JOIN transports t ON t.transport_id = g.default_transport_id
LEFT JOIN guide_languages gl ON gl.guide_id = g.guide_id
LEFT JOIN languages l ON l.language_id = gl.language_id
WHERE g.guide_code = 'TG-001'
GROUP BY 
    g.guide_id, g.guide_code, g.guide_name, g.agency_name, g.gmaps_rating, g.gmaps_review_count,
    g.basecamp_address, g.latitude, g.longitude, g.phone_number, g.daily_rate_idr, g.availability_status,
    g.version, u.email, ts.specialization_name, d.destination_code, d.destination_name,
    c.culinary_code, c.culinary_name, h.hotel_code, h.hotel_name, t.transport_code, t.transport_name;
SQL;

$res1 = pg_query($conn, $sqlGuide);
$g1 = pg_fetch_assoc($res1);
print_r($g1);

echo "\n====================================================================\n";
echo "SAMPEL 2: QUERY JOIN TRANSAKSI BOOKING + STORED GENERATED COLUMNS + REVIEW\n";
echo "====================================================================\n";

$sqlBooking = <<<SQL
SELECT 
    b.booking_code,
    u.full_name AS tourist_name,
    u.email AS tourist_email,
    g.guide_code || ' - ' || g.guide_name AS guide,
    d.destination_name,
    t.transport_name,
    b.start_date,
    b.end_date,
    b.duration_days,
    b.daily_rate_idr,
    b.total_price_idr,
    b.status,
    b.meeting_point,
    r.rating AS review_rating,
    r.review_text
FROM bookings b
JOIN users u ON u.user_id = b.tourist_id
JOIN guides g ON g.guide_id = b.guide_id
JOIN destinations d ON d.destination_id = b.destination_id
JOIN transports t ON t.transport_id = b.transport_id
LEFT JOIN reviews r ON r.booking_id = b.booking_id
WHERE b.booking_code = 'BK-20261010-0001';
SQL;

$res2 = pg_query($conn, $sqlBooking);
$b1 = pg_fetch_assoc($res2);
print_r($b1);

echo "\n====================================================================\n";
echo "SAMPEL 3: QUERY JOIN AUDIT TRAIL LOG + USER PELAKU\n";
echo "====================================================================\n";

$sqlAudit = <<<SQL
SELECT 
    a.audit_id,
    a.entity_type,
    a.entity_id,
    a.action,
    a.old_status,
    a.new_status,
    a.old_data,
    a.new_data,
    u.full_name || ' (' || u.email || ')' AS changed_by_user,
    a.changed_at
FROM audit_logs a
LEFT JOIN users u ON u.user_id = a.changed_by
ORDER BY a.audit_id ASC
LIMIT 3;
SQL;

$res3 = pg_query($conn, $sqlAudit);
while ($a = pg_fetch_assoc($res3)) {
    print_r($a);
}

pg_close($conn);
