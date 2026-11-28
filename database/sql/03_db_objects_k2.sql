-- =====================================================================
-- SUMUT GO - MEDAN & NORTH SUMATRA TOUR GUIDE ECOSYSTEM
-- File: 03_db_objects_k2.sql (Kriteria K2 Objek Database Server)
-- Views, Stored Functions, Business Triggers, Audit, Roles & RLS
-- =====================================================================

-- ---------------------------------------------------------------------
-- 0. SEQUENCE GENERATOR UNTUK BOOKING CODE
-- ---------------------------------------------------------------------
CREATE SEQUENCE IF NOT EXISTS seq_booking_code START WITH 1000 INCREMENT BY 1;

-- ---------------------------------------------------------------------
-- 1. VIEWS UNTUK ENDPOINT API TAHAP 4
-- ---------------------------------------------------------------------

-- View 1: vw_guides_detail
-- Menggabungkan data guides lengkap, master lookup, agregasi bahasa (array, json, teks), dan rating review internal
CREATE OR REPLACE VIEW vw_guides_detail AS
WITH guide_langs AS (
    SELECT 
        gl.guide_id,
        ARRAY_AGG(l.language_id ORDER BY l.language_id) AS language_ids,
        JSONB_AGG(jsonb_build_object('language_id', l.language_id, 'language_name', l.language_name) ORDER BY l.language_name) AS languages_json,
        STRING_AGG(l.language_name, ', ' ORDER BY l.language_name) AS spoken_languages
    FROM guide_languages gl
    JOIN languages l ON l.language_id = gl.language_id
    GROUP BY gl.guide_id
),
guide_revs AS (
    SELECT 
        b.guide_id,
        ROUND(AVG(r.rating)::numeric, 2) AS internal_avg_rating,
        COUNT(r.review_id)::int AS internal_review_count
    FROM bookings b
    JOIN reviews r ON r.booking_id = b.booking_id
    GROUP BY b.guide_id
),
active_bookings AS (
    SELECT
        guide_id,
        COALESCE(
            MAX(end_date) FILTER (WHERE status = 'ongoing'),
            MAX(end_date) FILTER (WHERE status IN ('confirmed', 'pending'))
        ) AS active_booking_end_date
    FROM bookings
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
FROM guides g
LEFT JOIN users u ON u.user_id = g.user_id
LEFT JOIN tour_specializations ts ON ts.specialization_id = g.specialization_id
LEFT JOIN destinations d ON d.destination_id = g.primary_destination_id
LEFT JOIN culinaries c ON c.culinary_id = g.recommended_culinary_id
LEFT JOIN hotels h ON h.hotel_id = g.partner_hotel_id
LEFT JOIN transports t ON t.transport_id = g.default_transport_id
LEFT JOIN guide_langs gl ON gl.guide_id = g.guide_id
LEFT JOIN guide_revs gr ON gr.guide_id = g.guide_id
LEFT JOIN active_bookings ab ON ab.guide_id = g.guide_id;

-- View 2: vw_bookings_detail
-- Menggabungkan data bookings dengan pemesan, pemandu, destinasi, transportasi, durasi & total harga, dan review
CREATE OR REPLACE VIEW vw_bookings_detail AS
SELECT 
    b.booking_id,
    b.booking_code,
    b.tourist_id,
    u.full_name AS tourist_name,
    u.email AS tourist_email,
    b.guide_id,
    g.guide_code,
    g.guide_name,
    g.agency_name,
    g.phone_number AS guide_phone,
    b.destination_id,
    d.destination_code,
    d.destination_name,
    b.transport_id,
    t.transport_code,
    t.transport_name,
    b.start_date,
    b.end_date,
    b.duration_days,
    b.daily_rate_idr,
    b.total_price_idr,
    b.number_of_guests,
    b.meeting_point,
    b.notes,
    b.status,
    b.cancel_reason,
    b.cancelled_at,
    b.version,
    r.review_id,
    r.rating AS review_rating,
    r.review_text,
    r.created_at AS review_created_at,
    u.phone_number AS tourist_phone,
    u.is_verified AS tourist_verified,
    COALESCE(bc_agg.contact_count, 0)::int AS contact_count,
    bc_latest.confirmation_status AS latest_contact_status,
    bc_latest.contact_phone AS latest_contact_phone,
    bc_latest.confirmed_at AS latest_contact_confirmed_at
FROM bookings b
JOIN users u ON u.user_id = b.tourist_id
JOIN guides g ON g.guide_id = b.guide_id
JOIN destinations d ON d.destination_id = b.destination_id
JOIN transports t ON t.transport_id = b.transport_id
LEFT JOIN reviews r ON r.booking_id = b.booking_id
LEFT JOIN LATERAL (
    SELECT COUNT(*) AS contact_count
    FROM booking_contacts bc
    WHERE bc.booking_id = b.booking_id
) bc_agg ON TRUE
LEFT JOIN LATERAL (
    SELECT bc.confirmation_status, bc.contact_phone, bc.confirmed_at
    FROM booking_contacts bc
    WHERE bc.booking_id = b.booking_id
    ORDER BY bc.contact_id DESC
    LIMIT 1
) bc_latest ON TRUE;

-- ---------------------------------------------------------------------
-- 2. STORED FUNCTIONS TRANSAKSIONAL / ACID
-- ---------------------------------------------------------------------

-- Function 1: fn_create_booking
-- Membuat booking transaksional dengan validasi, locking FOR UPDATE, auto-pricing, auto booking code, dan trigger audit
CREATE OR REPLACE FUNCTION fn_create_booking(
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
RETURNS bookings AS $$
DECLARE
    v_tourist_role VARCHAR(20);
    v_tourist_active BOOLEAN;
    v_guide RECORD;
    v_dest_id INT;
    v_trans_id INT;
    v_meet_point TEXT;
    v_booking_code VARCHAR(20);
    v_new_booking bookings;
BEGIN
    -- 1. Validasi tourist
    SELECT role, is_active INTO v_tourist_role, v_tourist_active
    FROM users WHERE user_id = p_tourist_id;

    IF NOT FOUND THEN
        RAISE EXCEPTION USING ERRCODE = 'P0002', MESSAGE = 'Tourist user ID tidak ditemukan.';
    END IF;

    IF NOT v_tourist_active THEN
        RAISE EXCEPTION USING ERRCODE = 'P0003', MESSAGE = 'Akun wisatawan berstatus non-aktif.';
    END IF;

    IF v_tourist_role NOT IN ('wisatawan', 'admin') THEN
        RAISE EXCEPTION USING ERRCODE = 'P0004', MESSAGE = 'Hanya user dengan role wisatawan atau admin yang dapat membuat booking.';
    END IF;

    -- 2. Validasi rentang tanggal
    IF p_end_date < p_start_date THEN
        RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'Tanggal selesai (end_date) tidak boleh lebih awal dari tanggal mulai (start_date).';
    END IF;

    -- 3. Kunci baris guide dengan FOR UPDATE untuk mencegah race condition
    SELECT 
        guide_id, daily_rate_idr, primary_destination_id, default_transport_id, basecamp_address, availability_status
    INTO v_guide
    FROM guides
    WHERE guide_id = p_guide_id
    FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION USING ERRCODE = 'P0005', MESSAGE = 'Tour guide tidak ditemukan.';
    END IF;

    -- 4. Fallback nilai default destinasi, transportasi, meeting point
    v_dest_id := COALESCE(p_destination_id, v_guide.primary_destination_id);
    v_trans_id := COALESCE(p_transport_id, v_guide.default_transport_id);
    v_meet_point := COALESCE(NULLIF(TRIM(p_meeting_point), ''), v_guide.basecamp_address);

    -- 5. Set transaction-local context untuk audit trigger
    PERFORM set_config('app.current_user_id', p_tourist_id::text, true);

    -- 6. Generate booking code unik aman terhadap konkurensi: BK-YYYYMMDD-XXXX
    v_booking_code := 'BK-' || TO_CHAR(CURRENT_DATE, 'YYYYMMDD') || '-' || LPAD(NEXTVAL('seq_booking_code')::text, 4, '0');

    -- 7. Insert ke bookings (duration_days dan total_price_idr adalah GENERATED ALWAYS STORED)
    -- Exclude constraint ex_bookings_guide_no_overlap akan otomatis melempar SQLSTATE 23P01 jika jadwal bentrok
    INSERT INTO bookings (
        booking_code, tourist_id, guide_id, destination_id, transport_id,
        start_date, end_date, daily_rate_idr, number_of_guests, meeting_point,
        notes, status, version
    ) VALUES (
        v_booking_code, p_tourist_id, p_guide_id, v_dest_id, v_trans_id,
        p_start_date, p_end_date, v_guide.daily_rate_idr, COALESCE(p_number_of_guests, 1::smallint),
        v_meet_point, p_notes, 'pending', 1
    )
    RETURNING * INTO v_new_booking;

    -- 8. Sinkronkan status ketersediaan pemandu menjadi Booked bila sebelumnya Tersedia
    IF v_guide.availability_status = 'Tersedia' THEN
        UPDATE guides SET availability_status = 'Booked', version = version + 1 WHERE guide_id = p_guide_id;
    END IF;

    RETURN v_new_booking;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public;

-- Function 2: fn_update_booking_status
-- Memperbarui status booking dengan validasi transisi, Optimistic Locking, sinkronisasi guide status, dan auto audit
CREATE OR REPLACE FUNCTION fn_update_booking_status(
    p_booking_id BIGINT,
    p_new_status VARCHAR,
    p_expected_version INT,
    p_changed_by BIGINT,
    p_cancel_reason VARCHAR DEFAULT NULL
)
RETURNS bookings AS $$
DECLARE
    v_booking RECORD;
    v_updated bookings;
BEGIN
    -- 1. Kunci baris booking untuk memeriksa keberadaan & version
    SELECT * INTO v_booking FROM bookings WHERE booking_id = p_booking_id FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION USING ERRCODE = 'P0002', MESSAGE = 'Booking dengan ID tersebut tidak ditemukan.';
    END IF;

    -- 2. Optimistic Locking Check
    IF v_booking.version != p_expected_version THEN
        RAISE EXCEPTION USING ERRCODE = '40001', MESSAGE = 'OPTIMISTIC_LOCK_CONFLICT';
    END IF;

    -- 3. Validasi status final
    IF v_booking.status IN ('completed', 'cancelled') THEN
        RAISE EXCEPTION USING ERRCODE = 'P0007', MESSAGE = 'Booking dengan status ' || v_booking.status || ' bersifat final dan tidak dapat diubah lagi.';
    END IF;

    -- 4. Validasi transisi status yang sah
    IF v_booking.status = 'pending' AND p_new_status NOT IN ('confirmed', 'cancelled') THEN
        RAISE EXCEPTION USING ERRCODE = 'P0006', MESSAGE = 'Transisi status tidak valid: pending hanya dapat berubah menjadi confirmed atau cancelled.';
    ELSIF v_booking.status = 'confirmed' AND p_new_status NOT IN ('ongoing', 'cancelled') THEN
        RAISE EXCEPTION USING ERRCODE = 'P0006', MESSAGE = 'Transisi status tidak valid: confirmed hanya dapat berubah menjadi ongoing atau cancelled.';
    ELSIF v_booking.status = 'ongoing' AND p_new_status NOT IN ('completed', 'cancelled') THEN
        RAISE EXCEPTION USING ERRCODE = 'P0006', MESSAGE = 'Transisi status tidak valid: ongoing hanya dapat berubah menjadi completed atau cancelled.';
    END IF;

    -- 5. Set transaction-local context untuk audit trigger
    PERFORM set_config('app.current_user_id', p_changed_by::text, true);

    -- 6. Update booking dengan kenaikan version
    UPDATE bookings 
    SET 
        status = p_new_status,
        version = version + 1,
        cancel_reason = CASE WHEN p_new_status = 'cancelled' THEN COALESCE(p_cancel_reason, 'Dibatalkan oleh pengguna/sistem') ELSE cancel_reason END,
        cancelled_at = CASE WHEN p_new_status = 'cancelled' THEN CURRENT_TIMESTAMP ELSE cancelled_at END
    WHERE booking_id = p_booking_id
    RETURNING * INTO v_updated;

    -- 7. Sinkronkan status ketersediaan guide
    IF p_new_status = 'ongoing' THEN
        UPDATE guides SET availability_status = 'Sedang Bertugas', version = version + 1
        WHERE guide_id = v_booking.guide_id AND availability_status != 'Sedang Bertugas';
    ELSIF p_new_status = 'confirmed' THEN
        UPDATE guides SET availability_status = 'Booked', version = version + 1
        WHERE guide_id = v_booking.guide_id AND availability_status = 'Tersedia';
    ELSIF p_new_status IN ('completed', 'cancelled') THEN
        -- Cek prioritas booking aktif lainnya: ongoing -> Sedang Bertugas, confirmed/pending -> Booked, selain itu -> Tersedia
        IF EXISTS (
            SELECT 1 FROM bookings 
            WHERE guide_id = v_booking.guide_id 
              AND booking_id != p_booking_id 
              AND status = 'ongoing'
        ) THEN
            UPDATE guides SET availability_status = 'Sedang Bertugas', version = version + 1
            WHERE guide_id = v_booking.guide_id AND availability_status != 'Sedang Bertugas';
        ELSIF EXISTS (
            SELECT 1 FROM bookings 
            WHERE guide_id = v_booking.guide_id 
              AND booking_id != p_booking_id 
              AND status IN ('confirmed', 'pending')
        ) THEN
            UPDATE guides SET availability_status = 'Booked', version = version + 1
            WHERE guide_id = v_booking.guide_id AND availability_status != 'Booked';
        ELSE
            UPDATE guides SET availability_status = 'Tersedia', version = version + 1
            WHERE guide_id = v_booking.guide_id AND availability_status != 'Tersedia';
        END IF;
    END IF;

    RETURN v_updated;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public;

-- ---------------------------------------------------------------------
-- 3. TRIGGERS BISNIS & AUDIT OTOMATIS
-- ---------------------------------------------------------------------

-- Trigger 1: trg_bookings_audit_log (SECURITY DEFINER)
CREATE OR REPLACE FUNCTION fn_trg_bookings_audit()
RETURNS TRIGGER
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
    v_user_id BIGINT;
    v_old_status VARCHAR(20) := NULL;
    v_new_status VARCHAR(20) := NULL;
    v_old_data JSONB := NULL;
    v_new_data JSONB := NULL;
    v_entity_id BIGINT;
BEGIN
    BEGIN
        v_user_id := NULLIF(current_setting('app.current_user_id', true), '')::BIGINT;
    EXCEPTION WHEN OTHERS THEN
        v_user_id := NULL;
    END;

    IF TG_OP = 'INSERT' THEN
        v_entity_id := NEW.booking_id;
        v_new_status := NEW.status;
        v_new_data := to_jsonb(NEW);
    ELSIF TG_OP = 'UPDATE' THEN
        v_entity_id := NEW.booking_id;
        v_old_status := OLD.status;
        v_new_status := NEW.status;
        v_old_data := to_jsonb(OLD);
        v_new_data := to_jsonb(NEW);
    ELSIF TG_OP = 'DELETE' THEN
        v_entity_id := OLD.booking_id;
        v_old_status := OLD.status;
        v_old_data := to_jsonb(OLD);
    END IF;

    INSERT INTO audit_logs (
        entity_type, entity_id, action, old_status, new_status, old_data, new_data, changed_by, changed_at
    ) VALUES (
        'booking', v_entity_id, TG_OP, v_old_status, v_new_status, v_old_data, v_new_data, v_user_id, CURRENT_TIMESTAMP
    );

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_bookings_audit_log ON bookings;
CREATE TRIGGER trg_bookings_audit_log
AFTER INSERT OR UPDATE OR DELETE ON bookings
FOR EACH ROW
EXECUTE FUNCTION fn_trg_bookings_audit();

-- Trigger 2: trg_guides_audit_log (SECURITY DEFINER)
CREATE OR REPLACE FUNCTION fn_trg_guides_audit()
RETURNS TRIGGER
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
    v_user_id BIGINT;
    v_old_status VARCHAR(20) := NULL;
    v_new_status VARCHAR(20) := NULL;
    v_old_data JSONB := NULL;
    v_new_data JSONB := NULL;
    v_entity_id BIGINT;
BEGIN
    BEGIN
        v_user_id := NULLIF(current_setting('app.current_user_id', true), '')::BIGINT;
    EXCEPTION WHEN OTHERS THEN
        v_user_id := NULL;
    END;

    IF TG_OP = 'INSERT' THEN
        v_entity_id := NEW.guide_id;
        v_new_status := NEW.availability_status;
        v_new_data := to_jsonb(NEW);
    ELSIF TG_OP = 'UPDATE' THEN
        v_entity_id := NEW.guide_id;
        v_old_status := OLD.availability_status;
        v_new_status := NEW.availability_status;
        v_old_data := to_jsonb(OLD);
        v_new_data := to_jsonb(NEW);
    ELSIF TG_OP = 'DELETE' THEN
        v_entity_id := OLD.guide_id;
        v_old_status := OLD.availability_status;
        v_old_data := to_jsonb(OLD);
    END IF;

    INSERT INTO audit_logs (
        entity_type, entity_id, action, old_status, new_status, old_data, new_data, changed_by, changed_at
    ) VALUES (
        'guide', v_entity_id, TG_OP, v_old_status, v_new_status, v_old_data, v_new_data, v_user_id, CURRENT_TIMESTAMP
    );

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_guides_audit_log ON guides;
CREATE TRIGGER trg_guides_audit_log
AFTER INSERT OR UPDATE OR DELETE ON guides
FOR EACH ROW
EXECUTE FUNCTION fn_trg_guides_audit();

-- Trigger 3: trg_reviews_only_completed (BEFORE INSERT ON reviews)
CREATE OR REPLACE FUNCTION fn_trg_reviews_only_completed()
RETURNS TRIGGER AS $$
DECLARE
    v_status VARCHAR(20);
BEGIN
    SELECT status INTO v_status FROM bookings WHERE booking_id = NEW.booking_id;
    IF NOT FOUND THEN
        RAISE EXCEPTION USING ERRCODE = 'P0002', MESSAGE = 'Booking dengan ID tersebut tidak ditemukan.';
    END IF;

    IF v_status != 'completed' THEN
        RAISE EXCEPTION USING ERRCODE = 'P0001', MESSAGE = 'Ulasan review hanya dapat diberikan untuk booking yang berstatus completed.';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_reviews_only_completed ON reviews;
CREATE TRIGGER trg_reviews_only_completed
BEFORE INSERT ON reviews
FOR EACH ROW
EXECUTE FUNCTION fn_trg_reviews_only_completed();

-- ---------------------------------------------------------------------
-- 4. ROLES LEAST PRIVILEGE & RLS POLICIES
-- ---------------------------------------------------------------------

DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'role_sumutgo_admin') THEN
        CREATE ROLE role_sumutgo_admin WITH LOGIN PASSWORD 'SumutGoAdmin2024!';
    END IF;
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'role_sumutgo_mobile') THEN
        CREATE ROLE role_sumutgo_mobile WITH LOGIN PASSWORD 'SumutGoMobile2024!';
    END IF;
