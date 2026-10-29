<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Concerns\HasAdvancedTable;
use App\Models\Organization;
use App\Models\User;
use App\Models\CreditWallet;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\OrganizationImportTemplateExport;
use Throwable;

class OrganizationManager extends Component
{
    use HasAdvancedTable;
    use WithFileUploads;

    public string $name = '';
    public string $status = 'active';

    public ?int $editingOrganizationId = null;

    // Organization View/Edit Details Modal Properties
    public bool $showOrgDetailsModal = false;
    public ?int $editingOrgId = null;
    public ?Organization $editingOrg = null;
    public string $orgDetailsMode = 'view'; // 'view' | 'edit'
    public string $editName = '';
    public string $editStatus = 'active';

    // Register Organization Modal (Create & Import)
    public bool $showCreateOrgModal = false;
    public string $orgModalTab = 'create'; // 'create' | 'import'

    // Bulk Import Properties
    public $importFile = null;
    public array $importRows = [];
    public array $importErrors = [];
    public int $totalImportRows = 0;

    // Organization Users Management Modal
    public bool $showUserModal = false;
    public bool $showUserOffcanvas = false; // Backward compatibility alias
    public ?int $selectedOrgId = null;
    public ?Organization $selectedOrg = null;

    public string $newUserName = '';
    public string $newUserEmail = '';
    public string $newUserRole = 'builder';
    public string $newUserPassword = '';
    public ?string $generatedInviteLink = null;

    public function mount(): void
    {
        $this->loadColumnPreferences();
    }

