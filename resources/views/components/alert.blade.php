@props(['variant' => 'info'])

@php
    $style = [
        'success' => ['bg-success-50 text-success-700 border-success-100', 'check'],
        'error' => ['bg-error-50 text-error-700 border-error-100', 'alert'],
        'warning' => ['bg-warning-50 text-warning-700 border-warning-100', 'incident'],
        'info' => ['bg-gray-50 text-gray-700 border-gray-200', 'info'],
    ][$variant] ?? ['bg-gray-50 text-gray-700 border-gray-200', 'info'];
@endphp
<div {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-xl border px-4 py-3 text-sm {$style[0]}"]) }}>
    <x-icon :name="$style[1]" class="mt-0.5 size-5 shrink-0" />
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