END
$$;

-- Izinkan user postgres berpindah role untuk testing / pooling
GRANT role_sumutgo_admin TO postgres;
GRANT role_sumutgo_mobile TO postgres;

-- Izin Skema
GRANT USAGE ON SCHEMA public TO role_sumutgo_admin;
GRANT USAGE ON SCHEMA public TO role_sumutgo_mobile;

-- Izin role_sumutgo_admin: Full CRUD tabel bisnis & master, Read-only pada audit_logs & reviews
GRANT SELECT, INSERT, UPDATE, DELETE ON users, tour_specializations, languages, destinations, culinaries, hotels, transports, guides, guide_languages, bookings TO role_sumutgo_admin;
GRANT SELECT ON audit_logs, reviews, vw_guides_detail, vw_bookings_detail TO role_sumutgo_admin;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO role_sumutgo_admin;
GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA public TO role_sumutgo_admin;

REVOKE INSERT, UPDATE, DELETE, TRUNCATE ON audit_logs FROM role_sumutgo_admin;
REVOKE INSERT, UPDATE, DELETE, TRUNCATE ON reviews FROM role_sumutgo_admin;
GRANT SELECT ON reviews TO role_sumutgo_admin;

-- Izin role_sumutgo_mobile: Read-only master & guides, Limited CRUD bookings, users, reviews
GRANT SELECT ON tour_specializations, languages, destinations, culinaries, hotels, transports, guides, guide_languages, vw_guides_detail, vw_bookings_detail TO role_sumutgo_mobile;
GRANT SELECT, INSERT, UPDATE ON users, bookings, reviews TO role_sumutgo_mobile;
REVOKE DELETE, TRUNCATE ON ALL TABLES IN SCHEMA public FROM role_sumutgo_mobile;
REVOKE ALL ON audit_logs FROM role_sumutgo_mobile;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO role_sumutgo_mobile;
GRANT EXECUTE ON FUNCTION fn_create_booking, fn_update_booking_status TO role_sumutgo_mobile;

