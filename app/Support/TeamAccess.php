<?php
namespace App\Support;
use App\Models\{User, SalesTeam, SalesTeamMember, Organization};
class TeamAccess {
    public static function isLeader(?User $user): bool { return $user && NavigationConfig::getRoleKey($user)==='team-leader'; }
    public static function teams(User $user) {
        $q=SalesTeam::query();
        if (! $user->is_active || ! $user->organization_id || ! $user->can('view-team-leads')) return $q->whereRaw('1=0');
        return $q->where('leader_id',$user->id)->where(function($q) use($user) {
            $q->where(fn($q)=>$q->where('ownable_type',Organization::class)->where('ownable_id',$user->organization_id))
                ->orWhere(fn($q)=>$q->where('ownable_type',User::class)->whereIn('ownable_id',User::where('organization_id',$user->organization_id)->select('id')));
        })->where(function ($membership) use ($user) {
            // leader_id is authoritative for imported teams without a duplicate leader pivot.
            // An explicitly revoked membership still denies access.
            $membership->whereHas('members', fn ($m) => $m->where('user_id', $user->id)->where('is_active', true))
                ->orWhereDoesntHave('members', fn ($m) => $m->where('user_id', $user->id));
        });
    }
    public static function executives(User $user, bool $active = true) {
        $q=User::where('organization_id',$user->organization_id)->whereHas('roles',fn($r)=>$r->whereIn('name',['Executive','sales-executive','Sales Executive','sales_executive']))
            ->whereIn('id',SalesTeamMember::whereIn('sales_team_id',self::teams($user)->select('id'))->when($active,fn($m)=>$m->where('is_active',true))->select('user_id'));
        if ($active) $q->where('status','active');
        return $q;
    }
    public static function scopeLeads($q, User $user) {
        return $q->where('internal_processing_pending',false)->where('delivery_pending',false)
            ->whereHas('client',fn($owner)=>$owner->where('organization_id',$user->organization_id))
            ->where(function ($q) use ($user) {
                // Inactive executives cannot receive new assignments, but their existing
                // leads must remain visible to the leader for reassignment.
                $assignees = self::executives($user, false)->whereIn('id', SalesTeamMember::whereIn('sales_team_id', self::teams($user)->select('id'))->where('is_active', true)->select('user_id'));
                $q->whereIn('assigned_to', $assignees->select('id'));
                if (self::teams($user)->exists()) $q->orWhere('assigned_to', $user->id);
                $teamId = $user->organization?->assignment_config['team_id'] ?? null;
                if ($teamId && self::teams($user)->whereKey($teamId)->exists()) {
                    $settings = $user->organization->assignment_config;
                    $q->orWhere(fn ($pending) => $pending->whereNull('assigned_to')->whereNotNull('delivered_at')
                        ->when($settings['project_ids'] ?? [],fn ($p) => $p->whereIn('campaign_id',\App\Models\Campaign::withoutGlobalScopes()->whereIn('project_id',$settings['project_ids'])->select('id')))
                        ->when($settings['campaign_ids'] ?? [],fn ($p) => $p->whereIn('campaign_id',$settings['campaign_ids'])));
                }
            });
    }
}
