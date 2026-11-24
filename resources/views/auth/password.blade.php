<x-layouts.app title="Change password">
    <div class="card max-w-lg">
        <h1 class="h2 mb-1">Change password</h1>
        <p class="muted mb-4">At least 8 characters with letters and numbers.</p>
        <form method="post" action="{{ route('password.update') }}" class="space-y-4">
            @csrf @method('put')
            <x-field name="current_password" label="Current password" type="password" required />
            <x-field name="password" label="New password" type="password" required />
            <x-field name="password_confirmation" label="Repeat new password" type="password" required />
            <button class="btn btn-primary">Save new password</button>
        </form>
    </div>
</x-layouts.app>
