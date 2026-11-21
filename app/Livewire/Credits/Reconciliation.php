<?php

namespace App\Livewire\Credits;

use App\Models\{CreditDispute, CreditTransaction, CreditWallet, User};
use App\Services\LeadCreditService;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Reconciliation extends Component
{
    #[Locked] public ?int $organizationId = null;
    #[Locked] public int $expected = 0;
    #[Locked] public ?int $resolvingId = null;
    public ?int $clientId = null;
    public $remaining = 0;
    public $legacyQuantity = 1;
    public string $legacyReason = '';
    public bool $legacyConfirmed = false;
    public string $reason = '';
    public bool $confirmed = false;
    public ?int $transactionId = null;
    public string $disputeReason = '';
    public string $resolution = '';
    public $correction = 0;
    public string $correctionAction = 'recharge';
    public string $message = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->status === 'active' && auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
    }
    public function mount(?int $organizationId = null): void
    {
        $this->organizationId = $organizationId;
        if ($organizationId) {
            $this->clientId = $this->clients()->orderBy('id')->value('id');
            $this->updatedClientId();
        }
    }
    private function clients()
    {
        return User::when($this->organizationId, fn ($q, $id) => $q->where('organization_id', $id))
            ->where(function ($q) {
                $roles = ['Client', 'client', 'Builder/Developer', 'Builder', 'builder', 'Broker', 'broker', 'Channel Partner', 'channel-partner'];
                $q->whereHas('roles', fn ($r) => $r->whereIn('name', $roles))
                    ->orWhereIn('id', CreditWallet::withoutGlobalScopes()->select('client_id'))
                    ->orWhereIn('id', \App\Models\Client::whereHas('roles', fn ($r) => $r->whereIn('name', $roles))->select('id'));
            });
    }
    private function client(): User { return $this->clients()->findOrFail($this->clientId); }
    public function updatedClientId(): void
    {
        $this->resetValidation();
        $this->reset('reason', 'confirmed', 'transactionId', 'disputeReason', 'resolvingId', 'message');
        if (!$this->clientId) return;
        $wallet = CreditWallet::where('client_id', $this->client()->id)->first();
        $this->expected = (int) ($wallet?->balance ?? 0);
        $this->remaining = $this->expected;
    }
    public function reconcile(): void
    {
        $this->validate(['confirmed' => 'accepted']);
        app(LeadCreditService::class)->reconcile($this->client(), $this->remaining, $this->expected, $this->reason, auth()->user());
        $this->updatedClientId();
        $this->message = 'Verified lead credits saved. Original history is preserved.';
        $this->dispatch('lead-credits-updated');
    }
    public function markLegacyConsumption(): void
    {
        $this->validate(['legacyConfirmed' => 'accepted']);
        $transaction = CreditTransaction::withoutGlobalScopes()->where('client_id', $this->client()->id)->findOrFail($this->transactionId);
        app(LeadCreditService::class)->markLegacyConsumption($transaction, $this->legacyQuantity, $this->legacyReason, auth()->user());
        $this->reset('transactionId', 'legacyReason', 'legacyConfirmed');
        $this->message = 'Legacy delivery recorded as credit consumption. Original history is preserved.';
        $this->dispatch('lead-credits-updated');
    }
    public function openDispute(): void
    {
        $transaction = CreditTransaction::withoutGlobalScopes()->where('client_id', $this->client()->id)->findOrFail($this->transactionId);
        app(LeadCreditService::class)->dispute($transaction, $this->disputeReason, auth()->user());
        $this->reset('disputeReason', 'transactionId');
        $this->message = 'Dispute recorded. No credits were changed.';
    }
    private function dispute(int $id): CreditDispute
    {
        return CreditDispute::whereHas('transaction', fn ($q) => $q->where('client_id', $this->client()->id))->findOrFail($id);
    }
    public function reviewDispute(int $id): void
    {
        $dispute = $this->dispute($id);
        abort_unless($dispute->status === 'open', 422);
        $this->resolvingId = $dispute->id;
        $this->reset('resolution', 'correction', 'correctionAction');
        $this->dispatch('open-modal', 'resolve-credit-dispute');
    }
    public function resolve(): void
    {
        abort_unless($this->resolvingId, 404);
        $this->dispute($this->resolvingId);
        app(LeadCreditService::class)->resolveDispute($this->resolvingId, $this->resolution, $this->correction, $this->correctionAction, auth()->user());
        $this->resolvingId = null;
        $this->dispatch('close-modal', 'resolve-credit-dispute');
        $this->message = 'Dispute resolved. Any correction is recorded as a separate ledger entry.';
        $this->dispatch('lead-credits-updated');
    }
    public function render()
    {
        $client = $this->clientId ? $this->client() : null;
        $transactions = $client ? CreditTransaction::withoutGlobalScopes()->where('client_id', $client->id)->latest('id')->limit(100)->get() : collect();
        return view('livewire.credits.reconciliation', [
            'clientOptions' => ['' => 'Select client'] + $this->clients()->orderBy('name')->pluck('name', 'id')->all(),
            'wallet' => $client ? CreditWallet::where('client_id', $client->id)->first() : null,
            'pricing' => $client ? app(\App\Services\LeadPricingService::class)->resolveCost(null, null, $client->organization)['formatted'] : null,
            'transactionOptions' => ['' => 'Select ledger entry'] + $transactions->mapWithKeys(fn ($tx) => [$tx->id => '#'.$tx->id.' · '.$tx->transaction_type_label.' · '.($tx->is_legacy ? 'Legacy reference: '.$tx->getRawOriginal('legacy_credit_used') : $tx->credit_used.' credits')])->all(),
            'disputes' => $client ? CreditDispute::whereHas('transaction', fn ($q) => $q->where('client_id', $client->id))->latest('id')->limit(30)->get() : collect(),
        ]);
    }
}
