-- =====================================================================
-- SUMUT GO - MEDAN & NORTH SUMATRA TOUR GUIDE ECOSYSTEM
-- File: 05_guides_active_booking_end_date.sql
-- Fitur:
--   1. Menambahkan kolom active_booking_end_date pada view vw_guides_detail
--      untuk menampilkan informasi "Bertugas/Booked s.d. tanggal ..." secara real-time
--      dari transaksi bookings aktif ('ongoing', 'confirmed', 'pending').
--   2. Memastikan fn_create_booking & fn_update_booking_status langsung
--      menyinkronkan guides.availability_status saat booking dibuat/diubah/dibatalkan/selesai.
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
        COALESCE(
            MAX(end_date) FILTER (WHERE status = 'ongoing'),
            MAX(end_date) FILTER (WHERE status IN ('confirmed', 'pending'))
        ) AS active_booking_end_date
    FROM public.bookings
    WHERE status IN ('pending', 'confirmed', 'ongoing')
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

CREATE OR REPLACE FUNCTION public.fn_create_booking(
    p_tourist_id BIGINT,
    p_guide_id INT,
    p_start_date DATE,
    p_end_date DATE,
    p_number_of_guests SMALLINT DEFAULT 1,
    p_destination_id INT DEFAULT NULL,
    p_transport_id INT DEFAULT NULL,
    p_meeting_point VARCHAR DEFAULT NULL,
    p_notes VARCHAR DEFAULT NULL
)
RETURNS public.bookings AS $$
DECLARE
    v_tourist_role VARCHAR(20);
    v_tourist_active BOOLEAN;
    v_guide RECORD;
    v_dest_id INT;
    v_trans_id INT;
    v_meet_point TEXT;
    v_booking_code VARCHAR(20);
    v_new_booking public.bookings;
BEGIN
    SELECT role, is_active INTO v_tourist_role, v_tourist_active
    FROM public.users WHERE user_id = p_tourist_id;

    IF NOT FOUND THEN
        RAISE EXCEPTION USING ERRCODE = 'P0002', MESSAGE = 'Tourist user ID tidak ditemukan.';
    END IF;

    IF NOT v_tourist_active THEN
        RAISE EXCEPTION USING ERRCODE = 'P0003', MESSAGE = 'Akun wisatawan berstatus non-aktif.';
    END IF;

    IF v_tourist_role NOT IN ('wisatawan', 'admin') THEN
        RAISE EXCEPTION USING ERRCODE = 'P0004', MESSAGE = 'Hanya user dengan role wisatawan atau admin yang dapat membuat booking.';
    END IF;

    IF p_end_date < p_start_date THEN
        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Tanggal selesai (end_date) tidak boleh lebih awal dari tanggal mulai (start_date).';
    END IF;

    SELECT 
        guide_id, daily_rate_idr, primary_destination_id, default_transport_id, basecamp_address, availability_status
    INTO v_guide
    FROM public.guides
    WHERE guide_id = p_guide_id
    FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION USING ERRCODE = 'P0005', MESSAGE = 'Tour guide tidak ditemukan.';
    END IF;

    v_dest_id := COALESCE(p_destination_id, v_guide.primary_destination_id);
    v_trans_id := COALESCE(p_transport_id, v_guide.default_transport_id);
    v_meet_point := COALESCE(NULLIF(TRIM(p_meeting_point), ''), v_guide.basecamp_address);

    PERFORM set_config('app.current_user_id', p_tourist_id::text, true);

    v_booking_code := 'BK-' || TO_CHAR(CURRENT_DATE, 'YYYYMMDD') || '-' || LPAD(NEXTVAL('public.seq_booking_code')::text, 4, '0');

    INSERT INTO public.bookings (
        booking_code, tourist_id, guide_id, destination_id, transport_id,
        start_date, end_date, daily_rate_idr, number_of_guests, meeting_point,
        notes, status, version
    ) VALUES (
        v_booking_code, p_tourist_id, p_guide_id, v_dest_id, v_trans_id,
        p_start_date, p_end_date, v_guide.daily_rate_idr, COALESCE(p_number_of_guests, 1::smallint),
        v_meet_point, p_notes, 'pending', 1
    )
    RETURNING * INTO v_new_booking;

    -- Sinkronkan status pemandu menjadi Booked bila sebelumnya Tersedia
    IF v_guide.availability_status = 'Tersedia' THEN
        UPDATE public.guides SET availability_status = 'Booked', version = version + 1 WHERE guide_id = p_guide_id;
    END IF;

    RETURN v_new_booking;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public;

CREATE OR REPLACE FUNCTION public.fn_update_booking_status(
    p_booking_id BIGINT,
    p_new_status VARCHAR,
    p_expected_version INT,
    p_changed_by BIGINT,
    p_cancel_reason VARCHAR DEFAULT NULL
)
RETURNS public.bookings AS $$
DECLARE
    v_booking RECORD;
    v_updated public.bookings;
