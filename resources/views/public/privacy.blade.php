<x-layouts.guest title="Privacy notice">
    <div class="card mx-auto max-w-3xl">
        <h1 class="h1 mb-4">Privacy notice</h1>
        <p class="whitespace-pre-line">{{ \App\Models\Setting::get('privacy_notice') }}</p>
        <h2 class="h2 mt-6 mb-2">How we protect your data</h2>
        <ul class="list-disc space-y-1 pl-5 text-sm">
            <li>Each account sees only what its role needs. Parents see only their own children; health and guidance records are limited to the nurse and guidance counselor.</li>
            <li>Every change to records (including grades) is written to an audit log with the user, time and old/new values.</li>
            <li>Passwords are stored encrypted (hashed). Daily backups are kept by the school.</li>
            <li>You may ask the registrar for a copy of your/your child's data, or to correct it (rights of a data subject under RA 10173).</li>
        </ul>
        <p class="muted mt-6">Questions or requests: {{ \App\Models\Setting::get('school_email') ?: 'contact the school registrar' }}</p>
    </div>
</x-layouts.guest>
