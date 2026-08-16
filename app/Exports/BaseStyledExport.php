<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class BaseStyledExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithCustomStartCell
{
    protected Collection $data;
    protected string $title;
    protected string $subtitle;
    protected array $headings;
    protected array $columns;
    protected array $statusColumns;
    protected array $currencyColumns;
    protected bool $hasTotals;

    /**
     * @param Collection $data
     * @param string $title E.g. 'Credit Transaction History'
     * @param string $subtitle E.g. 'Exported 14 Aug 2026 | Filtered: Client = Royal Realty'
     * @param array $headings ['Date', 'Client', 'Type', 'Amount']
     * @param array $columns ['created_at', 'client_name', 'transaction_type', 'amount']
     * @param array $statusColumns Column keys that should receive status badge coloring
     * @param array $currencyColumns Column keys that should be formatted as currency
     * @param bool $hasTotals Whether to calculate and append a summary/total row
     */
    public function __construct(
        Collection $data,
        string $title,
        string $subtitle = '',
        array $headings = [],
        array $columns = [],
        array $statusColumns = [],
        array $currencyColumns = [],
        bool $hasTotals = false
    ) {
        $this->data = $data;
        $this->title = $title;
        $this->subtitle = $subtitle ?: 'Exported ' . now()->format('d M Y, H:i T');
        $this->headings = $headings;
        $this->columns = $columns;
        $this->statusColumns = $statusColumns;
        $this->currencyColumns = $currencyColumns;
        $this->hasTotals = $hasTotals;
    }

    public function startCell(): string
    {
        return 'A4';
    }

    public function title(): string
    {
        return substr($this->title, 0, 31);
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

    public function styles(Worksheet $sheet)
    {
        $colCount = max(count($this->headings), 1);
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);
        $rowCount = count($this->data);
        $headerRow = 4;
        $firstDataRow = 5;
        $lastDataRow = $firstDataRow + $rowCount - 1;

        // 1. Row 1: Merged Header Masthead Banner
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->setCellValue('A1', 'LEAD PANTHER CRM');
        $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 14],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '111827']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);

        // 2. Row 2: Title and Filter Metadata
        $sheet->mergeCells("A2:{$lastColLetter}2");
        $sheet->setCellValue('A2', "{$this->title} — {$this->subtitle}");
        $sheet->getStyle("A2:{$lastColLetter}2")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '475569'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F8FAFC']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);

        // 3. Row 3: Spacer Row Height
        $sheet->getRowDimension(3)->setRowHeight(10);

        // 4. Row 4: Column Headers
        $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '0F172A'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F1F5F9']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'CBD5E1']],
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(26);

        // Freeze Pane right under header row
        $sheet->freezePane('A5');

        // 5. Data Rows Formatting
        if ($rowCount > 0) {
            for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                $isEven = ($r % 2 === 0);
                $bgColor = $isEven ? 'F8FAFC' : 'FFFFFF';
                
                $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $bgColor]],
                    'borders' => [
                        'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
                    ],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($r)->setRowHeight(22);

                // Status Column Cell Palette Coloring
                $dataIdx = $r - $firstDataRow;
                $rowItem = $this->data->values()->get($dataIdx);

                foreach ($this->statusColumns as $sColKey) {
                    $colIndex = array_search($sColKey, array_values($this->columns));
                    if ($colIndex !== false) {
                        $cellLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                        $val = strtolower(trim((string)(is_object($rowItem) ? data_get($rowItem, $sColKey) : ($rowItem[$sColKey] ?? ''))));

                        $badgeBg = 'F1F5F9';
                        $badgeText = '475569';

                        if (in_array($val, ['approved', 'active', 'completed', 'closed_won', 'recharge', 'refund', 'yes'])) {
                            $badgeBg = 'DCFCE7';
                            $badgeText = '15803D';
                        } elseif (in_array($val, ['pending', 'under_review', 'reserve', 'assigned'])) {
                            $badgeBg = 'FEF3C7';
                            $badgeText = 'B45309';
                        } elseif (in_array($val, ['rejected', 'failed', 'suspended', 'missed', 'breached', 'deduct', 'no'])) {
                            $badgeBg = 'FEE2E2';
                            $badgeText = 'B91C1C';
                        }

                        $sheet->getStyle("{$cellLetter}{$r}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => $badgeText]],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $badgeBg]],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        ]);
                    }
                }

                // Currency / Number Formatting
                foreach ($this->currencyColumns as $cColKey) {
                    $colIndex = array_search($cColKey, array_values($this->columns));
                    if ($colIndex !== false) {
                        $cellLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                        $sheet->getStyle("{$cellLetter}{$r}")->getNumberFormat()->setFormatCode('"₹"#,##0.00');
                    }
                }
            }
        }

        // 6. Optional Footer / Summary Total Row
        if ($this->hasTotals && $rowCount > 0) {
            $totalRow = $lastDataRow + 1;
            $sheet->setCellValue("A{$totalRow}", 'TOTAL SUMMARY');

            foreach ($this->currencyColumns as $cColKey) {
                $colIndex = array_search($cColKey, array_values($this->columns));
                if ($colIndex !== false) {
                    $cellLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                    $sheet->setCellValue("{$cellLetter}{$totalRow}", "=SUM({$cellLetter}{$firstDataRow}:{$cellLetter}{$lastDataRow})");
                    $sheet->getStyle("{$cellLetter}{$totalRow}")->getNumberFormat()->setFormatCode('"₹"#,##0.00');
                }
            }

            $sheet->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '0F172A'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'E2E8F0']],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '0F172A']],
                    'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0F172A']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension($totalRow)->setRowHeight(26);
        }

        return [];
    }
}
