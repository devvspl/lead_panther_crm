<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB, Hash};
use App\Models\{User, Organization, SalesTeam, SalesTeamMember};

class DeltekDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        DB::transaction(function () {
            $org=Organization::firstOrCreate(['name'=>'Deltek Demo'],['status'=>'active','auto_assign_enabled'=>true]);
            $users=[];
            foreach (array_merge(['Deltek','Brijesh'],array_map(fn($n)=>'SE'.$n,range(1,10))) as $index=>$name) {
                $email=strtolower($name).'@demo.leadpanther.test';
                $existing=User::where('email',$email)->first();
                if ($existing && ($existing->name!==$name || $existing->organization_id!==$org->id)) throw new \RuntimeException('Demo email already belongs to a different account: '.$email);
                $phone=(string)(919000010001+$index);
                while(User::where('phone',$phone)->when($existing,fn($q)=>$q->where('id','!=',$existing->id))->exists()) $phone=(string)((int)$phone+100);
                $user=User::firstOrCreate(['email'=>$email],['name'=>$name,'organization_id'=>$org->id,'phone'=>$phone,'password'=>Hash::make('password'),'status'=>'active','email_verified_at'=>now()]);
                // Existing accounts keep their password, profile, status and other roles.
                $user->assignRole($index===0 ? 'Builder/Developer' : ($index===1 ? 'Team Leader' : 'Executive'));
                $users[$name]=$user;
            }
            $team=SalesTeam::firstOrCreate(['ownable_type'=>Organization::class,'ownable_id'=>$org->id,'name'=>'Deltek Sales Team'],['leader_id'=>$users['Brijesh']->id]);
            if ($team->leader_id && $team->leader_id!==$users['Brijesh']->id) throw new \RuntimeException('Demo team already has another leader; existing setup was preserved.');
            $team->update(['leader_id'=>$users['Brijesh']->id]);
            foreach($users as $name=>$user) {
                if ($name==='Deltek') continue;
                SalesTeamMember::firstOrCreate(['sales_team_id'=>$team->id,'user_id'=>$user->id],['role'=>$name==='Brijesh'?'Team Leader':'Executive','is_active'=>true]);
            }
        });
    }
}
