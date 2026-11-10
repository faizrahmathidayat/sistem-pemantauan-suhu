@props(['href', 'icon', 'active' => false])

<a href="{{ $href }}" @if ($active) aria-current="page" @endif
   @class([
       'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
       'bg-brand-50 text-brand-500' => $active,
       'text-gray-700 hover:bg-gray-100' => ! $active,
   ])>
    <x-icon :name="$icon" :class="'size-5 shrink-0 '.($active ? 'text-brand-500' : 'text-gray-500 group-hover:text-gray-700')" />
    <span>{{ $slot }}</span>
</a>
