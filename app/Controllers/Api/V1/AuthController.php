<?php

namespace App\Controllers\Api\V1;

use App\Libraries\DbTransactionRunner;
use App\Libraries\JwtService;
use App\Libraries\PgErrorMapper;
use App\Libraries\PhoneNormalizer;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class AuthController extends BaseApiController
{
    /**
     * POST /api/v1/auth/register
     */
    public function register(): ResponseInterface
    {
        $payload = $this->getJsonPayload();

        $rules = [
            'full_name'    => 'required|min_length[2]|max_length[100]',
            'email'        => 'required|valid_email|max_length[100]',
            'phone_number' => 'required|min_length[8]|max_length[25]',
            'password'     => 'required|min_length[8]',
        ];

        if (array_key_exists('password_confirmation', $payload)) {
            $rules['password_confirmation'] = 'required|matches[password]';
        }

        if (! $this->validateData($payload, $rules)) {
            return $this->respondError('Validasi formulir pendaftaran gagal.', 422, $this->getValidationErrors());
        }

        $normalizedPhone = PhoneNormalizer::normalize((string) ($payload['phone_number'] ?? ''));
        if (! PhoneNormalizer::isValid($normalizedPhone)) {
            return $this->respondError('Format Nomor HP tidak valid.', 422, [
                'phone_number' => 'Gunakan nomor HP Indonesia/internasional yang valid, contoh: 081234567890 atau +6281234567890.',
            ]);
        }

        $userModel = new UserModel();
        $email = strtolower(trim((string) $payload['email']));

        if ($userModel->findByEmail($email)) {
            return $this->respondError('Alamat email sudah terdaftar di sistem.', 409, [
                'email' => 'Email telah digunakan.',
            ]);
        }

        try {
            $user = DbTransactionRunner::runAsRole('role_sumutgo_mobile', null, function () use ($userModel, $payload, $normalizedPhone) {
                return $userModel->createUser([
                    'full_name'    => $payload['full_name'],
                    'email'        => $payload['email'],
                    'phone_number' => $normalizedPhone,
                    'password'     => $payload['password'],
                    'role'         => 'wisatawan', // Paksa role default wisatawan, abaikan input role publik
                ]);
            });

            $jwtService = new JwtService();
            $token = $jwtService->generateToken($user);

            unset($user['password_hash']);

            return $this->respondSuccess([
                'token' => $token,
                'user'  => $user,
            ], 'Pendaftaran akun wisatawan berhasil.', 201);
        } catch (Throwable $e) {
            return PgErrorMapper::toResponse($e);
        }
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(): ResponseInterface
    {
        $payload = $this->getJsonPayload();

        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->respondError('Format email atau password tidak valid.', 422, $this->validator->getErrors());
        }

        $userModel = new UserModel();
        $email = strtolower(trim((string) $payload['email']));
        $password = (string) $payload['password'];

        $user = $userModel->verifyCredentials($email, $password);
        if (! $user) {
            return $this->respondError('Kredensial login tidak cocok atau akun tidak aktif.', 401);
        }

        // Update last login timestamp
        $userModel->updateLastLogin((int) $user['user_id']);

        $jwtService = new JwtService();
        $token = $jwtService->generateToken($user);

        unset($user['password_hash']);

        return $this->respondSuccess([
            'token' => $token,
            'user'  => $user,
        ], 'Login berhasil.');
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(): ResponseInterface
    {
        $userId = $this->getCurrentUserId();
        if (! $userId) {
            return $this->respondError('Pengguna tidak terautentikasi.', 401);
        }

        $userModel = new UserModel();
        $user = $userModel->findPublicById($userId);

        if (! $user) {
            return $this->respondError('Pengguna tidak ditemukan.', 404);
        }

        if (! ($user['is_active'] ?? true)) {
            return $this->respondError('Akun pengguna sedang dinonaktifkan.', 403);
        }

        return $this->respondSuccess($user, 'Profil pengguna aktif.');
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(): ResponseInterface
    {
        return $this->respondSuccess(null, 'Berhasil logout (token dibebaskan dari client).');
    }
}
