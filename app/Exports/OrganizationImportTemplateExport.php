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
                'user_name' => 'Rajesh Sharma',
                'user_email' => 'rajesh@prestige.com',
                'user_phone' => '+919876543210',
                'user_role' => 'Builder/Developer',
            ],
            [
                'name' => 'Apex Realty Advisory Partners',
                'status' => 'active',
                'user_name' => 'Pooja Mehta',
                'user_email' => 'pooja@apexrealty.com',
                'user_phone' => '+919812345678',
                'user_role' => 'Broker',
            ],
            [
                'name' => 'Brigade Enterprises Limited',
                'status' => 'active',
                'user_name' => 'Anil Verma',
                'user_email' => 'anil@brigade.com',
                'user_phone' => '+919845612345',
                'user_role' => 'Channel Partner',
            ],
            [
                'name' => 'Square Yards Advisory',
                'status' => 'active',
                'user_name' => 'Vikram Singh',
                'user_email' => 'vikram@squareyards.com',
                'user_phone' => '+919822334455',
                'user_role' => 'Executive',
            ],
            [
                'name' => 'Platform Administration Group',
                'status' => 'active',
                'user_name' => 'Admin Contact',
                'user_email' => 'contact@platform.com',
                'user_phone' => '+919800112233',
                'user_role' => 'Builder/Developer',
            ],
            [
                'name' => 'Skyline Developers LLP',
                'status' => 'suspended',
                'user_name' => 'Sameer Khan',
                'user_email' => 'sameer@skyline.com',
                'user_phone' => '+919877889900',
                'user_role' => 'Builder/Developer',
            ],
        ]);

        parent::__construct(
            $sampleData,
            'ORGANIZATION IMPORT DATA TEMPLATE',
            'Sample records included — Replace with actual organizations and users before uploading',
            ['Organization Name', 'Status', 'User Name', 'User Email', 'User Phone', 'User Role'],
            ['name', 'status', 'user_name', 'user_email', 'user_phone', 'user_role'],
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
        $sheet->setAutoFilter('A4:F10');
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
            [
                'field' => 'User Name',
                'required' => 'Optional',
                'format' => 'Text string (max 255 chars)',
                'example' => 'Rajesh Sharma',
                'rules' => 'Primary user contact person. If omitted, defaults to Organization Name.',
            ],
            [
                'field' => 'User Email',
                'required' => 'Optional',
                'format' => 'Valid email address',
                'example' => 'rajesh@prestige.com',
                'rules' => 'Login email for the primary user. If omitted, auto-generates a unique email.',
            ],
            [
                'field' => 'User Phone',
                'required' => 'Optional',
                'format' => 'Phone / Mobile string',
                'example' => '+919876543210',
                'rules' => 'Contact mobile number for primary user.',
            ],
            [
                'field' => 'User Role',
                'required' => 'Optional',
                'format' => 'Builder/Developer | Broker | Channel Partner | Executive',
                'example' => 'Builder/Developer',
                'rules' => 'Spatie permissions role assigned to user. Defaults to Builder/Developer.',
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
        $sheet->setAutoFilter('A4:E10');
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
