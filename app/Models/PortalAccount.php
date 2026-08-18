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
        $cred = $this->credentials()->where('key_name', $key)->first();
        return $cred ? $cred->encrypted_value : null;
    }

    public function setCredential(string $key, ?string $value): void
    {
        if ($value === null) {
            $this->credentials()->where('key_name', $key)->delete();
            return;
        }

        $this->credentials()->updateOrCreate(
            ['key_name' => $key],
            ['encrypted_value' => $value]
        );
    }
}
