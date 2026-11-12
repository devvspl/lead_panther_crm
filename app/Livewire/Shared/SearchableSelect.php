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
    public array $options = [];
    public bool $showClearOption = true;
    public $clearValue = null;

    public function mount(
        string $model = '',
        string $searchColumn = 'name',
        string $valueColumn = 'id',
        string $displayColumn = 'name',
        string $placeholder = 'Select Option',
        string $roleFilter = '',
        bool $searchable = true,
        string $class = '',
        $value = null,
        $options = [],
        bool $showClearOption = true,
        $clearValue = null
    ): void {
        $this->model = $model;
        $this->searchColumn = $searchColumn;
        $this->valueColumn = $valueColumn;
        $this->displayColumn = $displayColumn;
        $this->placeholder = $placeholder;
        $this->roleFilter = $roleFilter;
        $this->searchable = $searchable;
        $this->class = $class;
        $this->showClearOption = $showClearOption;
        $this->clearValue = $clearValue;
        $this->value = ($value !== null && $value !== '') ? $value : $clearValue;
        $this->options = is_array($options) ? $options : (is_iterable($options) ? iterator_to_array($options) : []);
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
        $this->value = ($id !== null && $id !== '') ? $id : null;
    }

    public function clearSelection(): void
    {
        $this->value = $this->clearValue;
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
            if ($user && !$user->hasRole('Super Admin') && !$user->hasRole('super-admin')) {
                if ($this->model === \App\Models\User::class && $user->organization_id) {
                    $query->where('organization_id', $user->organization_id);
                }
                if ($this->model === \App\Models\SalesTeam::class) {
                    if ($user->hasRole('channel-partner') || $user->hasRole('Channel Partner')) {
                        $query->where(function ($q) use ($user) {
                            $q->where(function ($sub) use ($user) {
                                $sub->where('ownable_type', \App\Models\Organization::class)
                                    ->where('ownable_id', $user->organization_id);
                            })->orWhereHas('members', function ($sub) use ($user) {
                                $sub->where('user_id', $user->id);
                            });
                        });
                    } elseif ($user->organization_id) {
                        $query->where('ownable_type', \App\Models\Organization::class)
                              ->where('ownable_id', $user->organization_id);
                    }
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
        } elseif (!empty($this->options)) {
            $normalized = collect();
            foreach ($this->options as $k => $opt) {
                if (is_array($opt)) {
                    $val = (string)($opt[$this->valueColumn] ?? $opt['value'] ?? $opt['id'] ?? $k);
                    $label = (string)($opt[$this->displayColumn] ?? $opt['label'] ?? $opt['name'] ?? $val);
                } elseif (is_object($opt)) {
                    $val = (string)($opt->{$this->valueColumn} ?? $opt->id ?? $k);
                    $label = (string)($opt->{$this->displayColumn} ?? $opt->name ?? $val);
                } else {
                    $val = (string)$k;
                    $label = (string)$opt;
                }

                if ($val === '' && $label === $this->placeholder) {
                    continue;
                }

                $normalized->push((object)[
                    $this->valueColumn => $val,
                    $this->displayColumn => $label,
                ]);
            }

            if ($this->search !== '') {
                $term = mb_strtolower($this->search);
                $normalized = $normalized->filter(function ($item) use ($term) {
                    return str_contains(mb_strtolower($item->{$this->displayColumn}), $term);
                });
            }

            $total = $normalized->count();
            $items = $normalized->take($this->page * $this->perPage);
            $hasMore = ($total > $items->count());

            if ($this->value !== null && $this->value !== '') {
                $selected = $normalized->first(function ($item) {
                    return (string)$item->{$this->valueColumn} === (string)$this->value;
                });
                if ($selected) {
                    $selectedLabel = $selected->{$this->displayColumn};
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
