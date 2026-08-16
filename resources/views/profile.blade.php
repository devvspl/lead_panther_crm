<x-app-layout>
    <div class="space-y-6 max-w-3xl">
        <!-- Page Header -->
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Profile Settings</h1>
            <p class="text-xs text-muted">Manage your personal account information, password, and security preferences.</p>
        </div>

        <!-- Section 1: Profile Information -->
        <livewire:profile.update-profile-information-form />

        <!-- Section 2: Update Password -->
        <livewire:profile.update-password-form />

        <!-- Section 3: Delete Account -->
        <livewire:profile.delete-user-form />
    </div>
</x-app-layout>
