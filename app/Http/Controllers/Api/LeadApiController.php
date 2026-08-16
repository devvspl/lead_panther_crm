<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Http\Resources\LeadResource;

class LeadApiController extends Controller
{
    public function show(Lead $lead): LeadResource
    {
        return new LeadResource($lead);
    }
}
