<x-layouts.app title="Edit Checksheet" heading="PM Management / Master" :actor-name="$actorName" :role="$role">
    @include('pages.master.checksheet.wizard', [
        'title' => 'Edit Checksheet',
        'action' => route('master-checksheet.update', $checksheet->id),
        'method' => 'PUT',
        'checksheet' => $checksheet,
        'machines' => $machines,
        'initialPayload' => old('wizard_payload') ? json_decode(old('wizard_payload'), true) : $initialPayload,
    ])
</x-layouts.app>
