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
    protected bool $financialColumnsRestricted = false;

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
        if (!\App\Support\FinancialVisibility::allowed()) {
            foreach ($headings as $index => $heading) {
                $key = strtolower(str_replace([' ', '/'], '_', (string) $heading));
                $column = $columns[$index] ?? null;
                if (\App\Support\FinancialVisibility::sensitive($key) || (is_string($column) && \App\Support\FinancialVisibility::sensitive($column)) || preg_match('/booked_amount|payments_received|revenue|outstanding|ad_spend/i', $key)) {
                    $this->financialColumnsRestricted = true;
                    unset($headings[$index], $columns[$index]);
                }
            }
            $headings = array_values($headings);
            $columns = array_values($columns);
            $currencyColumns = [];
        }
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
        if ($this->financialColumnsRestricted && empty($this->columns)) return [];
        if (empty($this->columns)) {
            if (is_array($row)) {
                return array_values(\App\Support\FinancialVisibility::allowed() ? $row : \App\Support\FinancialVisibility::redact($row));
            }
            if ($row instanceof Collection) {
                return $row->values()->toArray();
            }
        }

        $mapped = [];
        foreach ($this->columns as $col) {
            $val = '';
            if ($col instanceof \Closure || (!is_string($col) && is_callable($col))) {
                $val = $col($row);
            } elseif (is_object($row)) {
                $val = data_get($row, $col, '');
            } elseif (is_array($row)) {
                $val = $row[$col] ?? '';
            }

            if (is_array($val) || (is_object($val) && !method_exists($val, '__toString'))) {
                $val = json_encode($val);
            }

            $mapped[] = $val;
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

                $statusColIndices = [];
                foreach ($this->statusColumns as $sColKey) {
                    if (is_int($sColKey)) {
                        $statusColIndices[] = $sColKey;
                        continue;
                    }
                    $found = false;
                    foreach ($this->columns as $idx => $cVal) {
                        if ((is_string($cVal) && strtolower($cVal) === strtolower($sColKey)) || (string)$idx === (string)$sColKey) {
                            $statusColIndices[] = (int) $idx;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        foreach ($this->headings as $idx => $heading) {
                            $normH = strtolower(str_replace([' ', '_', '-', '/'], '', (string)$heading));
                            $normK = strtolower(str_replace([' ', '_', '-', '/'], '', (string)$sColKey));
                            if ($normH === $normK || str_contains($normH, $normK)) {
                                $statusColIndices[] = (int) $idx;
                                break;
                            }
                        }
                    }
                }

                // Auto-detect type/status column if none matched
                if (empty($statusColIndices)) {
                    foreach ($this->headings as $idx => $heading) {
                        $normH = strtolower(trim((string)$heading));
                        if (in_array($normH, ['status', 'type', 'transaction type', 'tx type'])) {
                            $statusColIndices[] = (int) $idx;
                        }
                    }
                }

                foreach (array_unique($statusColIndices) as $colIndex) {
                    $cellLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                    $cell = $sheet->getCell("{$cellLetter}{$r}");
                    $cellVal = trim((string) $cell->getValue());
                    $norm = strtolower(str_replace([' ', '_', '-'], '', $cellVal));

                    $badgeBg = 'F1F5F9';
                    $badgeText = '475569';

                    if (in_array($norm, ['approved', 'active', 'completed', 'closedwon', 'recharge', 'refund', 'leadrefund', 'yes', 'verified'])) {
                        $badgeBg = 'DCFCE7';
                        $badgeText = '15803D';
                    } elseif (in_array($norm, ['pending', 'underreview', 'reserve', 'assigned', 'processing'])) {
                        $badgeBg = 'FEF3C7';
                        $badgeText = 'B45309';
                    } elseif (in_array($norm, ['leadconsumption', 'consumption', 'deduct', 'rejected', 'failed', 'suspended', 'missed', 'breached', 'no'])) {
                        $badgeBg = 'FEE2E2';
                        $badgeText = 'B91C1C';
                    } elseif (in_array($norm, ['reconciliation', 'adjustment', 'creditadjustment', 'reconciled'])) {
                        $badgeBg = 'EDE9FE';
                        $badgeText = '6D28D9';
                    }

                    $sheet->getStyle("{$cellLetter}{$r}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => $badgeText]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $badgeBg]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                // Nature Color Formatting for Financial/Credit Movements (+ for additions, - for deductions)
                for ($colIdx = 0; $colIdx < $colCount; $colIdx++) {
                    $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
                    $cCell = $sheet->getCell("{$cLetter}{$r}");
                    $cVal = trim((string) $cCell->getValue());
                    $heading = $this->headings[$colIdx] ?? '';
                    $headingNorm = strtolower(str_replace([' ', '_', '-', '/'], '', (string)$heading));
                    $isMovementHeading = in_array($headingNorm, ['creditusedadded', 'creditinout', 'change', 'movement', 'inout']);

                    if (str_starts_with($cVal, '+') || ($isMovementHeading && is_numeric($cVal) && ((float)$cVal > 0 || (is_object($rowItem) && in_array(data_get($rowItem, 'transaction_type'), ['recharge', 'refund', 'lead_refund', 'opening_credit']))))) {
                        // Inflows / Additions: Emerald Green badge with explicit + formatting
                        $dispVal = '+' . ltrim($cVal, '+');
                        $sheet->setCellValueExplicit("{$cLetter}{$r}", $dispVal, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->getStyle("{$cLetter}{$r}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => '15803D'], 'size' => 10],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'DCFCE7']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        ]);
                    } elseif (str_starts_with($cVal, '-') || ($isMovementHeading && is_numeric($cVal) && (is_object($rowItem) && in_array(data_get($rowItem, 'transaction_type'), ['lead_consumption', 'deduct', 'reserve'])))) {
                        // Outflows / Deductions: Rose / Red badge with explicit - formatting
                        $dispVal = '-' . ltrim($cVal, '-');
                        $sheet->setCellValueExplicit("{$cLetter}{$r}", $dispVal, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->getStyle("{$cLetter}{$r}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => 'B91C1C'], 'size' => 10],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FEE2E2']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        ]);
                    }
                }

                // Align codes and numbers cleanly
                foreach ($this->headings as $hIdx => $hText) {
                    $hNorm = strtolower(str_replace([' ', '_', '-'], '', (string)$hText));
                    $hLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($hIdx + 1);
                    if (in_array($hNorm, ['leadcode', 'leadreference', 'code', 'txid', 'id', 'date', 'datetime', 'dateandtime', 'timestamp'])) {
                        $sheet->getStyle("{$hLetter}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    } elseif (in_array($hNorm, ['creditbefore', 'creditafter', 'balance'])) {
                        $sheet->getStyle("{$hLetter}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                }

                // Currency / Number Formatting
                foreach ($this->currencyColumns as $cColKey) {
                    $colIndex = array_search($cColKey, array_values($this->columns), true);
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
                $colIndex = array_search($cColKey, array_values($this->columns), true);
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
