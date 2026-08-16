<?php

namespace App\Livewire\Shared;

use Livewire\Component;
use App\Models\Lead;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Support\LeadPresenter;

class GlobalSearch extends Component
{
    public string $search = '';

    public function clearSearch(): void
    {
        $this->search = '';
    }

    public function render()
    {
        $leads = collect();
        $clients = collect();
        $projects = collect();
        $people = collect();

        $query = trim($this->search);

        if (strlen($query) >= 2) {
            // 1. Search Leads (respects ClientScoped & LeadPresenter masking)
            $rawLeads = Lead::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('mobile', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('lead_code', 'like', "%{$query}%");
            })->take(5)->get();

            $leads = $rawLeads->map(function ($lead) {
                return LeadPresenter::present($lead, auth()->user());
            });

            // 2. Search Clients
            $clients = Client::where('name', 'like', "%{$query}%")->take(5)->get();

            // 3. Search Projects (respects ClientScoped)
            $projects = Project::where('name', 'like', "%{$query}%")->take(5)->get();

            // 4. Search People / Users
            if (auth()->user()?->hasAnyRole(['Super Admin', 'Builder', 'Channel Partner', 'Account Manager'])) {
                $people = User::where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->take(5)
                    ->get();
            }
        }

        return view('livewire.shared.global-search', [
            'leads' => $leads,
            'clients' => $clients,
            'projects' => $projects,
            'people' => $people,
        ]);
    }
}