BEGIN
    SELECT * INTO v_booking FROM public.bookings WHERE booking_id = p_booking_id FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION USING ERRCODE = 'P0002', MESSAGE = 'Booking dengan ID tersebut tidak ditemukan.';
    END IF;

    IF v_booking.version != p_expected_version THEN
        RAISE EXCEPTION USING ERRCODE = '40001', MESSAGE = 'OPTIMISTIC_LOCK_CONFLICT';
    END IF;

    IF v_booking.status IN ('completed', 'cancelled') THEN
        RAISE EXCEPTION USING ERRCODE = 'P0007', MESSAGE = 'Booking dengan status ' || v_booking.status || ' bersifat final dan tidak dapat diubah lagi.';
    END IF;

    IF v_booking.status = 'pending' AND p_new_status NOT IN ('confirmed', 'cancelled') THEN
        RAISE EXCEPTION USING ERRCODE = 'P0006', MESSAGE = 'Transisi status tidak valid: pending hanya dapat berubah menjadi confirmed atau cancelled.';
    ELSIF v_booking.status = 'confirmed' AND p_new_status NOT IN ('ongoing', 'cancelled') THEN
        RAISE EXCEPTION USING ERRCODE = 'P0006', MESSAGE = 'Transisi status tidak valid: confirmed hanya dapat berubah menjadi ongoing atau cancelled.';
    ELSIF v_booking.status = 'ongoing' AND p_new_status NOT IN ('completed', 'cancelled') THEN
        RAISE EXCEPTION USING ERRCODE = 'P0006', MESSAGE = 'Transisi status tidak valid: ongoing hanya dapat berubah menjadi completed atau cancelled.';
    END IF;

    PERFORM set_config('app.current_user_id', p_changed_by::text, true);

    UPDATE public.bookings 
    SET 
        status = p_new_status,
        version = version + 1,
        cancel_reason = CASE WHEN p_new_status = 'cancelled' THEN COALESCE(p_cancel_reason, 'Dibatalkan oleh pengguna/sistem') ELSE cancel_reason END,
        cancelled_at = CASE WHEN p_new_status = 'cancelled' THEN CURRENT_TIMESTAMP ELSE cancelled_at END
    WHERE booking_id = p_booking_id
    RETURNING * INTO v_updated;

    IF p_new_status = 'ongoing' THEN
        UPDATE public.guides
        SET availability_status = 'Sedang Bertugas', version = version + 1
        WHERE guide_id = v_booking.guide_id AND availability_status != 'Sedang Bertugas';
    ELSIF p_new_status = 'confirmed' THEN
        UPDATE public.guides
        SET availability_status = 'Booked', version = version + 1
        WHERE guide_id = v_booking.guide_id AND availability_status = 'Tersedia';
    ELSIF p_new_status IN ('completed', 'cancelled') THEN
        IF EXISTS (
            SELECT 1 FROM public.bookings 
            WHERE guide_id = v_booking.guide_id 
              AND booking_id != p_booking_id 
              AND status = 'ongoing'
        ) THEN
            UPDATE public.guides
            SET availability_status = 'Sedang Bertugas', version = version + 1
            WHERE guide_id = v_booking.guide_id AND availability_status != 'Sedang Bertugas';
        ELSIF EXISTS (
            SELECT 1 FROM public.bookings 
            WHERE guide_id = v_booking.guide_id 
              AND booking_id != p_booking_id 
              AND status IN ('confirmed', 'pending')
        ) THEN
            UPDATE public.guides
            SET availability_status = 'Booked', version = version + 1
            WHERE guide_id = v_booking.guide_id AND availability_status != 'Booked';
        ELSE
            UPDATE public.guides
            SET availability_status = 'Tersedia', version = version + 1
            WHERE guide_id = v_booking.guide_id AND availability_status != 'Tersedia';
        END IF;
    END IF;

    RETURN v_updated;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public;

GRANT EXECUTE ON FUNCTION public.fn_create_booking, public.fn_update_booking_status TO role_sumutgo_admin, role_sumutgo_mobile;

-- Sinkronkan status guide yang memiliki booking aktif (ongoing/confirmed/pending)
UPDATE public.guides g
SET availability_status = CASE
        WHEN EXISTS (SELECT 1 FROM public.bookings b WHERE b.guide_id = g.guide_id AND b.status = 'ongoing') THEN 'Sedang Bertugas'
        ELSE 'Booked'
    END,
    version = g.version + 1
WHERE EXISTS (
    SELECT 1 FROM public.bookings b
    WHERE b.guide_id = g.guide_id AND b.status IN ('pending', 'confirmed', 'ongoing')
)
AND g.availability_status != CASE
    WHEN EXISTS (SELECT 1 FROM public.bookings b WHERE b.guide_id = g.guide_id AND b.status = 'ongoing') THEN 'Sedang Bertugas'
    ELSE 'Booked'
END;

COMMIT;
