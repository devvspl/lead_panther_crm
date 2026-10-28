<?php

namespace App\Services;

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BaseStyledExport;
use Illuminate\Support\Collection;

class ExportService
{
    /**
     * Download styled Excel file.
     *
     * @param string $filename
     * @param array $headings
     * @param array|Collection $rows
     * @param string $title
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadExcel(string $filename, array $headings, $rows, string $title = 'Data Export')
    {
        $collection = $rows instanceof Collection ? $rows : collect($rows);

        $columns = [];
        if ($collection->isNotEmpty()) {
            $first = $collection->first();
            if (is_array($first)) {
                $columns = array_keys($first);
            }
        }

        return Excel::download(
            new BaseStyledExport(
                data: $collection,
                title: $title,
                headings: $headings,
                columns: $columns
            ),
            $filename
        );
    }
}
