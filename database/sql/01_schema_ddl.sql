-- =====================================================================
-- SUMUT GO - MEDAN & NORTH SUMATRA TOUR GUIDE ECOSYSTEM
-- 01_schema_ddl.sql : 12 Tabel Ternormalisasi 3NF untuk PostgreSQL 16 / Supabase
-- =====================================================================

-- 0. Ekstensi Wajib
CREATE EXTENSION IF NOT EXISTS btree_gist;
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Fungsi umum updated_at trigger
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- ---------------------------------------------------------------------
-- 1. Tabel users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    user_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'wisatawan',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT chk_users_role CHECK (role IN ('admin', 'wisatawan', 'guide')),
    CONSTRAINT chk_users_email_lower CHECK (email = LOWER(TRIM(email)))
);

CREATE TRIGGER trg_users_updated_at
BEFORE UPDATE ON users
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

-- ---------------------------------------------------------------------
-- 2. Tabel tour_specializations
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tour_specializations (
    specialization_id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    specialization_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    CONSTRAINT uq_specialization_name UNIQUE (specialization_name)
);

-- ---------------------------------------------------------------------
-- 3. Tabel languages
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS languages (
    language_id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    language_name VARCHAR(50) NOT NULL,
    CONSTRAINT uq_language_name UNIQUE (language_name)
);

-- ---------------------------------------------------------------------
-- 4. Tabel destinations
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS destinations (
    destination_id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    destination_code VARCHAR(10) NOT NULL,
    destination_name VARCHAR(150) NOT NULL,
    CONSTRAINT uq_destinations_code UNIQUE (destination_code),
    CONSTRAINT chk_destinations_code CHECK (destination_code ~ '^WST-[0-9]{2,3}$')
);

-- ---------------------------------------------------------------------
-- 5. Tabel culinaries
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS culinaries (
    culinary_id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    culinary_code VARCHAR(10) NOT NULL,
    culinary_name VARCHAR(150) NOT NULL,
    CONSTRAINT uq_culinaries_code UNIQUE (culinary_code),
    CONSTRAINT chk_culinaries_code CHECK (culinary_code ~ '^KLN-[0-9]{2,3}$')
);

-- ---------------------------------------------------------------------
-- 6. Tabel hotels
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS hotels (
    hotel_id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    hotel_code VARCHAR(10) NOT NULL,
    hotel_name VARCHAR(150) NOT NULL,
    CONSTRAINT uq_hotels_code UNIQUE (hotel_code),
    CONSTRAINT chk_hotels_code CHECK (hotel_code ~ '^HTL-[0-9]{2,3}$')
);

-- ---------------------------------------------------------------------
-- 7. Tabel transports
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transports (
    transport_id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    transport_code VARCHAR(10) NOT NULL,
    transport_name VARCHAR(150) NOT NULL,
    CONSTRAINT uq_transports_code UNIQUE (transport_code),
    CONSTRAINT chk_transports_code CHECK (transport_code ~ '^TRP-[0-9]{2,3}$')
);

