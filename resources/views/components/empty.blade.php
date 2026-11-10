@props(['title', 'icon' => 'info'])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-4 py-10 text-center']) }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-gray-100 text-gray-500"><x-icon :name="$icon" class="size-6" /></span>
    <p class="mt-3 text-base font-medium text-gray-800">{{ $title }}</p>
    @if (trim((string) $slot) !== '')
        <div class="mt-1 text-sm text-gray-500">{{ $slot }}</div>
    @endif
</div>
