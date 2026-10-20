<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\SalesTeam;
use App\Models\SalesTeamMember;
use Spatie\Permission\Models\Role;

class AccountAccessFixSeeder extends Seeder
{
    /**
     * Run the database seeds to fix account access, role assignments, and pivot links.
     */
    public function run(): void
    {
        // 1. Ensure all Spatie roles exist in standard Title Case
        $canonicalRoles = [
            'Super Admin',
            'Builder',
            'Channel Partner',
            'Sales Executive',
            'Account Manager',
            'Client',
        ];

        foreach ($canonicalRoles as $title) {
            Role::firstOrCreate(['name' => $title, 'guard_name' => 'web']);
        }

        // a. ORGANIZATION STATUS: Confirm all organizations are active
        Organization::query()->update(['status' => 'active']);

        // b. USER STATUS & ROLE ASSIGNMENT
        $users = User::all();

        foreach ($users as $user) {
            // Confirm active user status
            if ($user->status !== 'active') {
                $user->status = 'active';
                $user->save();
            }

            $titleRole = null;

            $name = $user->name;
            $email = strtolower($user->email);

            if ($email === 'admin@leadpanther.com' || str_contains($name, 'Super Admin')) {
                $titleRole = 'Super Admin';
            } elseif (str_contains($name, 'Builder Manager') || str_starts_with($email, 'builder')) {
                $titleRole = 'Builder';
            } elseif (str_contains($name, 'Partner') || str_contains($email, 'cp.com')) {
                $titleRole = 'Channel Partner';
            } elseif (str_contains($name, 'Sales Exec') || str_starts_with($email, 'sales')) {
                $titleRole = 'Sales Executive';
            } elseif (str_contains($name, 'Account Manager') || str_starts_with($email, 'accountmgr')) {
                $titleRole = 'Account Manager';
            } elseif (str_contains($name, 'Client User') || str_starts_with($email, 'client')) {
                $titleRole = 'Client';
            }

            if ($titleRole) {
                $user->syncRoles([$titleRole]);
            }

            // c. CLIENT_USERS PIVOT
            if ($titleRole === 'Client' || $user->hasRole('Client')) {
                $orgId = $user->organization_id ?? Organization::first()?->id;

                if ($orgId) {
                    $client = Client::firstOrCreate(
                        ['organization_id' => $orgId],
                        [
                            'name' => 'Client Org ' . $orgId,
                            'billing_email' => $user->email,
                            'status' => 'active',
                        ]
                    );

                    $hasPrimary = ClientUser::where('client_id', $client->id)->exists();

                    ClientUser::firstOrCreate(
                        [
                            'client_id' => $client->id,
                            'user_id' => $user->id,
                        ],
                        [
                            'is_primary_contact' => !$hasPrimary,
                        ]
                    );
                }
            }

            // d. SALES TEAM MEMBERSHIP
            if ($titleRole === 'Sales Executive' || $user->hasRole('Sales Executive')) {
                $orgId = $user->organization_id ?? Organization::first()?->id;

                if ($orgId) {
                    $salesTeam = SalesTeam::firstOrCreate(
                        [
                            'ownable_type' => Organization::class,
                            'ownable_id' => $orgId,
                        ],
                        [
                            'name' => 'Sales Team Org ' . $orgId,
                        ]
                    );

                    SalesTeamMember::firstOrCreate(
                        [
                            'sales_team_id' => $salesTeam->id,
                            'user_id' => $user->id,
                        ],
                        [
                            'role' => 'executive',
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }
}