-- ---------------------------------------------------------------------
-- 5. RLS POLICIES UNTUK ROLE APLIKASI
-- ---------------------------------------------------------------------

-- Policies role_sumutgo_admin
DROP POLICY IF EXISTS rls_admin_users ON users;
CREATE POLICY rls_admin_users ON users TO role_sumutgo_admin USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS rls_admin_specs ON tour_specializations;
CREATE POLICY rls_admin_specs ON tour_specializations TO role_sumutgo_admin USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS rls_admin_langs ON languages;
CREATE POLICY rls_admin_langs ON languages TO role_sumutgo_admin USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS rls_admin_dests ON destinations;
CREATE POLICY rls_admin_dests ON destinations TO role_sumutgo_admin USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS rls_admin_culs ON culinaries;
CREATE POLICY rls_admin_culs ON culinaries TO role_sumutgo_admin USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS rls_admin_hotels ON hotels;
CREATE POLICY rls_admin_hotels ON hotels TO role_sumutgo_admin USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS rls_admin_trans ON transports;
CREATE POLICY rls_admin_trans ON transports TO role_sumutgo_admin USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS rls_admin_guides ON guides;
CREATE POLICY rls_admin_guides ON guides TO role_sumutgo_admin USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS rls_admin_guide_langs ON guide_languages;
CREATE POLICY rls_admin_guide_langs ON guide_languages TO role_sumutgo_admin USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS rls_admin_bookings ON bookings;
CREATE POLICY rls_admin_bookings ON bookings TO role_sumutgo_admin USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS rls_admin_reviews ON reviews;
CREATE POLICY rls_admin_reviews ON reviews FOR SELECT TO role_sumutgo_admin USING (true);

