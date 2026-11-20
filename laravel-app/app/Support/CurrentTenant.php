<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;

final class CurrentTenant
{
    private ?int $tenantId = null;

    public function set(int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function requireId(): int
    {
        if ($this->tenantId === null) {
            throw new AuthorizationException('A workspace context is required.');
        }

        return $this->tenantId;
    }
}
