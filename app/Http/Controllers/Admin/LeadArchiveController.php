<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LeadArchiveService;
use Illuminate\Http\Request;

class LeadArchiveController extends Controller
{
    public function archive(Request $request, int $id, LeadArchiveService $service)
    {
        LeadArchiveService::authorize();
        $data = $request->validate(['confirmed'=>'required|accepted','reason'=>'nullable|string|max:2000']);
        $service->archive($id, $data['reason'] ?? null);
        return response()->noContent();
    }

    public function restore(Request $request, int $id, LeadArchiveService $service)
    {
        LeadArchiveService::authorize();
        $data = $request->validate(['confirmed'=>'required|accepted','reassign'=>'sometimes|boolean']);
        $service->restore($id, (bool)($data['reassign'] ?? false));
        return response()->noContent();
    }

    public function destroy(Request $request, int $id, LeadArchiveService $service)
    {
        LeadArchiveService::authorize();
        $data = $request->validate(['confirmed'=>'required|accepted','reason'=>'nullable|string|max:2000']);
        $service->permanentlyDelete($id, $data['reason'] ?? null);
        return response()->noContent();
    }
}
