@props([
    'title' => 'Hapus data ini?',
    'message' => 'Tindakan ini permanen dan tidak bisa dibatalkan.',
])

<x-ui.alert variant="error" :title="$title" {{ $attributes }}>
    {{ $message }}
</x-ui.alert>
