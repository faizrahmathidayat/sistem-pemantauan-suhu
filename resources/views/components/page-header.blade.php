@props(['title', 'description' => null, 'crumbs' => []])

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
        @if ($crumbs)
            {{-- HP: jalur halaman disembunyikan, jadi sediakan tautan kembali ke induknya --}}
            @php($parent = end($crumbs))
            <a href="{{ $parent['url'] }}" class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800 md:hidden">← {{ $parent['label'] }}</a>
        @endif
        <h1 class="text-xl font-semibold text-gray-800 sm:text-2xl">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
        @endif
    </div>
    <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
        @if ($crumbs)
            <nav aria-label="Jalur halaman" class="hidden text-sm text-gray-500 md:block">
                @foreach ($crumbs as $crumb)
                    <a href="{{ $crumb['url'] }}" class="hover:text-gray-800">{{ $crumb['label'] }}</a>
                    <span class="mx-1 text-gray-300">/</span>
                @endforeach
                <span class="text-gray-800">{{ $title }}</span>
            </nav>
        @endif
        {{ $slot }}
    </div>
</div>
