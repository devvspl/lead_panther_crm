<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Http\Resources\LeadResource;

class LeadApiController extends Controller
{
    public function show(Lead $lead): LeadResource
    {
        if (Lead::usesSalesPanel()) {
            abort_unless(Lead::panelQuery()->whereKey($lead->id)->exists(), 403);
        }

        return new LeadResource($lead);
    }
}
