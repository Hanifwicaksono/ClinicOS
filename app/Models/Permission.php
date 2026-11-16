<?php

namespace App\Models;

use App\Services\Realtime\AccessMutations;

class Permission extends \Spatie\Permission\Models\Permission
{
    public function assignRole(...$roles): static
    {
        return app(AccessMutations::class)->change($this, fn () => parent::assignRole(...$roles));
    }

    public function removeRole(...$roles): static
    {
        return app(AccessMutations::class)->change($this, fn () => parent::removeRole(...$roles));
    }

    public function syncRoles(...$roles): static
    {
        return app(AccessMutations::class)->change($this, fn () => parent::syncRoles(...$roles));
    }

    public function save(array $options = []): bool
    {
        return app(AccessMutations::class)->change($this, fn () => parent::save($options));
    }

    public function delete(): ?bool
    {
        return app(AccessMutations::class)->change($this, fn () => parent::delete());
    }
}
