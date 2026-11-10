@props(['title' => null, 'flush' => false])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-200 bg-white']) }}>
    @if ($title !== null || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 sm:px-6">
            <h2 class="text-base font-medium text-gray-800">{{ $title }}</h2>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div @class(['p-5 sm:p-6' => ! $flush])>{{ $slot }}</div>
</div>
