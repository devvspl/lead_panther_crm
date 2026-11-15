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
        })->whereHas('members',fn($m)=>$m->where('user_id',$user->id)->where('is_active',true));
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
            ->whereIn('assigned_to',self::executives($user)->select('id'));
    }
}
