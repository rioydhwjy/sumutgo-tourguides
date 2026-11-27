<?php

namespace App\Models;

use App\Libraries\PhoneNormalizer;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Model;

class TourGuideModel extends Model
{
    protected $table            = 'guides';
    protected $primaryKey       = 'guide_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'guide_code',
        'user_id',
        'guide_name',
        'agency_name',
        'gmaps_rating',
        'gmaps_review_count',
        'basecamp_address',
        'latitude',
        'longitude',
        'phone_number',
        'daily_rate_idr',
        'availability_status',
        'specialization_id',
        'primary_destination_id',
        'recommended_culinary_id',
        'partner_hotel_id',
        'default_transport_id',
        'version',
    ];

    protected $useTimestamps = false;

    /**
     * Retrieve paginated tour guides from vw_guides_detail with comprehensive filtering & sorting
     */
    public function getFilteredGuides(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = min(500, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $builder = $this->db->table('vw_guides_detail');

        // Status Filter
        if (! empty($filters['status']) && $filters['status'] !== 'ALL') {
            $builder->where('availability_status', trim($filters['status']));
        }

        // Specialization Filter
        if (! empty($filters['specialization_id']) && $filters['specialization_id'] !== 'ALL') {
            $builder->where('specialization_id', (int) $filters['specialization_id']);
        }

        // Destination Filter
        if (! empty($filters['destination_id']) && $filters['destination_id'] !== 'ALL') {
            $builder->where('primary_destination_id', (int) $filters['destination_id']);
        }

        // Language Filter (checks if language_id is inside language_ids integer array)
        if (! empty($filters['language_id']) && $filters['language_id'] !== 'ALL') {
            $langId = (int) $filters['language_id'];
            $builder->where("? = ANY(language_ids)", [$langId]);
        }

        // Price Range Filters
        if (! empty($filters['min_rate'])) {
            $builder->where('daily_rate_idr >=', (int) $filters['min_rate']);
        }
        if (! empty($filters['max_rate'])) {
            $builder->where('daily_rate_idr <=', (int) $filters['max_rate']);
        }

        // Rating Filter
        if (! empty($filters['min_rating'])) {
            $builder->where('gmaps_rating >=', (float) $filters['min_rating']);
        }

        // Full-Text / Keyword Search with parameterized ILIKE (insensitiveSearch = true)
        if (! empty($filters['q'])) {
            $q = trim($filters['q']);
            $builder->groupStart()
                ->like('guide_code', $q, 'both', null, true)
                ->orLike('guide_name', $q, 'both', null, true)
                ->orLike('agency_name', $q, 'both', null, true)
                ->orLike('basecamp_address', $q, 'both', null, true)
                ->orLike('specialization_name', $q, 'both', null, true)
                ->orLike('destination_name', $q, 'both', null, true)
                ->orLike('culinary_name', $q, 'both', null, true)
                ->orLike('hotel_name', $q, 'both', null, true)
                ->orLike('transport_name', $q, 'both', null, true)
                ->orLike('spoken_languages', $q, 'both', null, true)
                ->groupEnd();
        }

        // Sorting Whitelist
        $sort = $filters['sort'] ?? 'id_asc';
        switch ($sort) {
            case 'rating_desc':
                $builder->orderBy('gmaps_rating', 'DESC')->orderBy('gmaps_review_count', 'DESC');
                break;
            case 'reviews_desc':
                $builder->orderBy('gmaps_review_count', 'DESC');
                break;
            case 'rate_asc':
                $builder->orderBy('daily_rate_idr', 'ASC');
                break;
            case 'rate_desc':
                $builder->orderBy('daily_rate_idr', 'DESC');
                break;
            case 'name_asc':
                $builder->orderBy('guide_name', 'ASC');
                break;
            default:
                $builder->orderBy('guide_id', 'ASC');
                break;
        }

        if ($page === 1 && $perPage >= 150) {
            $rows = $builder->limit($perPage, $offset)->get()->getResultArray();
            $total = count($rows) < $perPage
                ? count($rows)
                : (clone $builder)->countAllResults(false);
        } else {
            $totalBuilder = clone $builder;
            $total = $totalBuilder->countAllResults(false);
            $rows = $builder->limit($perPage, $offset)->get()->getResultArray();
        }

        $items = array_map(function (array $r): array {
            return $this->formatGuideRow($r);
        }, $rows);

        return [
            'items' => $items,
            'meta'  => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total_items'  => $total,
                'total_pages'  => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Get detail of a single guide by guide_id or guide_code
     */
    public function getGuideDetail(int|string $identifier): ?array
    {
        $builder = $this->db->table('vw_guides_detail');
        if (is_numeric($identifier)) {
            $builder->where('guide_id', (int) $identifier);
        } else {
            $builder->where('guide_code', strtoupper(trim((string) $identifier)));
        }

        $row = $builder->get()->getRowArray();
        if (! $row) {
            return null;
        }

        $formatted = $this->formatGuideRow($row);

        // Fetch recent reviews
        $reviews = $this->db->table('reviews r')
            ->select('r.review_id, r.rating, r.review_text, r.created_at, u.full_name as reviewer_name')
            ->join('bookings b', 'b.booking_id = r.booking_id', 'inner')
            ->join('users u', 'u.user_id = b.tourist_id', 'inner')
            ->where('b.guide_id', $formatted['guide_id'])
            ->orderBy('r.review_id', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();

        $formatted['recent_reviews'] = $reviews;

        return $formatted;
    }

    /**
     * Create guide in ACID transaction
     */
    public function createGuide(array $data, array $languageIds): array
    {
        // 1. Sanitize phone number (e.g. 08... or +62... -> 62...)
        $phone = $this->normalizePhoneNumber((string) ($data['phone_number'] ?? ''));

        // 2. Resolve guide_code
        $guideCode = strtoupper(trim((string) ($data['guide_code'] ?? '')));
        if ($guideCode === '') {
            $nextRow = $this->db->query("SELECT 'TG-' || LPAD((COALESCE(MAX(SUBSTRING(guide_code FROM 4)::INT), 0) + 1)::TEXT, 3, '0') AS next_code FROM guides")->getRowArray();
            $guideCode = $nextRow['next_code'] ?? 'TG-999';
        }

        $sql = "INSERT INTO guides (
            guide_code, user_id, guide_name, agency_name, gmaps_rating, gmaps_review_count,
            basecamp_address, latitude, longitude, phone_number, daily_rate_idr,
            availability_status, specialization_id, primary_destination_id, recommended_culinary_id,
            partner_hotel_id, default_transport_id, version
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1) RETURNING guide_id";

        $res = $this->db->query($sql, [
            $guideCode,
            ! empty($data['user_id']) ? (int) $data['user_id'] : null,
            trim((string) ($data['guide_name'] ?? '')),
            trim((string) ($data['agency_name'] ?? 'Pemandu Mandiri Medan')),
            (float) ($data['gmaps_rating'] ?? 5.0),
            (int) ($data['gmaps_review_count'] ?? 0),
            trim((string) ($data['basecamp_address'] ?? 'Medan, Sumatera Utara')),
            (float) ($data['latitude'] ?? 3.5952),
            (float) ($data['longitude'] ?? 98.6722),
            $phone,
            (int) ($data['daily_rate_idr'] ?? 500000),
            trim((string) ($data['availability_status'] ?? 'Tersedia')),
            (int) ($data['specialization_id'] ?? 1),
            ! empty($data['primary_destination_id']) ? (int) $data['primary_destination_id'] : null,
            ! empty($data['recommended_culinary_id']) ? (int) $data['recommended_culinary_id'] : null,
            ! empty($data['partner_hotel_id']) ? (int) $data['partner_hotel_id'] : null,
            ! empty($data['default_transport_id']) ? (int) $data['default_transport_id'] : null,
        ]);

        $row = $res->getRowArray();
        $guideId = (int) ($row['guide_id'] ?? 0);

        // 3. Insert languages
        if (! empty($languageIds)) {
            $langInserts = [];
            foreach (array_unique($languageIds) as $lid) {
                if ((int) $lid > 0) {
                    $langInserts[] = [
                        'guide_id'    => $guideId,
                        'language_id' => (int) $lid,
                    ];
                }
            }
            if (! empty($langInserts)) {
                $this->db->table('guide_languages')->insertBatch($langInserts);
            }
        }

        return $this->getGuideDetail($guideId);
    }

    /**
     * Update guide with Optimistic Locking
     */
    public function updateGuide(int $guideId, array $data, ?array $languageIds, int $expectedVersion): array
    {
        $phone = isset($data['phone_number']) ? $this->normalizePhoneNumber((string) $data['phone_number']) : null;

        $updateCols = [];
        $binds = [];

        $fields = [
            'guide_name', 'agency_name', 'basecamp_address', 'latitude', 'longitude',
            'daily_rate_idr', 'availability_status', 'specialization_id',
            'primary_destination_id', 'recommended_culinary_id', 'partner_hotel_id',
            'default_transport_id',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $updateCols[] = "{$field} = ?";
                $binds[] = $data[$field];
            }
        }

        if ($phone !== null) {
            $updateCols[] = 'phone_number = ?';
            $binds[] = $phone;
        }

        if (empty($updateCols)) {
            return $this->getGuideDetail($guideId);
        }

        // Add version increment
        $updateCols[] = 'version = version + 1';

        $setClause = implode(', ', $updateCols);
        $sql = "UPDATE guides SET {$setClause} WHERE guide_id = ? AND version = ? RETURNING guide_id, version";
        $binds[] = $guideId;
        $binds[] = $expectedVersion;

        $query = $this->db->query($sql, $binds);
        if ($this->db->affectedRows() === 0) {
            // Check if guide exists
            $exists = $this->db->table('guides')->where('guide_id', $guideId)->countAllResults();
            if ($exists > 0) {
                throw new DatabaseException('OPTIMISTIC_LOCK_CONFLICT (40001): Data pemandu wisata telah diubah oleh pengguna lain. Silakan muat ulang.');
            }
            throw new DatabaseException("Pemandu wisata dengan ID {$guideId} tidak ditemukan.");
        }

        // Sync languages if provided
        if ($languageIds !== null) {
            $this->db->table('guide_languages')->where('guide_id', $guideId)->delete();
            $langInserts = [];
            foreach (array_unique($languageIds) as $lid) {
                if ((int) $lid > 0) {
                    $langInserts[] = [
                        'guide_id'    => $guideId,
                        'language_id' => (int) $lid,
                    ];
                }
            }
            if (! empty($langInserts)) {
                $this->db->table('guide_languages')->insertBatch($langInserts);
            }
        }

        return $this->getGuideDetail($guideId);
    }

    /**
     * Delete guide
     */
    public function deleteGuide(int $guideId): bool
    {
        $this->db->table('guide_languages')->where('guide_id', $guideId)->delete();
        $this->db->table('guides')->where('guide_id', $guideId)->delete();

        return $this->db->affectedRows() > 0;
    }

    /**
     * Summary stats for dashboards
     */
    public function getSummaryStats(): array
    {
        $summaryRow = $this->db->query(
            "SELECT
                COUNT(*)::int AS total_guides,
                COUNT(*) FILTER (WHERE availability_status = 'Tersedia')::int AS available_guides,
                COUNT(*) FILTER (WHERE availability_status = 'Booked')::int AS booked_guides,
                COUNT(*) FILTER (WHERE availability_status = 'Sedang Bertugas')::int AS on_duty_guides,
                ROUND(AVG(gmaps_rating), 2) AS avg_rating,
                ROUND(AVG(daily_rate_idr), 0) AS avg_rate
             FROM guides"
        )->getRowArray();

        $specs = $this->db->table('guides g')
            ->select('ts.specialization_name, COUNT(g.guide_id) as count')
            ->join('tour_specializations ts', 'ts.specialization_id = g.specialization_id', 'left')
            ->groupBy('ts.specialization_name')
            ->get()
            ->getResultArray();

        $totalBookings = $this->db->table('bookings')->countAllResults();

        return [
            'total_guides'        => (int) ($summaryRow['total_guides'] ?? 0),
            'available_guides'    => (int) ($summaryRow['available_guides'] ?? 0),
            'booked_guides'       => (int) ($summaryRow['booked_guides'] ?? 0),
            'on_duty_guides'      => (int) ($summaryRow['on_duty_guides'] ?? 0),
            'avg_rating'          => (float) ($summaryRow['avg_rating'] ?? 4.74),
            'avg_rate_idr'        => (int) ($summaryRow['avg_rate'] ?? 515500),
            'total_bookings'      => $totalBookings,
            'by_specialization'   => $specs,
        ];
    }

    /**
     * Sanitize phone number to standard 62...
     */
    private function normalizePhoneNumber(string $phone): string
    {
        return PhoneNormalizer::normalize($phone);
    }

    /**
     * Format row from vw_guides_detail for JSON
     */
    private function formatGuideRow(array $r): array
    {
        $languages = [];
        if (isset($r['languages_json'])) {
            $languages = is_string($r['languages_json']) ? json_decode($r['languages_json'], true) : $r['languages_json'];
        }

        // Convert PostgreSQL integer array format string "{1,2}" if needed
        $langIds = [];
        if (isset($r['language_ids'])) {
            if (is_array($r['language_ids'])) {
                $langIds = array_map('intval', $r['language_ids']);
            } elseif (is_string($r['language_ids'])) {
                $clean = trim($r['language_ids'], '{}');
                $langIds = ($clean === '') ? [] : array_map('intval', explode(',', $clean));
            }
        }

        $languageNames = [];
        if (! empty($languages) && is_array($languages)) {
            foreach ($languages as $l) {
                if (is_array($l) && isset($l['language_name'])) {
                    $languageNames[] = $l['language_name'];
                } elseif (is_string($l)) {
                    $languageNames[] = $l;
                }
            }
        }

        $district = 'Kota Medan';
        if (! empty($r['basecamp_address']) && preg_match('/Kec\.\s*([^,]+)/i', (string) $r['basecamp_address'], $matches)) {
            $district = trim($matches[1]);
        }

        return [
            // Flat aliases for frontend backwards compatibility
            'id'                      => $r['guide_code'],
            'name'                    => $r['guide_name'],
            'agency'                  => $r['agency_name'],
            'rating'                  => (float) $r['gmaps_rating'],
            'reviews'                 => (int) $r['gmaps_review_count'],
            'address'                 => $r['basecamp_address'],
            'district'                => $district,
            'lat'                     => (float) $r['latitude'],
            'lng'                     => (float) $r['longitude'],
            'phone'                   => $r['phone_number'],
            'price'                   => (int) $r['daily_rate_idr'],
            'status'                  => $r['availability_status'],
            'specialization'          => $r['specialization_name'] ?? 'Umum',
            'destId'                  => $r['destination_code'] ?? ($r['primary_destination_id'] ?? null),
            'destName'                => $r['destination_name'] ?? '',
            'culinaryId'              => $r['culinary_code'] ?? ($r['recommended_culinary_id'] ?? null),
            'culinaryName'            => $r['culinary_name'] ?? '',
            'hotelId'                 => $r['hotel_code'] ?? ($r['partner_hotel_id'] ?? null),
            'hotelName'               => $r['hotel_name'] ?? '',
            'transportId'             => $r['transport_code'] ?? ($r['default_transport_id'] ?? null),
            'transportName'           => $r['transport_name'] ?? '',
            'language_names'          => $languageNames,

            // REST API v1 structured fields
            'guide_id'                => (int) $r['guide_id'],
            'guide_code'              => $r['guide_code'],
            'guide_name'              => $r['guide_name'],
            'agency_name'             => $r['agency_name'],
            'gmaps_rating'            => (float) $r['gmaps_rating'],
            'gmaps_review_count'       => (int) $r['gmaps_review_count'],
            'basecamp_address'        => $r['basecamp_address'],
            'latitude'                => (float) $r['latitude'],
            'longitude'               => (float) $r['longitude'],
            'phone_number'            => $r['phone_number'],
            'daily_rate_idr'          => (int) $r['daily_rate_idr'],
            'availability_status'     => $r['availability_status'],
            'version'                 => (int) $r['version'],
            'specialization_detail'   => [
                'id'          => $r['specialization_id'] ? (int) $r['specialization_id'] : null,
                'name'        => $r['specialization_name'],
            ],
            'primary_destination'     => [
                'id'          => ! empty($r['primary_destination_id']) ? (int) $r['primary_destination_id'] : null,
                'code'        => $r['destination_code'] ?? null,
                'name'        => $r['destination_name'] ?? null,
            ],
            'recommended_culinary'    => [
                'id'          => ! empty($r['recommended_culinary_id']) ? (int) $r['recommended_culinary_id'] : null,
                'code'        => $r['culinary_code'] ?? null,
                'name'        => $r['culinary_name'] ?? null,
            ],
            'partner_hotel'           => [
                'id'          => ! empty($r['partner_hotel_id']) ? (int) $r['partner_hotel_id'] : null,
                'code'        => $r['hotel_code'] ?? null,
                'name'        => $r['hotel_name'] ?? null,
            ],
            'default_transport'       => [
                'id'          => ! empty($r['default_transport_id']) ? (int) $r['default_transport_id'] : null,
                'code'        => $r['transport_code'] ?? null,
                'name'        => $r['transport_name'] ?? null,
            ],
            'languages'               => $languages,
            'language_ids'            => $langIds,
            'spoken_languages'        => $r['spoken_languages'] ?? '',
            'internal_reviews'        => [
                'avg_rating'   => $r['internal_avg_rating'] !== null ? (float) $r['internal_avg_rating'] : null,
                'review_count' => (int) ($r['internal_review_count'] ?? 0),
            ],
        ];
    }
}
