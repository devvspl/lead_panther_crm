<?php

namespace App\Traits;

trait HidesMonetaryValues
{
    public function toArray(): array
    {
        $data = parent::toArray();
        return \App\Support\FinancialVisibility::allowed() ? $data : \App\Support\FinancialVisibility::redact($data);
    }
}
