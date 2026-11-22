<?php

namespace App\Http\Controllers;

use App\Models\UploadBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class UploadBatchDownloadController extends Controller
{
    /**
     * Download the exact original file uploaded for a bulk import batch.
     */
    public function __invoke(Request $request, UploadBatch $batch): Response
    {
        $user = $request->user();

        // 1. Authorization Check
        $isSuperAdmin = $user && $user->hasAnyRole(['Super Admin', 'super-admin']);
        $isUploader = $user && ((int) $batch->uploaded_by === (int) $user->id);
        $isOrgMember = false;

        if ($user && $user->organization_id) {
            // Check if batch project belongs to user's organization
            $batchProjectOrgId = $batch->project?->client?->organization_id;
            $isOrgMember = $batchProjectOrgId && ((int) $batchProjectOrgId === (int) $user->organization_id);
        }

        if (!$isSuperAdmin && !$isUploader && !$isOrgMember) {
            abort(403, 'Unauthorized access to this upload batch.');
        }

        // 2. Validate file path against directory traversal
        $filePath = $batch->file_path;
        if (empty($filePath) || str_contains($filePath, '..') || str_starts_with($filePath, '/') || str_starts_with($filePath, '\\')) {
            return $this->fileUnavailable($request);
        }

        // 3. Validate existence on the private local disk
        $disk = Storage::disk('local');
        if (!$disk->exists($filePath)) {
            return $this->fileUnavailable($request);
        }

        // 4. Resolve exact original filename and safe header
        $downloadName = $batch->getDownloadFilename();

        // Stream binary download directly
        return $disk->download($filePath, $downloadName);
    }

    /**
     * Return error response when the original file is unavailable.
     */
    protected function fileUnavailable(Request $request): Response
    {
        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Original file unavailable.',
            ], 404);
        }

        return redirect()
            ->route('leads.upload-history')
            ->with('error', 'Original file unavailable for this batch.');
    }
}
