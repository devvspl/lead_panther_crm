<?php

namespace App\Livewire\Concerns;

use App\Models\GeneralSetting;
use Livewire\WithPagination;

trait HasAdvancedTable
{
    use WithPagination;

    // Search & Filter State
    public string $search = '';
    public string $statusFilter = 'all';
    public array $columnFilters = [];

    // Sorting State
    public string $sortField = '';
    public string $sortDirection = 'desc';

    // Column Visibility Configuration
    public array $visibleColumns = [];

    // Row Selection State
    public array $selectedRows = [];
    public bool $selectAll = false;

    // Pagination
    public int $perPage = 15;

    // UI Popover States
    public bool $showColumnConfigModal = false;
    public bool $showFilterDrawer = false;

    /**
     * Mount / Initialize table configuration and persisted user preferences.
     */
    public function initializeHasAdvancedTable(): void
    {
        $this->loadColumnPreferences();
    }

    /**
     * Unique identifier for this table instance used for settings persistence.
     */
    public function getTableIdentifier(): string
    {
        return \Illuminate\Support\Str::snake(class_basename(static::class));
    }

    /**
     * Load column visibility preferences from database or fallback to defaults.
     */
    public function loadColumnPreferences(): void
    {
        $defaults = array_map(fn($col) => $col['key'], array_filter($this->tableColumns(), fn($col) => ($col['visible'] ?? true) !== false));

        if (auth()->check()) {
            $saved = GeneralSetting::getValue(auth()->id(), 'table_columns_' . $this->getTableIdentifier());
            if ($saved) {
                $decoded = json_decode($saved, true);
                if (is_array($decoded) && !empty($decoded)) {
                    $this->visibleColumns = $decoded;
                    return;
                }
            }
        }

        $this->visibleColumns = $defaults;
    }

    /**
     * Toggle visibility of a specific column.
     */
    public function toggleColumn(string $column): void
    {
        if (in_array($column, $this->visibleColumns)) {
            // Keep at least one column visible
            if (count($this->visibleColumns) > 1) {
                $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
            }
        } else {
            $this->visibleColumns[] = $column;
        }

        $this->persistColumnPreferences();
    }

    /**
     * Reset column visibility to default configuration.
     */
    public function resetColumns(): void
    {
        $defaults = array_map(fn($col) => $col['key'], array_filter($this->tableColumns(), fn($col) => ($col['visible'] ?? true) !== false));
        $this->visibleColumns = $defaults;
        $this->persistColumnPreferences();
    }

    /**
     * Save column visibility preferences to GeneralSetting table.
     */
    public function persistColumnPreferences(): void
    {
        if (auth()->check()) {
            GeneralSetting::setValue(
                auth()->id(),
                'table_columns_' . $this->getTableIdentifier(),
                json_encode(array_values($this->visibleColumns))
            );
        }
    }

    /**
     * Check if a column is currently visible.
     */
    public function isColumnVisible(string $column): bool
    {
        return in_array($column, $this->visibleColumns);
    }

    /**
     * Sort table by a specific field.
     */
    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    /**
     * Set quick-filter status pill.
     */
    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
        $this->selectedRows = [];
        $this->selectAll = false;
    }

    /**
     * Updated search query handler.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->selectedRows = [];
        $this->selectAll = false;
    }

    /**
     * Reset all custom filters.
     */
    public function resetTableFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->columnFilters = [];
        $this->resetPage();
        $this->selectedRows = [];
        $this->selectAll = false;
    }

    /**
     * Bulk selection toggle all handler.
     */
    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selectedRows = $this->getCurrentPageRowIds();
        } else {
            $this->selectedRows = [];
        }
    }

    /**
     * Toggle single row selection.
     */
    public function toggleSelectRow($id): void
    {
        $id = (string) $id;
        if (in_array($id, $this->selectedRows)) {
            $this->selectedRows = array_values(array_diff($this->selectedRows, [$id]));
        } else {
            $this->selectedRows[] = $id;
        }

        $pageIds = $this->getCurrentPageRowIds();
        $this->selectAll = !empty($pageIds) && count(array_intersect($pageIds, $this->selectedRows)) === count($pageIds);
    }

    /**
     * Helper to get IDs of rows on current page (can be overridden by components).
     */
    protected function getCurrentPageRowIds(): array
    {
        return [];
    }

    /**
     * Declarative column definitions array.
     * Override in components using this trait.
     */
    protected function tableColumns(): array
    {
        return [];
    }

    /**
     * Declarative quick filter status pills array.
     * Example: [['key' => 'all', 'label' => 'All'], ['key' => 'active', 'label' => 'Active']]
     */
    protected function quickFilters(): array
    {
        return [
            ['key' => 'all', 'label' => 'All'],
        ];
    }

    /**
     * Additional filter configuration for dropdown filter panel.
     */
    protected function filterConfig(): array
    {
        return [];
    }

    /**
     * Get active filter count for badge indicator.
     */
    public function getActiveFilterCountProperty(): int
    {
        $count = 0;
        if ($this->statusFilter !== 'all') {
            $count++;
        }
        foreach ($this->columnFilters as $val) {
            if (!empty($val) && $val !== 'all') {
                $count++;
            }
        }
        return $count;
    }
}
