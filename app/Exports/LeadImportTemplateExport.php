<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LeadImportDataSheet extends BaseStyledExport
{
    public function __construct()
    {
        $sampleData = collect([
            [
                'name' => 'Rajesh Kumar',
                'mobile' => '+919876543210',
                'email' => 'rajesh.kumar@gmail.com',
                'city' => 'Mumbai',
                'budget' => 15000000,
                'property_type' => '3BHK Luxury',
                'requirement' => 'Looking for ready to move project with club amenities',
            ],
            [
                'name' => 'Priya Sharma',
                'mobile' => '+919811223344',
                'email' => 'priya.sharma@yahoo.com',
                'city' => 'Pune',
                'budget' => 22000000,
                'property_type' => '2BHK Premium',
                'requirement' => 'Interested in 2BHK near metro station, possession in 6 months',
            ],
            [
                'name' => 'Amit Patel',
                'mobile' => '+919822334455',
                'email' => 'amit.patel@outlook.com',
                'city' => 'Bangalore',
                'budget' => 8500000,
                'property_type' => '1BHK Compact',
                'requirement' => 'Investment purpose in commercial / residential hub',
            ],
            [
                'name' => 'Ananya Verma',
                'mobile' => '+919833445566',
                'email' => 'ananya.v@gmail.com',
                'city' => 'Delhi NCR',
                'budget' => 35000000,
                'property_type' => '4BHK Penthouse',
                'requirement' => 'Prefers sea view high rise tower on higher floor',
            ],
            [
                'name' => 'Vikram Singh',
                'mobile' => '+919844556677',
                'email' => 'vikram.singh@gmail.com',
                'city' => 'Hyderabad',
                'budget' => 12000000,
                'property_type' => '3BHK Villa',
                'requirement' => 'Wants gated community with private garden',
            ],
            [
                'name' => 'Sneha Reddy',
                'mobile' => '+919855667788',
                'email' => 'sneha.reddy@gmail.com',
                'city' => 'Chennai',
                'budget' => 18000000,
                'property_type' => '2BHK Premium',
                'requirement' => 'Looking near IT park with 2 car parking slots',
            ],
            [
                'name' => 'Rohan Gupta',
                'mobile' => '+919866778899',
                'email' => 'rohan.gupta@gmail.com',
                'city' => 'Kolkata',
                'budget' => 9500000,
                'property_type' => '1BHK Standard',
                'requirement' => 'First time homebuyer, pre-approved home loan available',
            ],
            [
                'name' => 'Deepak Joshi',
                'mobile' => '+919877889900',
                'email' => 'deepak.joshi@gmail.com',
                'city' => 'Ahmedabad',
                'budget' => 28000000,
                'property_type' => '3BHK Duplex',
                'requirement' => 'Requires spacious deck balcony and servant room',
            ],
            [
                'name' => 'Neha Kapoor',
                'mobile' => '+919888990011',
                'email' => 'neha.k@gmail.com',
                'city' => 'Thane',
                'budget' => 16000000,
                'property_type' => '2BHK Deluxe',
                'requirement' => 'Flexible budget for premium location and layout',
            ],
            [
                'name' => 'Sanjay Mehta',
                'mobile' => '+919899001122',
                'email' => 'sanjay.m@gmail.com',
                'city' => 'Navi Mumbai',
                'budget' => 45000000,
                'property_type' => '4BHK Villa',
                'requirement' => 'Corporate buyer seeking luxury penthouse with private terrace',
            ],
        ]);

        parent::__construct(
            $sampleData,
            'LEAD IMPORT DATA TEMPLATE',
            'Sample 10 Records Included — Replace with actual leads before uploading',
            ['Full Name', 'Mobile Number', 'Email Address', 'City', 'Budget (INR)', 'Property Type', 'Requirement / Notes'],
            ['name', 'mobile', 'email', 'city', 'budget', 'property_type', 'requirement'],
            [],
            ['budget'],
            false
        );
    }

    public function title(): string
    {
        return 'Lead Import Data';
    }

    public function styles(Worksheet $sheet)
    {
        parent::styles($sheet);
        $sheet->setAutoFilter('A4:G14');
        return [];
    }
}

class LeadImportInstructionsSheet extends BaseStyledExport
{
    public function __construct()
    {
        $instructions = collect([
            [
                'field' => 'Full Name',
                'required' => 'Optional',
                'format' => 'Text string (max 255 chars)',
                'example' => 'Rajesh Kumar',
                'rules' => 'Primary buyer name. If left blank, will default to "CSV Imported Lead".',
            ],
            [
                'field' => 'Mobile Number',
                'required' => 'REQUIRED',
                'format' => '10-digit number or +91XXXXXXXXXX',
                'example' => '+919876543210',
                'rules' => 'Mandatory 10-digit contact number. Used for 90-day duplicate prevention.',
            ],
            [
                'field' => 'Email Address',
                'required' => 'Optional',
                'format' => 'Valid email address format',
                'example' => 'rajesh.kumar@gmail.com',
                'rules' => 'Primary buyer email address for notifications and follow-up communication.',
            ],
            [
                'field' => 'City',
                'required' => 'Optional',
                'format' => 'City / Location name string',
                'example' => 'Mumbai',
                'rules' => 'Location preference or current city of residence.',
            ],
            [
                'field' => 'Budget (INR)',
                'required' => 'Optional',
                'format' => 'Numeric value (plain numbers)',
                'example' => '15000000',
                'rules' => 'Target budget in Indian Rupees. Plain numbers only without currency symbols.',
            ],
            [
                'field' => 'Property Type',
                'required' => 'Optional',
                'format' => 'Text (1BHK / 2BHK / 3BHK / Villa)',
                'example' => '3BHK Luxury',
                'rules' => 'Preferred unit configuration, size, or property category.',
            ],
            [
                'field' => 'Requirement / Notes',
                'required' => 'Optional',
                'format' => 'Freeform text description',
                'example' => 'Ready to move in 6 months with club amenities',
                'rules' => 'Customer preferences, notes, or specific project requirements.',
            ],
        ]);

        parent::__construct(
            $instructions,
            'IMPORT INSTRUCTIONS & FIELD GUIDE',
            'Specifications, expected data formats, and 90-day duplicate protection rules',
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
        $sheet->setAutoFilter('A4:E11');
        return [];
    }
}

class LeadImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new LeadImportDataSheet(),
            new LeadImportInstructionsSheet(),
        ];
    }
}
