<?php
namespace App\Livewire\Admin;

use App\Models\{AuditLog, User};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, Rules\Password};
use Livewire\Attributes\{Locked, On};
use Livewire\Component;

class OrganizationMemberDetails extends Component
{
    #[Locked] public int $organizationId;
    #[Locked] public ?int $memberId = null;
    public bool $show = false;
    public bool $editing = false;
    public bool $changingPassword = false;
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $status = 'active';
    public string $password = '';
    public string $password_confirmation = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->status === 'active' && auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
    }

    private function member(): User
    {
        $member = User::where('organization_id', $this->organizationId)->find($this->memberId);
        abort_unless($member, 404);
        return $member;
    }

    #[On('open-org-member')]
    public function open(int $memberId): void
    {
        $this->memberId = $memberId;
        $member = $this->member();
        $this->resetValidation();
        $this->reset('password', 'password_confirmation', 'editing', 'changingPassword');
        foreach (['name', 'email', 'phone', 'status'] as $field) $this->$field = (string) $member->$field;
        $this->show = true;
        $this->dispatch('open-modal', 'org-member-details');
    }

    public function close(): void
    {
        $this->reset('show', 'editing', 'changingPassword', 'password', 'password_confirmation');
        $this->dispatch('close-modal', 'org-member-details');
    }

    public function saveDetails(): void
    {
        $member = $this->member();
        $data = $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($member->id)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($member->id)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $data['phone'] = $data['phone'] ?: null;
        DB::transaction(function () use ($member, $data) {
            $before = $member->only(array_keys($data));
            if ($member->email !== $data['email']) $data['email_verified_at'] = null;
            $member->update($data);
            $this->audit($member, 'organization.member_updated', $before, $member->only(['name', 'email', 'phone', 'status']));
        });
        $this->editing = false;
        $this->dispatch('org-member-updated');
        $this->dispatch('toast', type: 'success', message: 'Member details saved.');
    }

    public function changePassword(): void
    {
        $member = $this->member();
        $this->validate(['password' => ['required', 'string', Password::defaults(), 'confirmed']]);
        DB::transaction(function () use ($member) {
            $member->update(['password' => Hash::make($this->password), 'remember_token' => Str::random(60)]);
            $this->audit($member, 'organization.member_password_changed', [], ['password_changed' => true]);
        });
        $this->reset('password', 'password_confirmation', 'changingPassword');
        $this->dispatch('password-updated');
        $this->dispatch('toast', type: 'success', message: 'Member password changed.');
    }

    private function audit(User $member, string $action, array $before, array $after): void
    {
        AuditLog::create(['user_id' => auth()->id(), 'organization_id' => $this->organizationId,
            'action' => $action, 'subject_type' => User::class, 'subject_id' => $member->id,
            'from_value' => json_encode($before), 'to_value' => json_encode($after), 'created_at' => now()]);
    }

    public function render()
    {
        return view('livewire.admin.organization-member-details', ['member' => $this->show ? $this->member() : null]);
    }
}
