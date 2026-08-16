<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\LeadPresenter;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return LeadPresenter::present($this->resource, $request->user());
    }
}
