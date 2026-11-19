<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

class DocumentDownloadController extends Controller
{
    public function download(Request $request, string $path): StreamedResponse|Response
    {
        if (!$request->hasValidSignature()) {
            abort(401, 'Invalid or expired download link signature.');
        }

        $disk = Storage::disk('documents');

        if (!$disk->exists($path)) {
            abort(404, 'Requested document was not found.');
        }

        // Extract tenant client_id from namespaced path: e.g. "1/42/proposal.pdf"
        $segments = explode('/', ltrim($path, '/'));
        $pathClientId = isset($segments[0]) ? (int) $segments[0] : null;
        // Lead document URLs must stop working while their lead is archived.
        if (isset($segments[1]) && ctype_digit($segments[1])) {
            abort_unless(\App\Models\Lead::withoutGlobalScopes()->whereKey((int)$segments[1])->exists(), 404);
        }

        $user = auth()->user();

        if ($user && !$user->hasRole('Super Admin')) {
            $userClientId = $user->organization_id ?? $user->client_id;
            if ($pathClientId && (int) $userClientId !== $pathClientId) {
                abort(403, 'Unauthorized access to tenant document.');
            }
        }

        return $disk->download($path);
    }
}
