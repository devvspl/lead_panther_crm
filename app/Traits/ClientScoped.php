<?php

namespace App\Traits;

use App\Models\Scopes\ClientScope;

trait ClientScoped
{
    protected static function booted(): void
    {
        static::addGlobalScope(new ClientScope);
    }
}
