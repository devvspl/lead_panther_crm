<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Concerns\HasAdvancedTable;
use App\Models\Organization;
use App\Models\User;
use App\Models\CreditWallet;
use App\Models\CreditTransaction;
use App\Models\AuditLog;
use App\Models\GeneralSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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

    public array $columnOrder = [];

    public function boot(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
    }

    public function mount(): void
    {
        $this->loadColumnPreferences();
    }

    private function directoryColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'Client ID', 'sortable' => true, 'formatter' => fn($id) => '#ORG-'.str_pad($id, 4, '0', STR_PAD_LEFT), 'class' => 'font-mono text-muted text-xs'],
            ['key' => 'name', 'label' => 'Client Name', 'type' => 'org_name', 'sortable' => true, 'priority' => 1, 'class' => 'font-bold text-ink'],
            ['key' => 'users_count', 'label' => 'Total Users', 'sortable' => true, 'class' => 'font-bold text-ink font-mono'],
            ['key' => 'projects_count', 'label' => 'Total Projects', 'sortable' => true, 'class' => 'font-mono text-muted'],
            ['key' => 'available_credits', 'label' => 'Credits (Available / Total)', 'type' => 'html', 'sortable' => true,
                'render' => fn($org) => view('components.ui.client-directory-credits', ['organization' => $org])->render()],
            [
                'key' => 'status',
                'label' => 'Account Status',
                'type' => 'badge',
                'sortable' => true,
                'priority' => 1,
                'badgeMap' => [
                    'active' => 'bg-success/10 text-success border border-success/20',
                    'suspended' => 'bg-danger/10 text-danger border border-danger/20',
                ]
            ],
            ['key' => 'created_at', 'label' => 'Created Date', 'type' => 'date', 'sortable' => true],
            ['key' => 'last_login', 'label' => 'Last Login', 'sortable' => true, 'formatter' => fn($date) => $date ? Carbon::parse($date)->format('M d, Y h:i A') : 'Never logged in', 'class' => 'text-xs text-muted'],
            ['key' => 'actions', 'label' => 'Actions', 'type' => 'html', 'mobileClass' => 'col-span-2 pt-2 border-t border-border',
                'render' => fn($org) => view('components.ui.client-directory-actions', ['organization' => $org])->render()],
        ];
    }

    public function tableColumns(): array
    {
        $columns = collect($this->directoryColumns())->keyBy('key');
        $order = array_values(array_unique(array_merge(array_intersect($this->columnOrder, $columns->keys()->all()), $columns->keys()->all())));
        return array_map(fn($key) => $columns[$key], $order);
    }

    // Reuse the existing per-user settings store, preserving explicitly hidden columns.
    public function loadColumnPreferences(): void
    {
        $keys = array_column($this->directoryColumns(), 'key');
        $saved = json_decode(GeneralSetting::getValue(auth()->id(), 'table_columns_'.$this->getTableIdentifier()) ?: 'null', true);
        $known = json_decode(GeneralSetting::getValue(auth()->id(), 'table_column_schema_'.$this->getTableIdentifier()) ?: 'null', true);
        if (is_array($saved) && $saved) {
            $saved = array_map(fn($key) => $key === 'clients_count' ? 'projects_count' : $key, $saved);
            $new = is_array($known) ? array_diff($keys, $known) : ['available_credits', 'last_login'];
            $this->visibleColumns = array_values(array_unique(array_intersect(array_merge($saved, $new), $keys)));
            if (!$this->visibleColumns) $this->visibleColumns = $keys;
        } else {
            $this->visibleColumns = $keys;
        }
        $order = json_decode(GeneralSetting::getValue(auth()->id(), 'table_column_order_'.$this->getTableIdentifier()) ?: '[]', true);
        $this->columnOrder = array_values(array_unique(array_merge(is_array($order) ? array_intersect($order, $keys) : [], $keys)));
    }

    public function persistColumnPreferences(): void
    {
        $keys = array_column($this->directoryColumns(), 'key');
        GeneralSetting::setValue(auth()->id(), 'table_columns_'.$this->getTableIdentifier(), json_encode(array_values(array_intersect($this->visibleColumns, $keys))));
        GeneralSetting::setValue(auth()->id(), 'table_column_schema_'.$this->getTableIdentifier(), json_encode($keys));
        GeneralSetting::setValue(auth()->id(), 'table_column_order_'.$this->getTableIdentifier(), json_encode(array_column($this->tableColumns(), 'key')));
    }

    public function moveColumn(string $key, int $direction): void
    {
        abort_unless(in_array($direction, [-1, 1], true), 422);
        $order = array_column($this->tableColumns(), 'key');
        $index = array_search($key, $order, true);
        abort_if($index === false, 422);
        $next = $index + $direction;
        if (isset($order[$next])) {
            [$order[$index], $order[$next]] = [$order[$next], $order[$index]];
            $this->columnOrder = $order;
            $this->persistColumnPreferences();
        }
    }

    public function resetColumns(): void
    {
        $this->columnOrder = $this->visibleColumns = array_column($this->directoryColumns(), 'key');
        $this->persistColumnPreferences();
    }

    public function updatedColumnFilters(): void
    {
        $this->resetPage();
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->validate([
            'columnFilters.projects_min' => 'nullable|integer|min:0', 'columnFilters.projects_max' => 'nullable|integer|min:0',
            'columnFilters.credits_min' => 'nullable|numeric|min:0', 'columnFilters.credits_max' => 'nullable|numeric|min:0',
            'columnFilters.created_from' => 'nullable|date_format:Y-m-d', 'columnFilters.created_to' => 'nullable|date_format:Y-m-d',
        ]);
    }

    public function updatedStatusFilter(): void
    {
        $this->setStatusFilter($this->statusFilter);
    }

    public function getActiveFilterCountProperty(): int
    {
        return ($this->statusFilter !== 'all' ? 1 : 0) + count(array_filter($this->columnFilters, fn($value) => $value !== '' && $value !== null));
    }

    protected function getCurrentPageRowIds(): array
    {
        return $this->getFilteredQuery()->paginate($this->perPage)->pluck('id')->map(fn($id) => (string)$id)->all();
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
            $userPasswordIdx = null;

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
                } elseif (in_array($colNormalized, ['password', 'userpassword', 'loginpassword', 'pass'])) {
                    $userPasswordIdx = $colIdx;
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
                $rawPassword = ($userPasswordIdx !== null && isset($row[$userPasswordIdx])) ? trim((string) $row[$userPasswordIdx]) : '';

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
                        'password' => $rawPassword !== '' ? $rawPassword : 'password',
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
                        'password' => bcrypt(!empty($row['password']) ? $row['password'] : 'password'),
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
            if (in_array(strtolower(trim($roleName)), ['account manager', 'account-manager'])) {
                continue;
            }
            $label = ucwords(str_replace(['-', '_'], ' ', $roleName));
            if (!isset($roles[$label])) {
                $roles[$label] = $label;
            }
        }

        if (empty($roles)) {
            $roles = [
                'Builder/Developer' => 'Builder/Developer',
                'Broker' => 'Broker',
                'Channel Partner' => 'Channel Partner',
                'Executive' => 'Executive',
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

    protected function getFilteredQuery(): Builder
    {
        $members = fn() => User::select('id')->whereColumn('users.organization_id', 'organizations.id');
        // Actual wallet movements: consumption less refunds. Funding/adjustments aren't lead usage.
        $movement = "CASE WHEN lead_id IS NULL THEN 0 WHEN transaction_type IN ('lead_consumption','deduct','reserve') THEN CASE WHEN credit_before > credit_after THEN credit_before-credit_after ELSE 0 END WHEN transaction_type = 'refund' THEN CASE WHEN credit_after > credit_before THEN credit_before-credit_after ELSE 0 END ELSE 0 END";
        $metrics = Organization::query()->select('organizations.*')->withCount(['users', 'projects'])->addSelect([
            'available_credits' => CreditWallet::query()->selectRaw('COALESCE(SUM(balance),0)')->whereIn('client_id', $members()),
            'credits_used' => CreditTransaction::query()->selectRaw("CASE WHEN SUM($movement)>0 THEN SUM($movement) ELSE 0 END")->whereIn('client_id', $members()),
            'last_login' => AuditLog::query()->selectRaw('MAX(created_at)')->where('action', 'auth.login')->where(function ($q) use ($members) {
                $q->whereColumn('audit_logs.organization_id', 'organizations.id')->orWhere(function ($q) use ($members) {
                    $q->whereNull('audit_logs.organization_id')->whereIn('user_id', $members());
                });
            }),
        ]);
        // Filter derived values without database-specific HAVING behavior.
        $query = Organization::query()->fromSub($metrics, 'organizations')->select('organizations.*');
        $search = trim($this->search);
        if ($search !== '') {
            $id = preg_match('/^(?:#?ORG-?)?#?0*(\d+)$/i', $search, $match) ? (int)$match[1] : null;
            $query->where(function ($q) use ($search, $id) {
                $q->where('organizations.name', 'like', '%'.$search.'%')
                    ->orWhereHas('users', fn($users) => $users->where('email', 'like', '%'.$search.'%'));
                if ($id !== null) $q->orWhere('organizations.id', $id);
            });
        }
        if (in_array($this->statusFilter, ['active', 'suspended'], true)) $query->where('organizations.status', $this->statusFilter);
        foreach (['projects_min' => ['projects_count', '>='], 'projects_max' => ['projects_count', '<='], 'credits_min' => ['available_credits', '>='], 'credits_max' => ['available_credits', '<=']] as $key => [$column, $operator]) {
            $value = $this->columnFilters[$key] ?? '';
            if (is_numeric($value) && (float)$value >= 0) $query->whereRaw("CAST($column AS DECIMAL(18,0)) $operator ?", [(float)$value]);
        }
        foreach (['created_from' => '>=', 'created_to' => '<='] as $key => $operator) {
            $date = $this->columnFilters[$key] ?? '';
            $parsed = is_string($date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
            if ($parsed && $parsed->format('Y-m-d') === $date) $query->where('organizations.created_at', $operator, $date.($key === 'created_from' ? ' 00:00:00' : ' 23:59:59'));
        }
        $sortable = ['id', 'name', 'users_count', 'projects_count', 'available_credits', 'status', 'created_at', 'last_login'];
        $field = $this->sortField === 'clients_count' ? 'projects_count' : $this->sortField;
        if (in_array($field, $sortable, true)) $query->orderBy($field, $this->sortDirection === 'asc' ? 'asc' : 'desc');
        return $query->orderByDesc('organizations.id');
    }

    private function creditMetrics(Organization $organization): Organization
    {
        $total = max(0, (int)$organization->available_credits + (int)$organization->credits_used);
        $organization->setAttribute('total_credits', $total);
        $organization->setAttribute('usage_percent', $total > 0 ? round(min(100, (float)$organization->credits_used / $total * 100), 1) : 0);
        return $organization;
    }

    public function render()
    {
        $organizations = $this->getFilteredQuery()->paginate($this->perPage)->through(fn($org) => $this->creditMetrics($org));
        $summary = [
            'total' => Organization::count(), 'active' => Organization::where('status', 'active')->count(),
            'suspended' => Organization::where('status', 'suspended')->count(), 'users' => User::whereHas('organization')->count(),
        ];
        $roles = Role::all();

        return view('livewire.admin.organization-manager', [
            'organizations' => $organizations,
            'summary' => $summary,
            'roles' => $roles,
        ])->layout('layouts.app');
    }

    public function exportExcel(bool $selectedOnly = false)
    {
        $query = $this->getFilteredQuery();
        if ($selectedOnly) {
            abort_if(empty($this->selectedRows), 422);
            $query->whereIn('organizations.id', $this->selectedRows);
        }
        $data = $query->get()->map(fn($org) => $this->creditMetrics($org));
        $filename = "clients-directory_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Client ID', 'Client Name', 'Total Users', 'Total Projects', 'Available Credits', 'Total Credits', 'Used Credits', 'Usage %', 'Account Status', 'Created Date', 'Last Login'];
        $columns = [
            fn($o) => '#ORG-'.str_pad($o->id, 4, '0', STR_PAD_LEFT), 'name', 'users_count', 'projects_count',
            'available_credits', 'total_credits', 'credits_used', 'usage_percent', 'status',
            fn($o) => $o->created_at?->format('Y-m-d H:i:s') ?? '',
            fn($o) => $o->last_login ? Carbon::parse($o->last_login)->format('Y-m-d H:i:s') : '',
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
