<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Support\TeamAccess;
class TeamLeaderAccess {
    public function handle(Request $request, Closure $next) {
        if (TeamAccess::isLeader($request->user())) {
            abort_unless($request->routeIs('dashboard','team-leader.*','leads.index','leads.kanban','followups.index','reports.index','notifications.*','profile','logout','verification.*','password.*','livewire.*'),403);
        }
        return $next($request);
    }
}
