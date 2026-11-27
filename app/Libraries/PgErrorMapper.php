<?php

namespace App\Libraries;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Throwable;

class PgErrorMapper
{
    /**
     * Map any Throwable / PostgreSQL exception to standardized HTTP status and clean user-friendly message
     *
     * @return array{statusCode: int, message: string, errors: mixed}
     */
    public static function map(Throwable $e): array
    {
        $msg = $e->getMessage();
        $code = (string) $e->getCode();

        // 1. Exclusion Constraint (Double-booking overlap) - 23P01
        if (str_contains($msg, '23P01') || str_contains($msg, 'ex_bookings_guide_no_overlap')) {
            return [
                'statusCode' => 409,
                'message'    => 'Tour guide sudah memiliki jadwal booking pada rentang tanggal tersebut.',
                'errors'     => null,
            ];
        }

        // 2. Optimistic Locking Conflict - 40001
        if (str_contains($msg, '40001') || str_contains($msg, 'OPTIMISTIC_LOCK_CONFLICT')) {
            return [
                'statusCode' => 409,
                'message'    => 'Data telah diubah oleh pengguna lain. Silakan muat ulang data terbaru.',
                'errors'     => null,
            ];
        }

        // 3. Unique Violation - 23505
        if (str_contains($msg, '23505') || str_contains($msg, 'unique constraint') || str_contains($msg, 'duplicate key value')) {
            if (str_contains($msg, 'uq_reviews_booking') || str_contains($msg, 'reviews_booking_id_key')) {
                return [
                    'statusCode' => 409,
                    'message'    => 'Anda sudah memberikan ulasan untuk booking ini.',
                    'errors'     => null,
                ];
            }
            if (str_contains($msg, 'uq_users_email') || str_contains($msg, 'users_email_key') || str_contains($msg, 'Key (email)')) {
                return [
                    'statusCode' => 409,
                    'message'    => 'Alamat email sudah terdaftar di sistem.',
                    'errors'     => ['email' => 'Email telah digunakan.'],
                ];
            }
            if (str_contains($msg, 'uq_guides_phone') || str_contains($msg, 'phone_number')) {
                return [
                    'statusCode' => 409,
                    'message'    => 'Nomor telepon sudah terdaftar pada pemandu wisata lain.',
                    'errors'     => ['phone_number' => 'Nomor telepon telah digunakan.'],
                ];
            }
            if (str_contains($msg, 'uq_guides_code') || str_contains($msg, 'guide_code')) {
                return [
                    'statusCode' => 409,
                    'message'    => 'Kode pemandu wisata sudah digunakan.',
                    'errors'     => ['guide_code' => 'Kode guide telah digunakan.'],
                ];
            }

            return [
                'statusCode' => 409,
                'message'    => 'Data duplikat terdeteksi pada sistem (nilai unik sudah digunakan).',
                'errors'     => null,
            ];
        }

        // 4. Foreign Key Violation / ON DELETE RESTRICT - 23503
        if (str_contains($msg, '23503') || str_contains($msg, 'foreign key constraint') || str_contains($msg, 'violates foreign key')) {
            return [
                'statusCode' => 409,
                'message'    => 'Data tidak dapat dihapus atau diproses karena masih terhubung dengan data relasi lain.',
                'errors'     => null,
            ];
        }

        // 5. Check Constraint - 23514
        if (str_contains($msg, '23514') || str_contains($msg, 'check constraint')) {
            if (str_contains($msg, 'chk_bookings_dates')) {
                return [
                    'statusCode' => 422,
                    'message'    => 'Tanggal selesai tur harus sama atau setelah tanggal mulai tur.',
                    'errors'     => ['end_date' => 'Tanggal selesai tidak valid.'],
                ];
            }
            if (str_contains($msg, 'chk_bookings_guests')) {
                return [
                    'statusCode' => 422,
                    'message'    => 'Jumlah tamu minimal 1 orang.',
                    'errors'     => ['number_of_guests' => 'Jumlah tamu minimal 1.'],
                ];
            }
            if (str_contains($msg, 'chk_guides_daily_rate')) {
                return [
                    'statusCode' => 422,
                    'message'    => 'Tarif harian harus bernilai positif.',
                    'errors'     => ['daily_rate_idr' => 'Tarif harian harus > 0.'],
                ];
            }
            if (str_contains($msg, 'chk_guides_phone') || str_contains($msg, 'chk_users_phone') || str_contains($msg, 'chk_booking_contacts_phone')) {
                return [
                    'statusCode' => 422,
                    'message'    => 'Format nomor HP tidak valid. Gunakan format Indonesia/internasional, contoh: 081234567890 atau 6281234567890.',
                    'errors'     => ['phone_number' => 'Format nomor telepon salah.'],
                ];
            }

            return [
                'statusCode' => 422,
                'message'    => 'Data yang dikirim melanggar batasan validasi sistem.',
                'errors'     => null,
            ];
        }

        // 6. User-defined exception from Trigger/Stored Procedure - P0001 / RAISE
        if (str_contains($msg, 'P0001') || str_contains($msg, 'raise_exception') || str_contains($msg, 'fn_trg_') || str_contains($msg, 'PL/pgSQL function') || str_contains($msg, 'completed')) {
            // Extract readable error message
            $cleanMsg = 'Aturan bisnis ditolak oleh server.';
            if (preg_match('/ERROR:\s*(.*?)(?:\n|CONTEXT:|$)/i', $msg, $matches)) {
                $cleanMsg = trim($matches[1]);
            } elseif (preg_match('/DETAIL:\s*(.*?)(?:\n|$)/i', $msg, $matches)) {
                $cleanMsg = trim($matches[1]);
            }

            return [
                'statusCode' => 422,
                'message'    => $cleanMsg,
                'errors'     => null,
            ];
        }

        // 7. Insufficient Privilege / RLS - 42501
        if (str_contains($msg, '42501') || str_contains($msg, 'permission denied')) {
            return [
                'statusCode' => 403,
                'message'    => 'Anda tidak memiliki hak akses database untuk operasi ini.',
                'errors'     => null,
            ];
        }

        // 8. Unexpected Error -> Log internal details, NEVER leak stack trace
        log_message('error', '[PG_ERROR_MAPPER] Unexpected error: ' . $msg . "\n" . $e->getTraceAsString());

        return [
            'statusCode' => 500,
            'message'    => 'Terjadi kesalahan pada server.',
            'errors'     => null,
        ];
    }

    /**
     * Map exception and return JSON ResponseInterface directly
     */
    public static function toResponse(Throwable $e): ResponseInterface
    {
        $mapped = self::map($e);

        return Services::response()
            ->setStatusCode($mapped['statusCode'])
            ->setJSON([
                'status'  => 'error',
                'message' => $mapped['message'],
                'data'    => null,
                'errors'  => $mapped['errors'],
            ]);
    }
}
