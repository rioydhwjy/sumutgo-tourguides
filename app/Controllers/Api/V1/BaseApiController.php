<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Libraries\AuthContext;
use App\Traits\ApiResponseTrait;

abstract class BaseApiController extends BaseController
{
    use ApiResponseTrait;

    protected function getJsonPayload(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json)) {
            return $json;
        }

        $post = $this->request->getPost();
        if (! empty($post)) {
            return $post;
        }

        $raw = $this->request->getRawInput();
        if (! empty($raw)) {
            return $raw;
        }

        return [];
    }

    protected function getCurrentUser(): ?array
    {
        return AuthContext::getUser() ?? ($this->request->user ?? null);
    }

    protected function getCurrentUserId(): ?int
    {
        $user = $this->getCurrentUser();
        return isset($user['sub']) ? (int) $user['sub'] : null;
    }

    protected function getCurrentRole(): ?string
    {
        $user = $this->getCurrentUser();
        return $user['role'] ?? null;
    }

    protected function getDbRole(): string
    {
        return ($this->getCurrentRole() === 'admin') ? 'role_sumutgo_admin' : 'role_sumutgo_mobile';
    }
}
