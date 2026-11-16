<?php

namespace App\Models;

use App\Services\Realtime\AccessMutations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Role extends \Spatie\Permission\Models\Role
{
    public function assignToModels(array|Collection|Model|int|string $models, ?string $modelClass = null): static
    {
        return app(AccessMutations::class)->change($this, fn () => parent::assignToModels($models, $modelClass));
    }

    public function removeFromModels(array|Collection|Model|int|string $models, ?string $modelClass = null): static
    {
        return app(AccessMutations::class)->change($this, fn () => parent::removeFromModels($models, $modelClass));
    }

    public function syncModels(array|Collection|Model|int|string $models, ?string $modelClass = null): static
    {
        return app(AccessMutations::class)->change($this, fn () => parent::syncModels($models, $modelClass));
    }

    public function givePermissionTo(...$permissions): static
    {
        return app(AccessMutations::class)->change($this, fn () => parent::givePermissionTo(...$permissions));
    }

    public function revokePermissionTo($permission): static
    {
        return app(AccessMutations::class)->change($this, fn () => parent::revokePermissionTo($permission));
    }

    public function syncPermissions(...$permissions): static
    {
        return app(AccessMutations::class)->change($this, fn () => parent::syncPermissions(...$permissions));
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
