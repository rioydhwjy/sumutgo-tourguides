-- =====================================================================
-- SUMUT GO - MEDAN & NORTH SUMATRA TOUR GUIDE ECOSYSTEM
-- File: 04_contacts_and_confirmation.sql
-- Fitur: Nomor HP Pendaftar + Verifikasi Admin pada users,
--        Tabel booking_contacts (Komunikasi Pemesan <-> Pemandu/Pemboking & Konfirmasi Admin),
--        Audit Triggers, View vw_bookings_detail, Grants & RLS Policies
-- Sifat: Idempoten & aman dijalankan ulang
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- 1. EKSTENSI KOLOM NOMOR HP & VERIFIKASI ADMIN PADA TABEL users
-- ---------------------------------------------------------------------
ALTER TABLE public.users
    ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS is_verified BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS verified_at TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS verified_by INT NULL,
    ADD COLUMN IF NOT EXISTS verification_note TEXT NULL;

ALTER TABLE public.users DROP CONSTRAINT IF EXISTS chk_users_phone;
ALTER TABLE public.users
    ADD CONSTRAINT chk_users_phone
    CHECK (phone_number IS NULL OR phone_number ~ '^[1-9][0-9]{8,14}$');

ALTER TABLE public.users DROP CONSTRAINT IF EXISTS fk_users_verified_by;
ALTER TABLE public.users
    ADD CONSTRAINT fk_users_verified_by
    FOREIGN KEY (verified_by) REFERENCES public.users(user_id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_users_phone ON public.users(phone_number);
CREATE INDEX IF NOT EXISTS idx_users_verified ON public.users(is_verified);

-- Lengkapi nomor HP & status terverifikasi untuk akun demo bawaan (bila belum terisi)
UPDATE public.users
SET phone_number = CASE email
        WHEN 'admin@sumutgo.id' THEN '628116000001'
        WHEN 'budi@wisatawan.id' THEN '628126001001'
        WHEN 'siti@wisatawan.id' THEN '628126001002'
        WHEN 'horas@guide.id' THEN '628126543001'
        WHEN 'kelvin@guide.id' THEN '628126543002'
        ELSE phone_number
    END,
    is_verified = CASE
        WHEN email IN ('admin@sumutgo.id', 'budi@wisatawan.id', 'siti@wisatawan.id', 'horas@guide.id', 'kelvin@guide.id') THEN TRUE
        ELSE is_verified
    END,
    verified_at = CASE
        WHEN email IN ('admin@sumutgo.id', 'budi@wisatawan.id', 'siti@wisatawan.id', 'horas@guide.id', 'kelvin@guide.id') AND verified_at IS NULL THEN CURRENT_TIMESTAMP
        ELSE verified_at
    END
WHERE email IN ('admin@sumutgo.id', 'budi@wisatawan.id', 'siti@wisatawan.id', 'horas@guide.id', 'kelvin@guide.id')
  AND phone_number IS NULL;

-- ---------------------------------------------------------------------
-- 2. TABEL BARU: booking_contacts (Kontak & Komunikasi Pemesan <-> Pemboking/Pemandu)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.booking_contacts (
    contact_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id BIGINT NOT NULL,
    tourist_id INT NOT NULL,
    guide_id INT NOT NULL,
    contact_phone VARCHAR(20) NOT NULL,
    contact_email VARCHAR(100) NULL,
    preferred_channel VARCHAR(20) NOT NULL DEFAULT 'whatsapp',
    sender_role VARCHAR(20) NOT NULL DEFAULT 'wisatawan',
    inquiry_message TEXT NOT NULL,
    confirmation_status VARCHAR(20) NOT NULL DEFAULT 'unverified',
    confirmed_by INT NULL,
    confirmed_at TIMESTAMPTZ NULL,
    admin_note TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_booking_contacts_phone CHECK (contact_phone ~ '^[1-9][0-9]{8,14}$'),
    CONSTRAINT chk_booking_contacts_channel CHECK (preferred_channel IN ('whatsapp', 'phone', 'email')),
    CONSTRAINT chk_booking_contacts_sender_role CHECK (sender_role IN ('wisatawan', 'guide', 'admin')),
    CONSTRAINT chk_booking_contacts_status CHECK (confirmation_status IN ('unverified', 'contacted', 'confirmed')),
    CONSTRAINT fk_booking_contacts_booking FOREIGN KEY (booking_id) REFERENCES public.bookings(booking_id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_contacts_tourist FOREIGN KEY (tourist_id) REFERENCES public.users(user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_booking_contacts_guide FOREIGN KEY (guide_id) REFERENCES public.guides(guide_id) ON DELETE RESTRICT,
    CONSTRAINT fk_booking_contacts_confirmed_by FOREIGN KEY (confirmed_by) REFERENCES public.users(user_id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_booking_contacts_booking ON public.booking_contacts(booking_id);
CREATE INDEX IF NOT EXISTS idx_booking_contacts_tourist ON public.booking_contacts(tourist_id);
CREATE INDEX IF NOT EXISTS idx_booking_contacts_guide ON public.booking_contacts(guide_id);
CREATE INDEX IF NOT EXISTS idx_booking_contacts_status ON public.booking_contacts(confirmation_status);
CREATE INDEX IF NOT EXISTS idx_booking_contacts_created ON public.booking_contacts(created_at DESC);

DROP TRIGGER IF EXISTS trg_booking_contacts_updated_at ON public.booking_contacts;
CREATE TRIGGER trg_booking_contacts_updated_at
BEFORE UPDATE ON public.booking_contacts
FOR EACH ROW
EXECUTE FUNCTION public.set_updated_at();

-- ---------------------------------------------------------------------
-- 3. AUDIT LOGS SUPPORT UNTUK 'user' & 'booking_contact'
-- ---------------------------------------------------------------------
ALTER TABLE public.audit_logs DROP CONSTRAINT IF EXISTS chk_audit_logs_entity;
ALTER TABLE public.audit_logs
    ADD CONSTRAINT chk_audit_logs_entity
    CHECK (entity_type IN ('booking', 'guide', 'user', 'booking_contact'));

CREATE OR REPLACE FUNCTION public.fn_trg_booking_contacts_audit()
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
        v_entity_id := NEW.contact_id;
        v_new_status := NEW.confirmation_status;
        v_new_data := to_jsonb(NEW);
    ELSIF TG_OP = 'UPDATE' THEN
        v_entity_id := NEW.contact_id;
        v_old_status := OLD.confirmation_status;
        v_new_status := NEW.confirmation_status;
        v_old_data := to_jsonb(OLD);
        v_new_data := to_jsonb(NEW);
    ELSIF TG_OP = 'DELETE' THEN
        v_entity_id := OLD.contact_id;
        v_old_status := OLD.confirmation_status;
        v_old_data := to_jsonb(OLD);
    END IF;

    INSERT INTO public.audit_logs (
        entity_type, entity_id, action, old_status, new_status, old_data, new_data, changed_by, changed_at
    ) VALUES (
        'booking_contact', v_entity_id, TG_OP, v_old_status, v_new_status, v_old_data, v_new_data, v_user_id, CURRENT_TIMESTAMP
    );

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_booking_contacts_audit_log ON public.booking_contacts;
CREATE TRIGGER trg_booking_contacts_audit_log
AFTER INSERT OR UPDATE OR DELETE ON public.booking_contacts
FOR EACH ROW
EXECUTE FUNCTION public.fn_trg_booking_contacts_audit();

CREATE OR REPLACE FUNCTION public.fn_trg_users_verification_audit()
RETURNS TRIGGER
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
    v_user_id BIGINT;
BEGIN
    IF (OLD.is_verified IS NOT DISTINCT FROM NEW.is_verified)
       AND (OLD.phone_number IS NOT DISTINCT FROM NEW.phone_number)
       AND (OLD.verification_note IS NOT DISTINCT FROM NEW.verification_note) THEN
        RETURN NEW;
    END IF;

    BEGIN
        v_user_id := COALESCE(
            NULLIF(current_setting('app.current_user_id', true), '')::BIGINT,
            NEW.verified_by::BIGINT
        );
    EXCEPTION WHEN OTHERS THEN
        v_user_id := NEW.verified_by::BIGINT;
    END;

    INSERT INTO public.audit_logs (
        entity_type, entity_id, action, old_status, new_status, old_data, new_data, changed_by, changed_at
    ) VALUES (
        'user',
        NEW.user_id,
        'UPDATE',
        CASE WHEN OLD.is_verified THEN 'verified' ELSE 'unverified' END,
        CASE WHEN NEW.is_verified THEN 'verified' ELSE 'unverified' END,
        jsonb_build_object(
            'phone_number', OLD.phone_number,
            'is_verified', OLD.is_verified,
            'verification_note', OLD.verification_note
        ),
        jsonb_build_object(
            'phone_number', NEW.phone_number,
            'is_verified', NEW.is_verified,
            'verified_by', NEW.verified_by,
            'verification_note', NEW.verification_note
        ),
        v_user_id,
        CURRENT_TIMESTAMP
    );

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_users_verification_audit_log ON public.users;
CREATE TRIGGER trg_users_verification_audit_log
AFTER UPDATE ON public.users
FOR EACH ROW
EXECUTE FUNCTION public.fn_trg_users_verification_audit();

-- ---------------------------------------------------------------------
-- 4. PERBARUI VIEW vw_bookings_detail (Append kolom di akhir agar kompatibel)
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW public.vw_bookings_detail AS
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
FROM public.bookings b
JOIN public.users u ON u.user_id = b.tourist_id
JOIN public.guides g ON g.guide_id = b.guide_id
JOIN public.destinations d ON d.destination_id = b.destination_id
JOIN public.transports t ON t.transport_id = b.transport_id
LEFT JOIN public.reviews r ON r.booking_id = b.booking_id
LEFT JOIN LATERAL (
    SELECT COUNT(*) AS contact_count
    FROM public.booking_contacts bc
    WHERE bc.booking_id = b.booking_id
) bc_agg ON TRUE
LEFT JOIN LATERAL (
    SELECT bc.confirmation_status, bc.contact_phone, bc.confirmed_at
    FROM public.booking_contacts bc
    WHERE bc.booking_id = b.booking_id
    ORDER BY bc.contact_id DESC
    LIMIT 1
) bc_latest ON TRUE;

-- ---------------------------------------------------------------------
-- 5. GRANTS & ROW LEVEL SECURITY (RLS) UNTUK TABEL booking_contacts
-- ---------------------------------------------------------------------
ALTER TABLE public.booking_contacts ENABLE ROW LEVEL SECURITY;

REVOKE ALL ON TABLE public.booking_contacts FROM PUBLIC, anon, authenticated;

GRANT SELECT, INSERT, UPDATE, DELETE ON public.booking_contacts TO role_sumutgo_admin;
GRANT SELECT, INSERT, UPDATE ON public.booking_contacts TO role_sumutgo_mobile;
GRANT SELECT ON public.vw_bookings_detail TO role_sumutgo_admin, role_sumutgo_mobile;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO role_sumutgo_admin, role_sumutgo_mobile;

DROP POLICY IF EXISTS rls_admin_booking_contacts ON public.booking_contacts;
CREATE POLICY rls_admin_booking_contacts
    ON public.booking_contacts
    FOR ALL
    TO role_sumutgo_admin
    USING (true)
    WITH CHECK (true);

DROP POLICY IF EXISTS rls_mobile_booking_contacts ON public.booking_contacts;
CREATE POLICY rls_mobile_booking_contacts
    ON public.booking_contacts
    FOR ALL
    TO role_sumutgo_mobile
    USING (true)
    WITH CHECK (true);

-- ---------------------------------------------------------------------
-- 6. SEED DATA AWAL booking_contacts (IDEMPOTEN)
-- ---------------------------------------------------------------------
INSERT INTO public.booking_contacts (
    booking_id, tourist_id, guide_id, contact_phone, contact_email,
    preferred_channel, sender_role, inquiry_message,
    confirmation_status, confirmed_by, confirmed_at, admin_note
)
SELECT
    b.booking_id,
    b.tourist_id,
    b.guide_id,
    COALESCE(u.phone_number, '628126001001'),
    u.email,
    'whatsapp',
    'wisatawan',
    'Halo, mohon konfirmasi jadwal penjemputan dan titik kumpul untuk tur ' || b.booking_code || '.',
    CASE WHEN b.status = 'confirmed' THEN 'confirmed' ELSE 'unverified' END,
    CASE WHEN b.status = 'confirmed' THEN (SELECT user_id FROM public.users WHERE email = 'admin@sumutgo.id' LIMIT 1) ELSE NULL END,
    CASE WHEN b.status = 'confirmed' THEN CURRENT_TIMESTAMP ELSE NULL END,
    CASE WHEN b.status = 'confirmed' THEN 'Nomor HP wisatawan & pemandu telah terkonfirmasi via WhatsApp.' ELSE NULL END
FROM public.bookings b
JOIN public.users u ON u.user_id = b.tourist_id
WHERE b.booking_code IN ('BK-20261201-0003', 'BK-20261210-0004')
  AND NOT EXISTS (
      SELECT 1 FROM public.booking_contacts bc WHERE bc.booking_id = b.booking_id
  );

COMMIT;
