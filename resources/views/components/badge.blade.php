@props(['variant' => 'gray', 'pulse' => false])

@php
    $palette = [
        'gray' => 'bg-gray-100 text-gray-700',
        'brand' => 'bg-brand-50 text-brand-500',
        'success' => 'bg-success-50 text-success-700',
        'warning' => 'bg-warning-50 text-warning-700',
        'error' => 'bg-error-50 text-error-700',
    ][$variant] ?? 'bg-gray-100 text-gray-700';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {$palette}"]) }}>
    @if ($pulse)
        <span class="relative inline-flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-error-500 opacity-75"></span><span class="relative inline-flex size-2 rounded-full bg-error-600"></span></span>
    @endif
    {{ $slot }}
</span>
