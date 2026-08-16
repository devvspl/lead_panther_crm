<x-guest-layout>
    <div class="w-full max-w-md space-y-6">
        <div class="bg-surface rounded-card border border-border p-8 shadow-sm text-center space-y-6">
            <div class="inline-flex h-14 w-14 rounded-full bg-amber-50 text-amber-600 items-center justify-center font-bold text-2xl mx-auto border border-amber-200">
                ⏳
            </div>
            
            <div class="space-y-2">
                <h1 class="text-2xl font-bold text-ink tracking-tight">Organization Pending Activation</h1>
                <p class="text-xs text-muted leading-relaxed">
                    Your organization account is currently under review or awaiting activation by the platform administration team.
                </p>
            </div>

            <div class="p-4 bg-canvas rounded-lg border border-border text-left space-y-2 text-xs">
                <div class="font-semibold text-ink">What should I do?</div>
                <p class="text-muted leading-relaxed">
                    Please contact your organization manager or email <span class="font-semibold text-ink">support@leadpanther.com</span> to expedite account verification.
                </p>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full py-2.5 px-4 border border-border bg-white hover:bg-canvas text-ink text-xs font-semibold rounded-lg shadow-sm transition">
                    Sign Out
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>
