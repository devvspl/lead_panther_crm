<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function start(User $user): RedirectResponse
    {
        $originalAdminId = auth()->id();

        AuditLog::create([
            'user_id' => $originalAdminId,
            'action' => 'user.impersonate_start',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'from_value' => 'Admin User ID: ' . $originalAdminId,
            'to_value' => 'Impersonating User: ' . $user->name . ' (ID: ' . $user->id . ')',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        session(['impersonator_id' => $originalAdminId]);
        auth()->login($user);

        return redirect()->route('dashboard')->with('success', "Now impersonating {$user->name}.");
    }

    public function stop(): RedirectResponse
    {
        $impersonatorId = session('impersonator_id');

        if (!$impersonatorId) {
            return redirect()->route('dashboard');
        }

        $admin = User::find($impersonatorId);
        $currentUser = auth()->user();

        if ($admin) {
            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'user.impersonate_stop',
                'subject_type' => User::class,
                'subject_id' => $currentUser?->id,
                'from_value' => 'Stopped impersonation of User ID: ' . $currentUser?->id,
                'to_value' => 'Restored Admin Session ID: ' . $admin->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            session()->forget('impersonator_id');
            auth()->login($admin);

            return redirect()->route('admin.users')->with('success', 'Impersonation ended. Returned to Admin panel.');
        }

        return redirect()->route('dashboard');
    }
}
