<?php

namespace App\Controllers\Api\V1;

use App\Libraries\DbTransactionRunner;
use App\Libraries\PgErrorMapper;
use App\Models\ReviewModel;
use CodeIgniter\HTTP\ResponseInterface;
use DomainException;
use Throwable;

class ReviewController extends BaseApiController
{
    /**
     * GET /api/v1/reviews
     */
    public function index(): ResponseInterface
    {
        $model = new ReviewModel();

        $page = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 15);

        $filters = [
            'guide_id'   => $this->request->getGet('guide_id'),
            'booking_id' => $this->request->getGet('booking_id'),
        ];

        try {
            $result = $model->getReviews($filters, $page, $perPage);
            return $this->respondSuccess($result['items'], 'Daftar ulasan berhasil dimuat.', 200, $result['meta']);
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * POST /api/v1/reviews
     */
    public function create(): ResponseInterface
    {
        $payload = $this->getJsonPayload();
        $userId = $this->getCurrentUserId();

        $rules = [
            'booking_id'  => 'required|numeric',
            'rating'      => 'required|numeric|greater_than_equal_to[1]|less_than_equal_to[5]',
            'review_text' => 'required|min_length[5]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->respondError('Validasi data ulasan gagal.', 422, $this->getValidationErrors());
        }

        $bookingId = (int) $payload['booking_id'];
        $rating = (int) $payload['rating'];
        $reviewText = (string) $payload['review_text'];

        $model = new ReviewModel();

        try {
            $created = DbTransactionRunner::runAsRole('role_sumutgo_mobile', $userId, function () use ($model, $bookingId, $rating, $reviewText, $userId) {
                return $model->createReview($bookingId, $rating, $reviewText, $userId);
            });

            return $this->respondSuccess($created, 'Ulasan berhasil disimpan.', 201);
        } catch (DomainException $e) {
            $statusCode = in_array($e->getCode(), [403, 404], true) ? (int) $e->getCode() : 422;
            return $this->respondError($e->getMessage(), $statusCode);
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }
}
