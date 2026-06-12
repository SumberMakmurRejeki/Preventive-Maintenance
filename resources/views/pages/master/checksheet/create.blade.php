<x-layouts.app title="Buat Checksheet" heading="PM Management / Master" :actor-name="$actorName" :role="$role">
    @include('pages.master.checksheet.wizard', [
        'title' => 'Buat Checksheet Baru',
        'action' => route('master-checksheet.store'),
        'method' => 'POST',
        'checksheet' => null,
        'machines' => $machines,
        'initialPayload' => old('wizard_payload') ? json_decode(old('wizard_payload'), true) : null,
    ])
</x-layouts.app>
