@props(['href', 'icon', 'active' => false])

{{-- title = tooltip nama menu saat sidebar diciutkan (hanya ikon yang terlihat) --}}
<a href="{{ $href }}" title="{{ trim(strip_tags((string) $slot)) }}" @if ($active) aria-current="page" @endif
   @class([
       'group flex items-center rounded-lg px-3 py-2.5 text-sm font-medium transition lg:group-data-[collapsed=true]/shell:justify-center',
       'bg-brand-50 text-brand-500' => $active,
       'text-gray-700 hover:bg-gray-100' => ! $active,
   ])>
    <x-icon :name="$icon" :class="'size-5 shrink-0 '.($active ? 'text-brand-500' : 'text-gray-500 group-hover:text-gray-700')" />
    <span class="sidebar-fade ml-3 max-w-48 truncate lg:group-data-[collapsed=true]/shell:ml-0 lg:group-data-[collapsed=true]/shell:max-w-0 lg:group-data-[collapsed=true]/shell:opacity-0">{{ $slot }}</span>
</a>
