-- =====================================================================
-- SUMUT GO - MEDAN & NORTH SUMATRA TOUR GUIDE ECOSYSTEM
-- File: 05_guides_active_booking_end_date.sql
-- Fitur: Menambahkan kolom active_booking_end_date pada view vw_guides_detail
--        untuk menampilkan informasi "Bertugas s.d. tanggal ..." secara real-time
--        dari transaksi bookings berstatus 'confirmed' atau 'ongoing'.
-- Sifat: Idempoten & aman dijalankan ulang tanpa mengganggu vw_bookings_detail.
-- =====================================================================

BEGIN;

CREATE OR REPLACE VIEW public.vw_guides_detail AS
WITH guide_langs AS (
    SELECT 
        gl.guide_id,
        ARRAY_AGG(l.language_id ORDER BY l.language_id) AS language_ids,
        JSONB_AGG(jsonb_build_object('language_id', l.language_id, 'language_name', l.language_name) ORDER BY l.language_name) AS languages_json,
        STRING_AGG(l.language_name, ', ' ORDER BY l.language_name) AS spoken_languages
    FROM public.guide_languages gl
    JOIN public.languages l ON l.language_id = gl.language_id
    GROUP BY gl.guide_id
),
guide_revs AS (
    SELECT 
        b.guide_id,
        ROUND(AVG(r.rating)::numeric, 2) AS internal_avg_rating,
        COUNT(r.review_id)::int AS internal_review_count
    FROM public.bookings b
    JOIN public.reviews r ON r.booking_id = b.booking_id
    GROUP BY b.guide_id
),
active_bookings AS (
    SELECT
        guide_id,
        MAX(end_date) AS active_booking_end_date
    FROM public.bookings
    WHERE status IN ('confirmed', 'ongoing')
    GROUP BY guide_id
)
SELECT 
    g.guide_id,
    g.guide_code,
    g.user_id,
    u.email AS user_email,
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
    g.specialization_id,
    ts.specialization_name,
    g.primary_destination_id,
    d.destination_code,
    d.destination_name,
    g.recommended_culinary_id,
    c.culinary_code,
    c.culinary_name,
    g.partner_hotel_id,
    h.hotel_code,
    h.hotel_name,
    g.default_transport_id,
    t.transport_code,
    t.transport_name,
    COALESCE(gl.language_ids, '{}'::int[]) AS language_ids,
    COALESCE(gl.languages_json, '[]'::jsonb) AS languages_json,
    COALESCE(gl.spoken_languages, '') AS spoken_languages,
    COALESCE(gr.internal_avg_rating, 0.0) AS internal_avg_rating,
    COALESCE(gr.internal_review_count, 0) AS internal_review_count,
    ab.active_booking_end_date
FROM public.guides g
LEFT JOIN public.users u ON u.user_id = g.user_id
LEFT JOIN public.tour_specializations ts ON ts.specialization_id = g.specialization_id
LEFT JOIN public.destinations d ON d.destination_id = g.primary_destination_id
LEFT JOIN public.culinaries c ON c.culinary_id = g.recommended_culinary_id
LEFT JOIN public.hotels h ON h.hotel_id = g.partner_hotel_id
LEFT JOIN public.transports t ON t.transport_id = g.default_transport_id
LEFT JOIN guide_langs gl ON gl.guide_id = g.guide_id
LEFT JOIN guide_revs gr ON gr.guide_id = g.guide_id
LEFT JOIN active_bookings ab ON ab.guide_id = g.guide_id;

GRANT SELECT ON public.vw_guides_detail TO role_sumutgo_admin, role_sumutgo_mobile;

COMMIT;
