<?php

namespace App\Models;

use CodeIgniter\Model;

class TourGuideModel extends Model
{
    protected $table            = 'tour_guides';
    protected $primaryKey       = 'guide_id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'guide_id',
        'nama_guide',
        'nama_agensi_gmaps',
        'rating_gmaps',
        'jumlah_ulasan_gmaps',
        'alamat_basecamp',
        'kecamatan',
        'latitude',
        'longitude',
        'no_telepon',
        'specialization_id',
        'tarif_per_hari_idr',
        'status_ketersediaan',
        'destination_id',
        'culinary_id',
        'hotel_id',
        'transport_id',
    ];

    /**
     * Mengambil daftar lengkap Tour Guide dari VIEW PostgreSQL `vw_tour_guides_lengkap`
     * lengkap dengan filter pencarian, spesialisasi, status, bahasa, tarif maksimum, dan pengurutan.
     */
    public function getFilteredGuides(array $filters = []): array
    {
        $builder = $this->db->table('vw_tour_guides_lengkap');

        if (! empty($filters['spec']) && $filters['spec'] !== 'ALL') {
            $builder->where('spesialisasi_tur', $filters['spec']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'ALL') {
            $builder->where('status_ketersediaan', $filters['status']);
        }

        if (! empty($filters['lang']) && $filters['lang'] !== 'ALL') {
            $builder->like('bahasa_dikuasai', $filters['lang'], 'both', null, true);
        }

        if (! empty($filters['max_price'])) {
            $builder->where('tarif_per_hari_idr <=', (int) $filters['max_price']);
        }

        if (! empty($filters['search'])) {
            $q = trim($filters['search']);
            $builder->groupStart()
                ->like('guide_id', $q, 'both', null, true)
                ->orLike('nama_guide', $q, 'both', null, true)
                ->orLike('nama_agensi_gmaps', $q, 'both', null, true)
                ->orLike('kecamatan', $q, 'both', null, true)
                ->orLike('nama_wisata_utama', $q, 'both', null, true)
                ->orLike('rekomendasi_kuliner', $q, 'both', null, true)
                ->orLike('nama_hotel', $q, 'both', null, true)
                ->orLike('nama_transportasi', $q, 'both', null, true)
                ->orLike('bahasa_dikuasai', $q, 'both', null, true)
                ->groupEnd();
        }

        $sort = $filters['sort'] ?? 'id_asc';
        switch ($sort) {
            case 'rating_desc':
                $builder->orderBy('rating_gmaps', 'DESC')->orderBy('jumlah_ulasan_gmaps', 'DESC');
                break;
            case 'reviews_desc':
                $builder->orderBy('jumlah_ulasan_gmaps', 'DESC');
                break;
            case 'price_asc':
                $builder->orderBy('tarif_per_hari_idr', 'ASC');
                break;
            case 'price_desc':
                $builder->orderBy('tarif_per_hari_idr', 'DESC');
                break;
            default:
                $builder->orderBy('guide_id', 'ASC');
                break;
        }

        $rows = $builder->get()->getResultArray();

        // Format agar struktur JSON identik dengan konsumsi frontend & API
        return array_map(static function (array $r): array {
            $langs = array_values(array_filter(array_map('trim', explode(',', (string) ($r['bahasa_dikuasai'] ?? '')))));
            return [
                'id'             => $r['guide_id'],
                'name'           => $r['nama_guide'],
                'agency'         => $r['nama_agensi_gmaps'],
                'rating'         => (float) $r['rating_gmaps'],
                'reviews'        => (int) $r['jumlah_ulasan_gmaps'],
                'address'        => $r['alamat_basecamp'],
                'district'       => $r['kecamatan'] ?? 'Medan',
                'lat'            => (float) $r['latitude'],
                'lng'            => (float) $r['longitude'],
                'phone'          => $r['no_telepon'],
                'languages'      => $langs,
                'specialization' => $r['spesialisasi_tur'],
                'price'          => (int) $r['tarif_per_hari_idr'],
                'status'         => $r['status_ketersediaan'],
                'destId'         => $r['destination_id'],
                'destName'       => $r['nama_wisata_utama'],
                'culinaryId'     => $r['culinary_id'],
                'culinaryName'   => $r['rekomendasi_kuliner'],
                'hotelId'        => $r['hotel_id'],
                'hotelName'      => $r['nama_hotel'],
                'transportId'    => $r['transport_id'],
                'transportName'  => $r['nama_transportasi'],
            ];
        }, $rows);
    }

    /**
     * Mengambil detail 1 pemandu wisata berdasarkan guide_id (contoh: TG-001)
     */
    public function getGuideDetail(string $guideId): ?array
    {
        $row = $this->db->table('vw_tour_guides_lengkap')
            ->where('guide_id', strtoupper(trim($guideId)))
            ->get()
            ->getRowArray();

        if (! $row) {
            return null;
        }

        $langs = array_values(array_filter(array_map('trim', explode(',', (string) ($row['bahasa_dikuasai'] ?? '')))));
        return [
            'id'             => $row['guide_id'],
            'name'           => $row['nama_guide'],
            'agency'         => $row['nama_agensi_gmaps'],
            'rating'         => (float) $row['rating_gmaps'],
            'reviews'        => (int) $row['jumlah_ulasan_gmaps'],
            'address'        => $row['alamat_basecamp'],
            'district'       => $row['kecamatan'] ?? 'Medan',
            'lat'            => (float) $row['latitude'],
            'lng'            => (float) $row['longitude'],
            'phone'          => $row['no_telepon'],
            'languages'      => $langs,
            'specialization' => $row['spesialisasi_tur'],
            'price'          => (int) $row['tarif_per_hari_idr'],
            'status'         => $row['status_ketersediaan'],
            'destId'         => $row['destination_id'],
            'destName'       => $row['nama_wisata_utama'],
            'culinaryId'     => $row['culinary_id'],
            'culinaryName'   => $row['rekomendasi_kuliner'],
            'hotelId'        => $row['hotel_id'],
            'hotelName'      => $row['nama_hotel'],
            'transportId'    => $row['transport_id'],
            'transportName'  => $row['nama_transportasi'],
        ];
    }

    /**
     * Statistik ringkasan database untuk header dan kartu informasi
     */
    public function getSummaryStats(): array
    {
        $total = $this->db->table('tour_guides')->countAllResults();
        $available = $this->db->table('tour_guides')
            ->where('status_ketersediaan', 'Tersedia')
            ->countAllResults();

        $avgRow = $this->db->query('SELECT ROUND(AVG(rating_gmaps), 2) AS avg_rating, ROUND(AVG(tarif_per_hari_idr), 0) AS avg_price FROM tour_guides')->getRowArray();

        return [
            'total_guides'     => $total,
            'available_guides' => $available,
            'avg_rating'       => (float) ($avgRow['avg_rating'] ?? 4.74),
            'avg_price'        => (int) ($avgRow['avg_price'] ?? 515500),
        ];
    }
}
