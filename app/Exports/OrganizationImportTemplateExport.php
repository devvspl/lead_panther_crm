<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrganizationImportDataSheet extends BaseStyledExport
{
    public function __construct()
    {
        $sampleData = collect([
            [
                'name' => 'Prestige Estates Projects Ltd',
                'status' => 'active',
            ],
            [
                'name' => 'Apex Realty Advisory Partners',
                'status' => 'active',
            ],
            [
                'name' => 'Brigade Enterprises Limited',
                'status' => 'active',
            ],
            [
                'name' => 'Square Yards Advisory',
                'status' => 'active',
            ],
            [
                'name' => 'Platform Administration Group',
                'status' => 'active',
            ],
            [
                'name' => 'Skyline Developers LLP',
                'status' => 'suspended',
            ],
        ]);

        parent::__construct(
            $sampleData,
            'ORGANIZATION IMPORT DATA TEMPLATE',
            'Sample records included — Replace with actual organizations before uploading',
            ['Organization Name', 'Status'],
            ['name', 'status'],
            ['status'],
            [],
            false
        );
    }

    public function title(): string
    {
        return 'Organization Import Data';
    }

    public function styles(Worksheet $sheet)
    {
        parent::styles($sheet);
        $sheet->setAutoFilter('A4:B10');
        return [];
    }
}

class OrganizationImportInstructionsSheet extends BaseStyledExport
{
    public function __construct()
    {
        $instructions = collect([
            [
                'field' => 'Organization Name',
                'required' => 'REQUIRED',
                'format' => 'Text string (max 255 chars)',
                'example' => 'Prestige Estates Projects Ltd',
                'rules' => 'Mandatory company or organization name. Must be unique across the platform.',
            ],
            [
                'field' => 'Status',
                'required' => 'Optional',
                'format' => 'active | suspended',
                'example' => 'active',
                'rules' => 'Initial operational status. Allowed values: active or suspended. Defaults to active if left blank.',
            ],
        ]);

        parent::__construct(
            $instructions,
            'IMPORT INSTRUCTIONS & FIELD GUIDE',
            'Specifications and allowed values for organization batch imports',
            ['Field Name', 'Required Status', 'Expected Format', 'Example Value', 'Validation & Business Rules'],
            ['field', 'required', 'format', 'example', 'rules'],
            ['required'],
            [],
            false
        );
    }

    public function title(): string
    {
        return 'Instructions & Field Guide';
    }

    public function styles(Worksheet $sheet)
    {
        parent::styles($sheet);
        $sheet->setAutoFilter('A4:E6');
        return [];
    }
}

class OrganizationImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new OrganizationImportDataSheet(),
            new OrganizationImportInstructionsSheet(),
        ];
    }
}