DROP POLICY IF EXISTS rls_admin_audit ON audit_logs;
CREATE POLICY rls_admin_audit ON audit_logs FOR SELECT TO role_sumutgo_admin USING (true);

-- Policies role_sumutgo_mobile
DROP POLICY IF EXISTS rls_mobile_specs ON tour_specializations;
CREATE POLICY rls_mobile_specs ON tour_specializations FOR SELECT TO role_sumutgo_mobile USING (true);

DROP POLICY IF EXISTS rls_mobile_langs ON languages;
CREATE POLICY rls_mobile_langs ON languages FOR SELECT TO role_sumutgo_mobile USING (true);

DROP POLICY IF EXISTS rls_mobile_dests ON destinations;
CREATE POLICY rls_mobile_dests ON destinations FOR SELECT TO role_sumutgo_mobile USING (true);

DROP POLICY IF EXISTS rls_mobile_culs ON culinaries;
CREATE POLICY rls_mobile_culs ON culinaries FOR SELECT TO role_sumutgo_mobile USING (true);

DROP POLICY IF EXISTS rls_mobile_hotels ON hotels;
CREATE POLICY rls_mobile_hotels ON hotels FOR SELECT TO role_sumutgo_mobile USING (true);

DROP POLICY IF EXISTS rls_mobile_trans ON transports;
CREATE POLICY rls_mobile_trans ON transports FOR SELECT TO role_sumutgo_mobile USING (true);

DROP POLICY IF EXISTS rls_mobile_guides ON guides;
CREATE POLICY rls_mobile_guides ON guides FOR SELECT TO role_sumutgo_mobile USING (true);

DROP POLICY IF EXISTS rls_mobile_guide_langs ON guide_languages;
CREATE POLICY rls_mobile_guide_langs ON guide_languages FOR SELECT TO role_sumutgo_mobile USING (true);

DROP POLICY IF EXISTS rls_mobile_users ON users;
CREATE POLICY rls_mobile_users ON users FOR ALL TO role_sumutgo_mobile USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS rls_mobile_bookings ON bookings;
CREATE POLICY rls_mobile_bookings ON bookings FOR ALL TO role_sumutgo_mobile USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS rls_mobile_reviews ON reviews;
CREATE POLICY rls_mobile_reviews ON reviews FOR ALL TO role_sumutgo_mobile USING (true) WITH CHECK (true);
