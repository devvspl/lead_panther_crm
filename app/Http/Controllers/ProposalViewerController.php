<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Proposal;

class ProposalViewerController extends Controller
{
    public function view(Request $request, Proposal $proposal)
    {
        // Update viewed_at timestamp if signed URL is valid
        if (!$proposal->viewed_at) {
            $proposal->update(['viewed_at' => now()]);
        }

        return view('pages.proposal-public', [
            'proposal' => $proposal,
            'lead' => $proposal->lead,
            'unit' => $proposal->unit,
        ]);
    }
}
