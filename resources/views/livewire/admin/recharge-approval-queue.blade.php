<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Recharge Approval Queue</h1>
            <p class="text-xs text-muted">Review and confirm client credit recharge requests.</p>
        </div>
    </div>



    <!-- Recharge Requests Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Request ID</th>
                        <th class="py-3 px-4">Client / Org</th>
                        <th class="py-3 px-4">Package</th>
                        <th class="py-3 px-4">Credits</th>
                        <th class="py-3 px-4">Price</th>
                        <th class="py-3 px-4">Requested At</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($requests as $req)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-ink">#{{ $req->id }}</td>
                            <td class="py-3 px-4 font-bold text-ink">{{ $req->client->name ?? 'Client #'.$req->client_id }}</td>
                            <td class="py-3 px-4 font-semibold text-ink">{{ $req->package->name ?? 'Custom Package' }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-primary">+{{ $req->package->credit_count ?? ($req->amount / 10) }} Cr</td>
                            <td class="py-3 px-4 font-mono font-bold text-ink">₹{{ number_format($req->amount, 2) }}</td>
                            <td class="py-3 px-4 font-mono text-muted whitespace-nowrap">{{ $req->requested_at }}</td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($req->status === 'pending')
                                    <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-pill bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
                                @elseif($req->status === 'approved')
                                    <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-pill bg-green-50 text-green-700 border border-green-200">Approved</span>
                                @else
                                    <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-pill bg-red-50 text-red-700 border border-red-200">Rejected</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right space-x-2 whitespace-nowrap">
                                @if($req->status === 'pending')
                                    <button wire:click="openApproveModal({{ $req->id }})" class="px-2.5 py-1 text-xs font-bold text-white bg-accent rounded hover:bg-black transition">
                                        Approve ✓
                                    </button>
                                    <button wire:click="openRejectModal({{ $req->id }})" class="px-2.5 py-1 text-xs font-bold text-danger bg-canvas rounded hover:bg-red-50 border border-border transition">
                                        Reject ✕
                                    </button>
                                @else
                                    <span class="text-[11px] text-muted italic">Processed</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-muted">No recharge requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $requests->links('vendor.pagination.tailwind') }}
        </div>
    </div>

    <!-- APPROVE CONFIRMATION MODAL -->
    @if($showApproveModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4">
            <div class="bg-surface rounded-card max-w-md w-full p-6 shadow-xl border border-border space-y-4">
                <h3 class="text-base font-bold text-ink">Approve Credit Recharge Request</h3>
                <p class="text-xs text-muted">Confirming this request will immediately credit the client's wallet with the requested credit balance.</p>

                <div>
                    <label class="block text-xs font-semibold text-ink mb-1">Payment Reference / Note (Optional)</label>
                    <input type="text" wire:model="referenceNote" placeholder="e.g. Bank Transfer Ref #12345 or Cash Payment" class="w-full text-xs rounded border border-border p-2 bg-canvas focus:ring-1 focus:ring-ink">
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <button wire:click="$set('showApproveModal', false)" class="px-3 py-1.5 text-xs text-muted hover:text-ink font-semibold">Cancel</button>
                    <button wire:click="approveRequest" class="px-4 py-1.5 text-xs font-bold text-white bg-accent rounded hover:bg-black">Confirm Approval ✓</button>
                </div>
            </div>
        </div>
    @endif

    <!-- REJECT MODAL -->
    @if($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4">
            <div class="bg-surface rounded-card max-w-md w-full p-6 shadow-xl border border-border space-y-4">
                <h3 class="text-base font-bold text-ink">Decline Credit Recharge Request</h3>
                <p class="text-xs text-muted">Please specify a reason for rejecting this recharge request. This will be sent to the client.</p>

                <div>
                    <label class="block text-xs font-semibold text-ink mb-1">Rejection Reason (Required)</label>
                    <textarea wire:model="rejectionReason" rows="3" placeholder="e.g. Payment receipt not verified or incorrect amount." class="w-full text-xs rounded border border-border p-2 bg-canvas focus:ring-1 focus:ring-ink"></textarea>
                    @error('rejectionReason') <span class="text-red-500 text-[10px]">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <button wire:click="$set('showRejectModal', false)" class="px-3 py-1.5 text-xs text-muted hover:text-ink font-semibold">Cancel</button>
                    <button wire:click="rejectRequest" class="px-4 py-1.5 text-xs font-bold text-white bg-danger rounded hover:opacity-90">Decline Request ✕</button>
                </div>
            </div>
        </div>
    @endif
</div>
