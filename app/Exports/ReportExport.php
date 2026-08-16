<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Collection;

class ReportExport implements FromCollection, WithHeadings, WithMapping
{
    protected Collection $data;
    protected array $headings;
    protected array $columns;

    /**
     * Config-driven export constructor.
     * @param Collection $data
     * @param array $headings ['Header 1', 'Header 2', ...]
     * @param array $columns ['field_1', 'field_2', ...] or callable map
     */
    public function __construct(Collection $data, array $headings, array $columns)
    {
        $this->data = $data;
        $this->headings = $headings;
        $this->columns = $columns;
    }

    public function collection(): Collection
    {
        return $this->data;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function map($row): array
    {
        $mapped = [];
        foreach ($this->columns as $col) {
            if (is_callable($col)) {
                $mapped[] = $col($row);
            } elseif (is_object($row)) {
                $mapped[] = data_get($row, $col, '');
            } elseif (is_array($row)) {
                $mapped[] = $row[$col] ?? '';
            } else {
                $mapped[] = '';
            }
        }
        return $mapped;
    }
}