-- ---------------------------------------------------------------------
-- 8. Tabel guides
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS guides (
    guide_id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    guide_code VARCHAR(10) NOT NULL,
    user_id BIGINT NULL,
    guide_name VARCHAR(150) NOT NULL,
    agency_name VARCHAR(150) NOT NULL,
    gmaps_rating NUMERIC(2,1) NOT NULL DEFAULT 0.0,
    gmaps_review_count INT NOT NULL DEFAULT 0,
    basecamp_address TEXT NOT NULL,
    latitude NUMERIC(9,6) NOT NULL,
    longitude NUMERIC(9,6) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    daily_rate_idr NUMERIC(12,2) NOT NULL,
    availability_status VARCHAR(20) NOT NULL DEFAULT 'Tersedia',
    specialization_id INT NOT NULL,
    primary_destination_id INT NOT NULL,
    recommended_culinary_id INT NOT NULL,
    partner_hotel_id INT NOT NULL,
    default_transport_id INT NOT NULL,
    version INT NOT NULL DEFAULT 1,
    CONSTRAINT uq_guides_code UNIQUE (guide_code),
    CONSTRAINT uq_guides_agency UNIQUE (agency_name),
    CONSTRAINT uq_guides_phone UNIQUE (phone_number),
    CONSTRAINT chk_guides_code CHECK (guide_code ~ '^TG-[0-9]{3,4}$'),
    CONSTRAINT chk_guides_phone CHECK (phone_number ~ '^62[0-9]{9,12}$'),
    CONSTRAINT chk_guides_rating CHECK (gmaps_rating >= 0.0 AND gmaps_rating <= 5.0),
    CONSTRAINT chk_guides_review_count CHECK (gmaps_review_count >= 0),
    CONSTRAINT chk_guides_rate CHECK (daily_rate_idr >= 0),
    CONSTRAINT chk_guides_status CHECK (availability_status IN ('Tersedia', 'Sedang Bertugas', 'Booked')),
    CONSTRAINT fk_guides_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_guides_specialization FOREIGN KEY (specialization_id) REFERENCES tour_specializations(specialization_id) ON DELETE RESTRICT,
    CONSTRAINT fk_guides_destination FOREIGN KEY (primary_destination_id) REFERENCES destinations(destination_id) ON DELETE RESTRICT,
    CONSTRAINT fk_guides_culinary FOREIGN KEY (recommended_culinary_id) REFERENCES culinaries(culinary_id) ON DELETE RESTRICT,
    CONSTRAINT fk_guides_hotel FOREIGN KEY (partner_hotel_id) REFERENCES hotels(hotel_id) ON DELETE RESTRICT,
    CONSTRAINT fk_guides_transport FOREIGN KEY (default_transport_id) REFERENCES transports(transport_id) ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- 9. Tabel guide_languages (Relasi M:N)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS guide_languages (
    guide_id INT NOT NULL,
    language_id INT NOT NULL,
    PRIMARY KEY (guide_id, language_id),
    CONSTRAINT fk_guide_languages_guide FOREIGN KEY (guide_id) REFERENCES guides(guide_id) ON DELETE CASCADE,
    CONSTRAINT fk_guide_languages_language FOREIGN KEY (language_id) REFERENCES languages(language_id) ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- 10. Tabel bookings
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
    booking_id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_code VARCHAR(20) NOT NULL,
    tourist_id BIGINT NOT NULL,
    guide_id INT NOT NULL,
    destination_id INT NOT NULL,
    transport_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    duration_days INT GENERATED ALWAYS AS ((end_date - start_date) + 1) STORED,
    daily_rate_idr NUMERIC(12,2) NOT NULL,
    total_price_idr NUMERIC(14,2) GENERATED ALWAYS AS (((end_date - start_date) + 1) * daily_rate_idr) STORED,
    number_of_guests INT NOT NULL DEFAULT 1,
    meeting_point TEXT NOT NULL,
    notes TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    cancel_reason TEXT NULL,
    cancelled_at TIMESTAMPTZ NULL,
    version INT NOT NULL DEFAULT 1,
    CONSTRAINT uq_bookings_code UNIQUE (booking_code),
    CONSTRAINT chk_bookings_dates CHECK (end_date >= start_date),
    CONSTRAINT chk_bookings_guests CHECK (number_of_guests > 0),
    CONSTRAINT chk_bookings_rate CHECK (daily_rate_idr >= 0),
    CONSTRAINT chk_bookings_status CHECK (status IN ('pending', 'confirmed', 'ongoing', 'completed', 'cancelled')),
    CONSTRAINT fk_bookings_tourist FOREIGN KEY (tourist_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_guide FOREIGN KEY (guide_id) REFERENCES guides(guide_id) ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_destination FOREIGN KEY (destination_id) REFERENCES destinations(destination_id) ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_transport FOREIGN KEY (transport_id) REFERENCES transports(transport_id) ON DELETE RESTRICT,
    CONSTRAINT ex_bookings_guide_no_overlap EXCLUDE USING gist (
        guide_id WITH =,
        daterange(start_date, end_date, '[]') WITH &&
    ) WHERE (status NOT IN ('cancelled'))
);

-- ---------------------------------------------------------------------
-- 11. Tabel reviews
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    review_id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id INT NOT NULL,
    rating INT NOT NULL,
    review_text TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_reviews_booking UNIQUE (booking_id),
    CONSTRAINT chk_reviews_rating CHECK (rating >= 1 AND rating <= 5),
    CONSTRAINT fk_reviews_booking FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- 12. Tabel audit_logs
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    audit_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    entity_type VARCHAR(20) NOT NULL,
    entity_id INT NOT NULL,
    action VARCHAR(10) NOT NULL,
    old_status VARCHAR(20) NULL,
    new_status VARCHAR(20) NULL,
    old_data JSONB NULL,
    new_data JSONB NULL,
    changed_by BIGINT NULL,
    changed_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_audit_logs_entity CHECK (entity_type IN ('booking', 'guide')),
    CONSTRAINT chk_audit_logs_action CHECK (action IN ('INSERT', 'UPDATE', 'DELETE')),
    CONSTRAINT fk_audit_changed_by FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- =====================================================================
-- INDEKS PERFORMA & PENCARIAN
-- =====================================================================
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_guides_status ON guides(availability_status);
CREATE INDEX IF NOT EXISTS idx_guides_specialization ON guides(specialization_id);
CREATE INDEX IF NOT EXISTS idx_guides_destination ON guides(primary_destination_id);
CREATE INDEX IF NOT EXISTS idx_guides_daily_rate ON guides(daily_rate_idr);
CREATE INDEX IF NOT EXISTS idx_guides_rating ON guides(gmaps_rating);
CREATE INDEX IF NOT EXISTS idx_guides_name_trgm ON guides USING gin (guide_name gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_guides_agency_trgm ON guides USING gin (agency_name gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_bookings_tourist ON bookings(tourist_id);
CREATE INDEX IF NOT EXISTS idx_bookings_guide ON bookings(guide_id);
CREATE INDEX IF NOT EXISTS idx_bookings_status ON bookings(status);
CREATE INDEX IF NOT EXISTS idx_bookings_dates ON bookings(start_date, end_date);
CREATE INDEX IF NOT EXISTS idx_audit_entity ON audit_logs(entity_type, entity_id);

-- =====================================================================
-- ROW LEVEL SECURITY (RLS)
-- =====================================================================
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE tour_specializations ENABLE ROW LEVEL SECURITY;
ALTER TABLE languages ENABLE ROW LEVEL SECURITY;
ALTER TABLE destinations ENABLE ROW LEVEL SECURITY;
ALTER TABLE culinaries ENABLE ROW LEVEL SECURITY;
ALTER TABLE hotels ENABLE ROW LEVEL SECURITY;
ALTER TABLE transports ENABLE ROW LEVEL SECURITY;
ALTER TABLE guides ENABLE ROW LEVEL SECURITY;
ALTER TABLE guide_languages ENABLE ROW LEVEL SECURITY;
ALTER TABLE bookings ENABLE ROW LEVEL SECURITY;
ALTER TABLE reviews ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;
