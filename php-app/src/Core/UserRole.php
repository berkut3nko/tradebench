<?php

namespace App\Core;

/**
 * @brief Value Object encapsulating user role logic and permission checks.
 *
 * This class eliminates scattered magic strings ('pro', 'admin', 'standard')
 * and duplicated conditional checks throughout the codebase by centralizing
 * all role-based access control logic in a single place.
 */
class UserRole
{
    public const STANDARD = 'standard';
    public const PRO      = 'pro';
    public const ADMIN    = 'admin';

    /** @var string The underlying role string */
    private string $role;

    /**
     * @param string $role One of the UserRole::* constants.
     * @throws \InvalidArgumentException When an unknown role string is provided.
     */
    public function __construct(string $role)
    {
        if (!in_array($role, [self::STANDARD, self::PRO, self::ADMIN], true)) {
            throw new \InvalidArgumentException("Unknown role: '$role'");
        }
        $this->role = $role;
    }

    /**
     * @brief Returns the raw role string (for DB persistence / JWT payload).
     */
    public function value(): string
    {
        return $this->role;
    }

    /**
     * @brief Whether the role has at least PRO-level privileges.
     * Admins implicitly satisfy every PRO permission.
     */
    public function isPro(): bool
    {
        return $this->role === self::PRO || $this->role === self::ADMIN;
    }

    /**
     * @brief Whether the role is ADMIN.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ADMIN;
    }

    /**
     * @brief Whether the role is a plain STANDARD (free) account.
     */
    public function isStandard(): bool
    {
        return $this->role === self::STANDARD;
    }

    /**
     * @brief Maximum back-test period (in days) allowed for this role.
     */
    public function maxBacktestDays(): int
    {
        return $this->isPro() ? PHP_INT_MAX : 30;
    }

    /**
     * @brief Whether custom timeframes are allowed for this role.
     */
    public function canUseCustomTimeframe(): bool
    {
        return $this->isPro();
    }

    /**
     * @brief Whether the genetic-optimization strategy is available.
     */
    public function canUseOptimizeStrategy(): bool
    {
        return $this->isPro();
    }

    public function __toString(): string
    {
        return $this->role;
    }
}
