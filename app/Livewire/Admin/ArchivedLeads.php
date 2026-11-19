<?php

namespace App\Livewire\Admin;

use App\Exports\BaseStyledExport;
use App\Livewire\Concerns\HasAdvancedTable;
use App\Models\{AuditLog, Lead, User};
use App\Services\LeadArchiveService;
use Livewire\Attributes\{Layout, Locked};
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.app')]
class ArchivedLeads extends Component
{
    use HasAdvancedTable;

    public string $archivedBy = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $stage = '';
    #[Locked] public ?int $detailId = null;
    #[Locked] public ?int $actionId = null;
    #[Locked] public string $action = '';
    public string $reason = '';
    public bool $confirmed = false;
    public bool $reassign = false;

    public function boot(): void { LeadArchiveService::authorize(); }
    public function mount(): void { $this->sortField = 'archived_at'; }

    public function updated(string $name): void
    {
        if (in_array($name, ['archivedBy','dateFrom','dateTo','stage'])) $this->resetPage();
    }

    public function tableColumns(): array
    {
        return [
            ['key'=>'lead_code', 'label'=>'Lead ID', 'sortable'=>true],
            ['key'=>'name', 'label'=>'Name', 'sortable'=>true],
            ['key'=>'mobile', 'label'=>'Mobile'], ['key'=>'email', 'label'=>'Email'],
            ['key'=>'current_stage', 'label'=>'Stage', 'sortable'=>true],
            ['key'=>'assignedTo.name', 'label'=>'Executive'],
            ['key'=>'archive_reason', 'label'=>'Archive Reason'],
            ['key'=>'archivedBy.name', 'label'=>'Archived By'],
            ['key'=>'archived_at', 'label'=>'Archived At', 'sortable'=>true],
            ['key'=>'actions', 'label'=>'Actions', 'render'=>fn($lead)=>view('components.ui.archived-lead-actions', ['lead'=>$lead])->render()],
        ];
    }

    private function query()
    {
        LeadArchiveService::authorize();
        $this->validate(['dateFrom'=>'nullable|date_format:Y-m-d', 'dateTo'=>'nullable|date_format:Y-m-d', 'archivedBy'=>'nullable|integer']);
        return Lead::withoutGlobalScopes()->onlyArchived()->with(['assignedTo', 'archivedBy', 'client.organization'])
            ->when($this->search !== '', fn($q)=>$q->where(function ($q) {
                foreach (['lead_code','name','mobile','email','archive_reason'] as $field) $q->orWhere($field, 'like', '%'.$this->search.'%');
            }))
            ->when($this->archivedBy, fn($q)=>$q->where('archived_by', $this->archivedBy))
            ->when($this->stage, fn($q)=>$q->where('current_stage', $this->stage))
            ->when($this->dateFrom, fn($q)=>$q->whereDate('archived_at','>=',$this->dateFrom))
            ->when($this->dateTo, fn($q)=>$q->whereDate('archived_at','<=',$this->dateTo))
            ->orderBy(in_array($this->sortField, ['lead_code','name','current_stage','archived_at']) ? $this->sortField : 'archived_at', $this->sortDirection === 'asc' ? 'asc' : 'desc')->orderBy('id');
    }

    public function details(int $id): void
    {
        $this->detailId = Lead::withoutGlobalScopes()->onlyArchived()->findOrFail($id)->id;
        $this->dispatch('open-modal', 'archived-lead-details');
    }

    public function prepare(int $id, string $action): void
    {
        LeadArchiveService::authorize();
        abort_unless(in_array($action, ['restore','delete'], true), 422);
        $this->actionId = Lead::withoutGlobalScopes()->onlyArchived()->findOrFail($id)->id;
        $this->action = $action;
        $this->reset('reason','confirmed','reassign');
        $this->resetValidation();
        $this->dispatch('open-modal', 'archived-lead-action');
    }

    public function execute(LeadArchiveService $service): void
    {
        LeadArchiveService::authorize();
        $this->validate(['confirmed'=>'accepted', 'reason'=>'nullable|string|max:2000', 'actionId'=>'required|integer']);
        abort_unless(in_array($this->action, ['restore','delete'], true), 422);
        if ($this->action === 'restore') $service->restore($this->actionId, $this->reassign);
        else $service->permanentlyDelete($this->actionId, $this->reason);
        $this->reset('detailId','actionId');
        $this->dispatch('close-modal', 'archived-lead-action');
        $this->dispatch('close-modal', 'archived-lead-details');
        $this->dispatch('lead-updated');
        $this->dispatch('toast', type:'success', message:$this->action === 'restore' ? 'Lead restored.' : 'Lead permanently deleted.');
    }

    public function exportExcel()
    {
        return Excel::download(new BaseStyledExport(data:$this->query()->get(), title:'Archived Leads',
            headings:['Lead ID','Name','Mobile','Email','Status','Stage','Executive','Archive Reason','Archived By','Archived At'],
            columns:['lead_code','name','mobile','email','status','current_stage','assignedTo.name','archive_reason','archivedBy.name',fn($l)=>$l->archived_at?->format('Y-m-d H:i:s')]), 'archived-leads.xlsx');
    }

    public function render()
    {
        $detail = $this->detailId ? Lead::withoutGlobalScopes()->onlyArchived()->with(['client.organization','assignedTo','archivedBy'])->find($this->detailId) : null;
        $retainedRecords = [];
        if ($detail) {
            foreach (['statusHistory'=>'Stages', 'calls'=>'Calls', 'communications'=>'Communications', 'messages'=>'Messages', 'meetings'=>'Meetings', 'siteVisits'=>'Site Visits', 'negotiations'=>'Negotiations', 'bookings'=>'Bookings', 'metadata'=>'Additional Details'] as $relation=>$label) {
                $retainedRecords[$label] = $detail->{$relation}()->withArchived()->get();
            }
        }
        return view('livewire.admin.archived-leads', [
            'retainedRecords'=>$retainedRecords,
            'leads'=>$this->query()->paginate(15), 'detail'=>$detail,
            'archivers'=>User::whereIn('id',Lead::withoutGlobalScopes()->onlyArchived()->select('archived_by'))->orderBy('name')->get(),
            'stages'=>Lead::withoutGlobalScopes()->onlyArchived()->distinct()->pluck('current_stage'),
            'history'=>$detail ? AuditLog::where('subject_type', Lead::class)->where('subject_id',$detail->id)->with('user')->orderByDesc('created_at')->orderByDesc('id')->get() : collect(),
            'followups'=>$detail ? $detail->followups()->withArchived()->get() : collect(),
            'assignments'=>$detail ? $detail->assignments()->withArchived()->with('assignee')->get() : collect(),
        ]);
    }
}
