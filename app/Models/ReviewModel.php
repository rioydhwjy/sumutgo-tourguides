<?php

namespace App\Models;

use CodeIgniter\Model;

class ReviewModel extends Model
{
    protected $table            = 'reviews';
    protected $primaryKey       = 'review_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'booking_id',
        'rating',
        'review_text',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    /**
     * Retrieve paginated reviews with optional guide_id filter
     */
    public function getReviews(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $builder = $this->db->table('reviews r')
            ->select('r.review_id, r.booking_id, r.rating, r.review_text, r.created_at,
                      b.booking_code, b.guide_id, g.guide_name, g.guide_code,
                      u.user_id as tourist_id, u.full_name as tourist_name, u.email as tourist_email')
            ->join('bookings b', 'b.booking_id = r.booking_id', 'inner')
            ->join('guides g', 'g.guide_id = b.guide_id', 'inner')
            ->join('users u', 'u.user_id = b.tourist_id', 'inner');

        if (! empty($filters['guide_id'])) {
            $builder->where('b.guide_id', (int) $filters['guide_id']);
        }
        if (! empty($filters['booking_id'])) {
            $builder->where('r.booking_id', (int) $filters['booking_id']);
        }

        $totalBuilder = clone $builder;
        $total = $totalBuilder->countAllResults(false);

        $items = $builder->orderBy('r.review_id', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

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
     * Create review. PostgreSQL trigger trg_reviews_only_completed will reject if booking is not completed.
     */
    public function createReview(int $bookingId, int $rating, string $reviewText, int $touristId): array
    {
        // 1. Check booking ownership if tourist
        $booking = $this->db->table('bookings')
            ->where('booking_id', $bookingId)
            ->get()
            ->getRowArray();

        if (! $booking) {
            throw new \CodeIgniter\Database\Exceptions\DatabaseException("Booking dengan ID {$bookingId} tidak ditemukan.");
        }

        if ((int) $booking['tourist_id'] !== $touristId) {
            throw new \CodeIgniter\Database\Exceptions\DatabaseException("Akses ditolak: Anda hanya dapat memberikan ulasan untuk booking milik sendiri.");
        }

        // 2. Insert into reviews (will trigger trg_reviews_only_completed and unique check uq_reviews_booking)
        $sql = 'INSERT INTO reviews (booking_id, rating, review_text) VALUES (?, ?, ?) RETURNING review_id, booking_id, rating, review_text, created_at';
        $query = $this->db->query($sql, [$bookingId, $rating, trim($reviewText)]);
        $row = $query->getRowArray();

        return $row ?? [];
    }
}
