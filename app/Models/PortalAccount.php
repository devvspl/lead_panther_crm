<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PortalAccount extends Model
{
    protected $guarded = [];

    public function credentials(): HasMany
    {
        return $this->hasMany(IntegrationCredential::class);
    }

    public function formMappings(): HasMany
    {
        return $this->hasMany(LeadFormMapping::class);
    }

    public function getCredential(string $key): ?string
    {
        $cred = $this->credentials->firstWhere('key_name', $key);
        return $cred ? $cred->encrypted_value : null;
    }
}