    public function tableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'ID', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono text-muted text-[11px]'],
            ['key' => 'name', 'label' => 'Client Name', 'type' => 'org_name', 'sortable' => true, 'priority' => 1, 'class' => 'font-bold text-ink'],
            ['key' => 'users_count', 'label' => 'Users Count', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-bold text-ink font-mono'],
            ['key' => 'clients_count', 'label' => 'Projects Count', 'type' => 'text', 'sortable' => true, 'priority' => 2, 'class' => 'font-mono text-muted'],
            [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'badge',
                'sortable' => true,
                'priority' => 1,
                'badgeMap' => [
                    'active' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                    'suspended' => 'bg-red-50 text-red-700 border border-red-200',
                ]
            ],
            ['key' => 'created_at', 'label' => 'Created', 'type' => 'date', 'sortable' => true, 'priority' => 2],
            ['key' => 'actions', 'label' => 'Actions', 'type' => 'org_actions', 'priority' => 1],
        ];
    }

    public function quickFilters(): array
    {
        return [
            ['key' => 'all', 'label' => 'All Clients'],
            ['key' => 'active', 'label' => 'Active'],
            ['key' => 'suspended', 'label' => 'Suspended'],
        ];
    }

    public function openRegisterOrgModal(string $tab = 'create'): void
    {
        $this->orgModalTab = in_array($tab, ['create', 'import']) ? $tab : 'create';
        $this->resetImportState();
        $this->showCreateOrgModal = true;
        $this->dispatch('open-modal', 'register-organization-modal');
    }

    public function closeRegisterOrgModal(): void
    {
        $this->showCreateOrgModal = false;
        $this->resetImportState();
        $this->dispatch('close-modal', 'register-organization-modal');
    }

    public function resetImportState(): void
    {
        $this->reset(['importFile', 'importRows', 'importErrors', 'totalImportRows']);
    }

    public function createOrganization(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'status' => 'nullable|in:active,suspended',
        ]);

        Organization::create([
            'name' => $this->name,
            'status' => $this->status ?: 'active',
        ]);

        $this->reset(['name', 'status']);
        $this->showCreateOrgModal = false;
        $this->dispatch('close-modal', 'register-organization-modal');
        $this->dispatch('toast', type: 'success', message: 'Organization created successfully.');
    }

    public function updatedImportFile(): void
    {
        $this->importRows = [];
        $this->importErrors = [];
        $this->totalImportRows = 0;

        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        try {
            $path = $this->importFile->getRealPath();
            $extension = strtolower($this->importFile->getClientOriginalExtension() ?: pathinfo($this->importFile->getClientOriginalName(), PATHINFO_EXTENSION) ?: $this->importFile->extension());
            $rows = [];

            if (in_array($extension, ['xlsx', 'xls'])) {
                // On Windows, Livewire temp paths can be over 260 chars and fail in ZipArchive. Copy to clean temp path.
                $cleanTempPath = tempnam(sys_get_temp_dir(), 'org_import_') . '.' . $extension;
                copy($path, $cleanTempPath);

                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($cleanTempPath);
                $worksheet = $spreadsheet->sheetNameExists('Organization Import Data')
                    ? $spreadsheet->getSheetByName('Organization Import Data')
                    : $spreadsheet->getSheet(0);

                $rawRows = $worksheet->toArray();
                @unlink($cleanTempPath);

                foreach ($rawRows as $r) {
                    if (is_array($r) && !empty(array_filter($r, fn($v) => $v !== null && trim((string) $v) !== ''))) {
                        $rows[] = array_map(fn($v) => mb_convert_encoding(trim((string) ($v ?? '')), 'UTF-8', 'UTF-8'), $r);
                    }
                }
            } else {
                $handle = fopen($path, 'r');
                if ($handle !== false) {
                    while (($r = fgetcsv($handle)) !== false) {
                        if (is_array($r) && !empty(array_filter($r, fn($v) => $v !== null && trim((string) $v) !== ''))) {
                            $rows[] = array_map(fn($v) => mb_convert_encoding(trim((string) ($v ?? '')), 'UTF-8', 'UTF-8'), $r);
                        }
                    }
                    fclose($handle);
                }
            }

            if (empty($rows)) {
                $this->dispatch('toast', type: 'error', message: 'The uploaded file is empty.');
                return;
            }

            // Detect header row (handling potential banner/instruction rows)
            $headerRowIndex = 0;
            $foundHeader = false;

            foreach ($rows as $idx => $r) {
                $cells = array_map(fn($c) => strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', (string) $c))), $r);
                $hasName = false;

                foreach ($cells as $c) {
                    if (in_array($c, ['name', 'organizationname', 'orgname', 'company', 'companyname', 'clients'])) {
                        $hasName = true;
                        break;
                    }
                }

                if ($hasName) {
                    $headerRowIndex = $idx;
                    $foundHeader = true;
                    break;
                }
            }

            // Fallback: search for row containing name in text
            if (!$foundHeader) {
                foreach ($rows as $idx => $r) {
                    $rowText = strtolower(implode(' ', array_filter($r)));
                    if (str_contains($rowText, 'clients') || str_contains($rowText, 'name')) {
                        $headerRowIndex = $idx;
                        $foundHeader = true;
                        break;
                    }
                }
            }

            $headerRow = $rows[$headerRowIndex] ?? [];
            $nameIdx = null;
            $statusIdx = null;
            $userNameIdx = null;
            $userEmailIdx = null;
            $userPhoneIdx = null;
            $userRoleIdx = null;

            foreach ($headerRow as $colIdx => $colName) {
                $colNormalized = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', (string) $colName)));
                if (in_array($colNormalized, ['organizationname', 'orgname', 'company', 'companyname', 'clientname', 'clientsname'])) {
                    $nameIdx = $colIdx;
                } elseif (in_array($colNormalized, ['username', 'contactperson', 'fullname', 'contactname', 'primaryuser', 'clientcontact'])) {
                    $userNameIdx = $colIdx;
                } elseif ($colNormalized === 'name' || $colNormalized === 'clients') {
                    if ($nameIdx === null) {
                        $nameIdx = $colIdx;
                    } else {
                        $userNameIdx = $colIdx;
                    }
                } elseif (in_array($colNormalized, ['status', 'initialstatus', 'state'])) {
                    $statusIdx = $colIdx;
                } elseif (in_array($colNormalized, ['useremail', 'email', 'loginemail', 'contactemail', 'emailaddress'])) {
                    $userEmailIdx = $colIdx;
                } elseif (in_array($colNormalized, ['userphone', 'phone', 'phonenumber', 'mobile', 'mobilenumber', 'contactphone'])) {
                    $userPhoneIdx = $colIdx;
                } elseif (in_array($colNormalized, ['userrole', 'role', 'assignedrole', 'userroles'])) {
                    $userRoleIdx = $colIdx;
                }
            }

            // Fallback column indices if positional/generic
            if ($nameIdx === null && isset($headerRow[0])) $nameIdx = 0;
            if ($statusIdx === null && isset($headerRow[1])) $statusIdx = 1;
            if ($userNameIdx === null && isset($headerRow[2])) $userNameIdx = 2;
            if ($userEmailIdx === null && isset($headerRow[3])) $userEmailIdx = 3;
            if ($userPhoneIdx === null && isset($headerRow[4])) $userPhoneIdx = 4;
            if ($userRoleIdx === null && isset($headerRow[5])) $userRoleIdx = 5;

            $dataRows = array_slice($rows, $headerRowIndex + 1);
            $this->totalImportRows = count($dataRows);

            if ($this->totalImportRows === 0) {
                $this->dispatch('toast', type: 'warning', message: 'No data rows found below the header row.');
                return;
            }

            $seenNamesInBatch = [];
            $seenEmailsInBatch = [];

            foreach ($dataRows as $offset => $row) {
                $rowNum = $headerRowIndex + 1 + $offset + 1; // 1-based actual row number in file
                $rawName = isset($row[$nameIdx]) ? trim((string) $row[$nameIdx]) : '';
                $rawStatus = ($statusIdx !== null && isset($row[$statusIdx])) ? trim((string) $row[$statusIdx]) : '';
                $rawUserName = ($userNameIdx !== null && isset($row[$userNameIdx])) ? trim((string) $row[$userNameIdx]) : '';
                $rawUserEmail = ($userEmailIdx !== null && isset($row[$userEmailIdx])) ? trim((string) $row[$userEmailIdx]) : '';
                $rawUserPhone = ($userPhoneIdx !== null && isset($row[$userPhoneIdx])) ? trim((string) $row[$userPhoneIdx]) : '';
                $rawUserRole = ($userRoleIdx !== null && isset($row[$userRoleIdx])) ? trim((string) $row[$userRoleIdx]) : '';

                // Skip blank/spacer rows
                if ($rawName === '' && $rawStatus === '' && $rawUserName === '' && $rawUserEmail === '') {
                    continue;
                }

                // Skip repeated header rows, banner metadata rows, or column header labels
                $normName = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $rawName)));
                $normStatus = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $rawStatus)));

                if (
                    in_array($normName, ['name', 'organizationname', 'orgname', 'company', 'companyname', 'clients', 'clientsname']) ||
                    in_array($normStatus, ['status', 'initialstatus', 'state', 'expectedformat'])
                ) {
                    continue;
                }

                if (str_contains(strtolower($rawName), 'lead panther') || str_contains(strtolower($rawName), 'import data template') || str_contains(strtolower($rawName), 'field guide')) {
                    continue;
                }

                $rowErrors = [];

                // 1. Validate Organization Name
                if ($rawName === '') {
                    $rowErrors[] = 'Organization Name is required.';
                } elseif (mb_strlen($rawName) > 255) {
                    $rowErrors[] = 'Organization Name cannot exceed 255 characters.';
                } else {
                    $normalizedNameKey = mb_strtolower($rawName);
                    if (isset($seenNamesInBatch[$normalizedNameKey])) {
                        $rowErrors[] = "Duplicate organization name '{$rawName}' in uploaded file.";
                    } elseif (Organization::whereRaw('LOWER(name) = ?', [$normalizedNameKey])->exists()) {
                        $rowErrors[] = "Organization '{$rawName}' already exists in database.";
                    } else {
                        $seenNamesInBatch[$normalizedNameKey] = true;
                    }
                }

                // 2. Validate Status
                $cleanStatus = 'active';
                if ($rawStatus !== '') {
                    $normalizedStatus = strtolower($rawStatus);
                    if (in_array($normalizedStatus, ['active', 'enabled', 'live', '1'])) {
                        $cleanStatus = 'active';
                    } elseif (in_array($normalizedStatus, ['suspended', 'inactive', 'disabled', '0'])) {
                        $cleanStatus = 'suspended';
                    } else {
                        $rowErrors[] = "Invalid status '{$rawStatus}'. Allowed values: active, suspended.";
                    }
                }

                // 3. User Name (defaults to Organization Name if blank)
                $cleanUserName = $rawUserName !== '' ? $rawUserName : $rawName;
                if (mb_strlen($cleanUserName) > 255) {
                    $rowErrors[] = "User Name cannot exceed 255 characters.";
                }

                // 4. User Email (validated if provided, auto-generated unique if blank)
                $cleanUserEmail = '';
                if ($rawUserEmail !== '') {
                    $normEmail = strtolower($rawUserEmail);
                    if (!filter_var($rawUserEmail, FILTER_VALIDATE_EMAIL)) {
                        $rowErrors[] = "Invalid user email format '{$rawUserEmail}'.";
                    } elseif (isset($seenEmailsInBatch[$normEmail])) {
                        $rowErrors[] = "Duplicate user email '{$rawUserEmail}' in uploaded file.";
                    } elseif (User::whereRaw('LOWER(email) = ?', [$normEmail])->exists()) {
                        $rowErrors[] = "User email '{$rawUserEmail}' already exists in database.";
                    } else {
                        $seenEmailsInBatch[$normEmail] = true;
                        $cleanUserEmail = $normEmail;
                    }
                } else {
                    // Auto-generate clean unique email if user email was omitted in file
                    $baseSlug = Str::slug($rawName);
                    if ($baseSlug === '') {
                        $baseSlug = 'client';
                    }
                    $candidateEmail = $baseSlug . '@leadpanther.crm';
                    $counter = 1;
                    while (isset($seenEmailsInBatch[strtolower($candidateEmail)]) || User::whereRaw('LOWER(email) = ?', [strtolower($candidateEmail)])->exists()) {
                        $candidateEmail = $baseSlug . '_' . $counter . '@leadpanther.crm';
                        $counter++;
                    }
                    $seenEmailsInBatch[strtolower($candidateEmail)] = true;
                    $cleanUserEmail = strtolower($candidateEmail);
                }

                // 5. User Phone
                $cleanUserPhone = $rawUserPhone !== '' ? $rawUserPhone : null;
                if ($cleanUserPhone !== null && mb_strlen($cleanUserPhone) > 50) {
                    $rowErrors[] = "User Phone cannot exceed 50 characters.";
                }

                // 6. User Role (defaults to Builder/Developer)
                $cleanRole = 'Builder/Developer';
                if ($rawUserRole !== '') {
                    $roleNorm = strtolower(trim($rawUserRole));
                    if (in_array($roleNorm, ['broker', 'agent/broker'])) {
                        $cleanRole = 'Broker';
                    } elseif (in_array($roleNorm, ['channel partner', 'channelpartner', 'channel_partner', 'cp'])) {
                        $cleanRole = 'Channel Partner';
                    } elseif (in_array($roleNorm, ['executive', 'sales executive', 'sales'])) {
                        $cleanRole = 'Executive';
                    } elseif (in_array($roleNorm, ['super admin', 'superadmin', 'admin'])) {
                        $cleanRole = 'Super Admin';
                    } elseif (in_array($roleNorm, ['builder', 'developer', 'builder/developer', 'builder / developer', 'builderdeveloper'])) {
                        $cleanRole = 'Builder/Developer';
                    } else {
                        $matchedRole = Role::whereRaw('LOWER(name) = ?', [$roleNorm])->first();
                        if ($matchedRole) {
                            $cleanRole = $matchedRole->name;
                        } else {
                            $rowErrors[] = "Invalid user role '{$rawUserRole}'. Allowed values: Builder/Developer, Broker, Channel Partner, Executive.";
                        }
                    }
                }

                if (!empty($rowErrors)) {
                    $this->importErrors[] = [
                        'row' => $rowNum,
                        'name' => $rawName ?: '—',
                        'status' => $rawStatus ?: 'active',
                        'user_name' => $cleanUserName,
                        'user_email' => $rawUserEmail ?: '—',
                        'errors' => $rowErrors,
                    ];
                } else {
                    $this->importRows[] = [
                        'row' => $rowNum,
                        'name' => $rawName,
                        'status' => $cleanStatus,
                        'user_name' => $cleanUserName,
                        'user_email' => $cleanUserEmail,
                        'user_phone' => $cleanUserPhone,
                        'user_role' => $cleanRole,
                    ];
                }
            }

            if (!empty($this->importErrors)) {
                $errorCount = count($this->importErrors);
                $validCount = count($this->importRows);
                $this->dispatch('toast', type: 'warning', message: "Validated: {$validCount} valid record(s), {$errorCount} row(s) with errors.");
            } else {
                $validCount = count($this->importRows);
                $this->dispatch('toast', type: 'success', message: "Validation passed! All {$validCount} organization & user record(s) ready to import.");
            }
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Failed to process file: ' . $e->getMessage());
        }
    }

    public function processImport(): void
    {
        if (empty($this->importRows)) {
            $this->dispatch('toast', type: 'error', message: 'No valid organizations to import.');
            return;
        }

        try {
            $importedCount = 0;

            DB::transaction(function () use (&$importedCount) {
                foreach ($this->importRows as $row) {
                    // 1. Create Organization in organizations table
                    $org = Organization::create([
                        'name' => $row['name'],
                        'status' => $row['status'] ?? 'active',
                    ]);

                    // 2. Create User in users table linked to this organization
                    $userStatus = ($row['status'] ?? 'active') === 'active' ? 'active' : 'inactive';
                    $user = User::create([
                        'organization_id' => $org->id,
                        'name' => $row['user_name'] ?? $row['name'],
                        'email' => $row['user_email'],
                        'phone' => $row['user_phone'] ?? null,
                        'password' => bcrypt(Str::random(16)),
                        'status' => $userStatus,
                        'email_verified_at' => now(),
                    ]);

                    // 3. Assign Role to User
                    $roleName = $row['user_role'] ?? 'Builder/Developer';
                    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
                    $user->assignRole($role->name);

                    // 4. Initialize Credit Wallet for User
                    CreditWallet::firstOrCreate(
                        ['client_id' => $user->id],
                        ['balance' => 0.00]
                    );

                    $importedCount++;
                }
            });

            $skippedCount = count($this->importErrors);
            $this->resetImportState();
            $this->showCreateOrgModal = false;
            $this->dispatch('close-modal', 'register-organization-modal');

            if ($skippedCount > 0) {
                $this->dispatch('toast', type: 'success', message: "Import completed! Created {$importedCount} organizations & users ({$skippedCount} invalid rows were skipped).");
            } else {
                $this->dispatch('toast', type: 'success', message: "Successfully imported {$importedCount} organizations and associated users.");
            }
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Import error: ' . $e->getMessage());
        }
    }

    public function downloadImportErrorsCsv(): StreamedResponse
    {
        $errors = $this->importErrors;
        $filename = "organization_import_errors_" . now()->format('Ymd_His') . ".csv";

        return response()->streamDownload(function () use ($errors) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Row Number', 'Organization Name', 'Status', 'User Name', 'User Email', 'Errors']);

            foreach ($errors as $err) {
                fputcsv($output, [
                    $err['row'] ?? '',
                    $err['name'] ?? '',
                    $err['status'] ?? '',
                    $err['user_name'] ?? '',
                    $err['user_email'] ?? '',
                    implode(' | ', $err['errors'] ?? []),
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function downloadImportTemplate(): BinaryFileResponse
    {
        return Excel::download(new OrganizationImportTemplateExport(), 'Organization_Import_Template.xlsx');
    }

    public function downloadImportTemplateCsv(): StreamedResponse
    {
        $filename = "organization_import_template.csv";

        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Organization Name', 'Status', 'User Name', 'User Email', 'User Phone', 'User Role']);
            fputcsv($output, ['Prestige Estates Projects Ltd', 'active', 'Rajesh Sharma', 'rajesh@prestige.com', '+919876543210', 'Builder/Developer']);
            fputcsv($output, ['Apex Realty Advisory Partners', 'active', 'Pooja Mehta', 'pooja@apexrealty.com', '+919812345678', 'Broker']);
            fputcsv($output, ['Brigade Enterprises Limited', 'active', 'Anil Verma', 'anil@brigade.com', '+919845612345', 'Channel Partner']);
            fputcsv($output, ['Square Yards Advisory', 'active', 'Vikram Singh', 'vikram@squareyards.com', '+919822334455', 'Executive']);
            fputcsv($output, ['Platform Administration Group', 'active', 'Admin Contact', 'contact@platform.com', '+919800112233', 'Builder/Developer']);
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function toggleStatus(int $id): void
    {
        $org = Organization::find($id);
        if ($org) {
            $newStatus = ($org->status === 'active') ? 'suspended' : 'active';
            $org->update(['status' => $newStatus]);
            $this->dispatch('toast', type: 'success', message: "Organization {$org->name} status changed to {$newStatus}.");
        }
    }

    public function openOrgDetailsModal(int $id, string $mode = 'view'): void
    {
        $this->editingOrgId = $id;
        $this->editingOrg = Organization::withCount(['users', 'clients'])->find($id);

        if (!$this->editingOrg) {
            $this->dispatch('toast', type: 'error', message: 'Organization not found.');
            return;
        }

        $this->orgDetailsMode = in_array($mode, ['view', 'edit']) ? $mode : 'view';
        $this->editName = $this->editingOrg->name;
        $this->editStatus = $this->editingOrg->status;
        $this->showOrgDetailsModal = true;
        $this->dispatch('open-modal', 'organization-details-modal');
    }

    public function switchOrgDetailsMode(string $mode): void
    {
        $this->orgDetailsMode = in_array($mode, ['view', 'edit']) ? $mode : 'view';
        if ($this->editingOrg && $this->orgDetailsMode === 'edit') {
            $this->editName = $this->editingOrg->name;
            $this->editStatus = $this->editingOrg->status;
        }
    }

    public function updateOrganization(): void
    {
        if (!$this->editingOrgId) {
            return;
        }

        $this->validate([
            'editName' => 'required|string|max:255',
            'editStatus' => 'required|in:active,suspended',
        ]);

        $org = Organization::find($this->editingOrgId);
        if (!$org) {
            $this->dispatch('toast', type: 'error', message: 'Organization not found.');
            return;
        }

        $org->update([
            'name' => $this->editName,
            'status' => $this->editStatus,
        ]);

        $this->editingOrg = Organization::withCount(['users', 'clients'])->find($this->editingOrgId);
        $this->orgDetailsMode = 'view';
        $this->dispatch('toast', type: 'success', message: "Organization '{$org->name}' updated successfully.");
    }

    public function closeOrgDetailsModal(): void
    {
        $this->showOrgDetailsModal = false;
        $this->editingOrgId = null;
        $this->editingOrg = null;
        $this->orgDetailsMode = 'view';
        $this->reset(['editName', 'editStatus']);
        $this->dispatch('close-modal', 'organization-details-modal');
    }

    public function openUserModal(int $orgId): void
    {
        $this->selectedOrgId = $orgId;
        $this->selectedOrg = Organization::with(['users.roles'])->find($orgId);

        if (!$this->selectedOrg) {
            $this->dispatch('toast', type: 'error', message: 'Organization not found.');
            return;
        }

        $roles = $this->availableRoles;
        $this->newUserRole = isset($roles['Builder']) ? 'Builder' : (string) (array_key_first($roles) ?? 'Builder');

        $this->reset(['newUserName', 'newUserEmail', 'newUserPassword', 'generatedInviteLink']);
        $this->showUserModal = true;
        $this->showUserOffcanvas = true;
        $this->dispatch('open-modal', 'manage-users-modal');
        $this->dispatch('open-offcanvas', 'org-users-drawer');
    }

    public function closeUserModal(): void
    {
        $this->showUserModal = false;
        $this->showUserOffcanvas = false;
        $this->selectedOrgId = null;
        $this->selectedOrg = null;
        $this->reset(['newUserName', 'newUserEmail', 'newUserPassword', 'generatedInviteLink']);
        $this->dispatch('close-modal', 'manage-users-modal');
        $this->dispatch('close-offcanvas', 'org-users-drawer');
    }

    public function openUserOffcanvas(int $orgId): void
    {
        $this->openUserModal($orgId);
    }

    public function closeUserOffcanvas(): void
    {
        $this->closeUserModal();
    }

    public function getAvailableRolesProperty(): array
    {
        $dbRoles = Role::orderBy('name')->pluck('name');
        $roles = [];

        foreach ($dbRoles as $roleName) {
            $label = ucwords(str_replace(['-', '_'], ' ', $roleName));
            if (!isset($roles[$label])) {
                $roles[$label] = $label;
            }
        }

        if (empty($roles)) {
            $roles = [
                'Builder' => 'Builder',
                'Channel Partner' => 'Channel Partner',
                'Sales Executive' => 'Sales Executive',
                'Account Manager' => 'Account Manager',
                'Client' => 'Client',
                'Super Admin' => 'Super Admin',
            ];
        }

        return $roles;
    }

    public function createUserForOrganization(): void
    {
        $this->validate([
            'selectedOrgId' => 'required|exists:organizations,id',
            'newUserName' => 'required|string|max:255',
            'newUserEmail' => 'required|email|unique:users,email',
            'newUserRole' => 'required|string',
        ]);

        $role = Role::firstOrCreate(['name' => $this->newUserRole, 'guard_name' => 'web']);

        $user = User::create([
            'name' => $this->newUserName,
            'email' => $this->newUserEmail,
            'password' => bcrypt($this->newUserPassword ?: Str::random(16)),
            'organization_id' => $this->selectedOrgId,
        ]);

        $user->assignRole($role->name);

        $token = Password::createToken($user);
        $this->generatedInviteLink = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        $this->reset(['newUserName', 'newUserEmail', 'newUserPassword']);
        $this->selectedOrg = Organization::with(['users.roles'])->find($this->selectedOrgId);

        $this->dispatch('toast', type: 'success', message: "User '{$user->name}' created and assigned to '{$this->selectedOrg->name}'.");
    }

    public function updateUserRoleInOrg(int $userId, string $newRole): void
    {
        $role = Role::firstOrCreate(['name' => $newRole, 'guard_name' => 'web']);

        $user = User::where('organization_id', $this->selectedOrgId)->find($userId);
        if ($user) {
            $user->syncRoles([$role->name]);
            $this->selectedOrg = Organization::with(['users.roles'])->find($this->selectedOrgId);
            $this->dispatch('toast', type: 'success', message: "Role for {$user->name} updated to '{$role->name}'.");
        }
    }

    public function removeUserFromOrganization(int $userId): void
    {
        $user = User::where('organization_id', $this->selectedOrgId)->find($userId);
        if ($user) {
            $user->update(['organization_id' => null]);
            $this->selectedOrg = Organization::with(['users.roles'])->find($this->selectedOrgId);
            $this->dispatch('toast', type: 'success', message: "User {$user->name} unassigned from this organization.");
        }
    }

    protected function getFilteredQuery()
    {
        $query = Organization::withCount(['clients', 'users']);

        if (!empty($this->search)) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        if ($this->statusFilter === 'active') {
            $query->where('status', 'active');
        } elseif ($this->statusFilter === 'suspended') {
            $query->where('status', 'suspended');
        }

        if (!empty($this->sortField)) {
            $query->orderBy($this->sortField, $this->sortDirection);
        } else {
            $query->latest('id');
        }

        return $query;
    }

    public function render()
    {
        $organizations = $this->getFilteredQuery()->paginate($this->perPage);
        $roles = Role::all();

        return view('livewire.admin.organization-manager', [
            'organizations' => $organizations,
            'roles' => $roles,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $data = $this->getFilteredQuery()->get();
        $filename = "clients-directory_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Client ID', 'Client Name', 'Users Count', 'Projects Count', 'Status', 'Created At'];
        $columns = [
            'id',
            'name',
            'users_count',
            'clients_count',
            'status',
            fn($o) => $o->created_at ? $o->created_at->format('M d, Y H:i') : '',
        ];

        $subtitle = "Exported " . now()->format('d M Y, H:i T');

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BaseStyledExport(
                data: $data,
                title: 'Multi-Tenant Clients Directory',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                statusColumns: ['status']
            ),
            $filename
        );
    }
}
