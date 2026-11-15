<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\{Role, Permission};
class TeamLeaderRoleSeeder extends Seeder {
    public function run(): void {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions=['view-leads','assign-leads','view-reports','view-team-leads','assign-team-leads','manage-team-members','view-team-reports','view-team-followups','manage-teams','assign-team-leaders'];
        foreach($permissions as $name) Permission::firstOrCreate(['name'=>$name,'guard_name'=>'web']);
        Role::firstOrCreate(['name'=>'Team Leader','guard_name'=>'web'])->givePermissionTo(array_diff($permissions,['manage-teams','assign-team-leaders']));
        foreach(['Builder/Developer','Builder','builder'] as $name) {
            $role=$name==='Builder/Developer' ? Role::firstOrCreate(['name'=>$name,'guard_name'=>'web']) : Role::where('name',$name)->where('guard_name','web')->first();
            $role?->givePermissionTo(['manage-teams','assign-team-leaders','manage-team-members']);
        }
        foreach(Role::whereIn('name',['Super Admin','super-admin'])->get() as $role) $role->givePermissionTo($permissions);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
