<?php

namespace App\Livewire\Shared;

use Livewire\Component;
use Livewire\Attributes\Modelable;

class SearchableSelect extends Component
{
    #[Modelable]
    public $value = null;

    public string $model = '';
    public string $searchColumn = 'name';
    public string $valueColumn = 'id';
    public string $displayColumn = 'name';
    public string $placeholder = 'Select Option';
    public string $roleFilter = '';
    public bool $searchable = true;
    public string $class = '';
    public string $search = '';
    public int $page = 1;
    public int $perPage = 20;

    public function mount(
        string $model = '',
        string $searchColumn = 'name',
        string $valueColumn = 'id',
        string $displayColumn = 'name',
        string $placeholder = 'Select Option',
        string $roleFilter = '',
        bool $searchable = true,
        string $class = '',
        $value = null
    ): void {
        $this->model = $model;
        $this->searchColumn = $searchColumn;
        $this->valueColumn = $valueColumn;
        $this->displayColumn = $displayColumn;
        $this->placeholder = $placeholder;
        $this->roleFilter = $roleFilter;
        $this->searchable = $searchable;
        $this->class = $class;
        $this->value = $value;
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function loadMore(): void
    {
        $this->page++;
    }

    public function selectOption($id): void
    {
        $this->value = $id;
    }

    public function clearSelection(): void
    {
        $this->value = null;
        $this->search = '';
        $this->page = 1;
    }

    public function render()
    {
        $items = collect();
        $hasMore = false;
        $selectedLabel = null;

        if ($this->model && class_exists($this->model)) {
            $query = $this->model::query();

            // Org Scoping for User model if non-super-admin user has organization_id
            $user = auth()->user();
            if ($user && !$user->hasRole('Super Admin')) {
                if ($this->model === \App\Models\User::class && $user->organization_id) {
                    $query->where('organization_id', $user->organization_id);
                }
            }

            if ($this->roleFilter) {
                $roles = array_map('trim', explode(',', $this->roleFilter));
                $query->where(function ($q) use ($roles) {
                    $q->whereHas('roles', function ($rq) use ($roles) {
                        $rq->whereIn('name', $roles);
                    });
                    if (in_array('sales-executive', $roles) || in_array('Sales Executive', $roles)) {
                        $q->orWhereHas('salesTeamMembers', function ($sq) {
                            $sq->where('is_active', true);
                        });
                    }
                });
            }

            if ($this->search !== '') {
                $query->where($this->searchColumn, 'like', '%' . $this->search . '%');
            }

            $total = (clone $query)->count();

            $items = $query->orderBy($this->displayColumn, 'asc')
                ->take($this->page * $this->perPage)
                ->get();

            $hasMore = ($total > $items->count());

            if ($this->value !== null && $this->value !== '') {
                $selectedItem = $this->model::where($this->valueColumn, $this->value)->first();
                if ($selectedItem) {
                    $selectedLabel = $selectedItem->{$this->displayColumn};
                }
            }
        }

        return view('livewire.shared.searchable-select', [
            'items' => $items,
            'hasMore' => $hasMore,
            'selectedLabel' => $selectedLabel,
        ]);
    }
}
