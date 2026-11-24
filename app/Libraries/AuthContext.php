<?php

namespace App\Libraries;

class AuthContext
{
    private static ?array $user = null;

    public static function setUser(array $user): void
    {
        self::$user = $user;
    }

    public static function getUser(): ?array
    {
        return self::$user;
    }

    public static function getUserId(): ?int
    {
        return isset(self::$user['sub']) ? (int) self::$user['sub'] : null;
    }

    public static function getRole(): ?string
    {
        return self::$user['role'] ?? null;
    }

    public static function getEmail(): ?string
    {
        return self::$user['email'] ?? null;
    }

    public static function getFullName(): ?string
    {
        return self::$user['full_name'] ?? null;
    }

    public static function getDbRole(): string
    {
        return (self::getRole() === 'admin') ? 'role_sumutgo_admin' : 'role_sumutgo_mobile';
    }

    public static function clear(): void
    {
        self::$user = null;
    }
}
