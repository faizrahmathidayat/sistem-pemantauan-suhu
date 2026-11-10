@auth
    @php
        $user = auth()->user();
        $initials = \Illuminate\Support\Str::of($user->name)->explode(' ')->filter()->take(2)
            ->map(fn ($word) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($word, 0, 1)))->implode('');
    @endphp
    <header class="border-b border-gray-200 bg-white">
        <div class="flex h-16 items-center justify-between gap-3 px-4 md:px-6">
            <div class="flex min-w-0 items-center gap-3">
                {{-- HP: buka panel menu --}}
                <button type="button" @click="sidebarOpen = true" :aria-expanded="sidebarOpen" aria-label="Buka menu" aria-controls="sidebar"
                        class="rounded-lg border border-gray-200 p-2 text-gray-600 hover:bg-gray-50 lg:hidden">
                    <x-icon name="menu" />
                </button>
                {{-- Layar lebar: ciutkan sidebar (hanya ikon) atau lebarkan kembali --}}
                <button type="button" @click="toggleSidebar()" aria-label="Ciutkan sidebar"
                        :aria-label="sidebarExpanded ? 'Ciutkan sidebar' : 'Lebarkan sidebar'" :aria-expanded="sidebarExpanded" aria-controls="sidebar"
                        class="hidden rounded-lg border border-gray-200 p-2 text-gray-600 hover:bg-gray-50 lg:inline-flex">
                    <x-icon name="chevrons-left" class="size-5 transition-transform duration-300 lg:group-data-[collapsed=true]/shell:rotate-180" />
                </button>
                <span class="hidden truncate text-sm font-medium text-gray-500 sm:block">@yield('title', 'Dashboard')</span>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                @can('notification.view')
                    @php($unread = $user->unreadNotifications()->count())
                    <a href="{{ url('/notifications') }}" class="relative rounded-full border border-gray-200 p-2.5 text-gray-600 hover:bg-gray-50" aria-label="Notifikasi" title="Notifikasi">
                        <x-icon name="bell" />
                        <span data-unread-badge @if ($unread === 0) hidden @endif
                              class="absolute -top-1 -right-1 min-w-5 rounded-full bg-error-600 px-1.5 text-center text-[11px] leading-5 font-semibold text-white">{{ $unread }}</span>
                    </a>
                @endcan

                <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                    <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="menu"
                            class="flex items-center gap-2 rounded-full py-1 pr-2 pl-1 hover:bg-gray-50">
                        <span class="flex size-9 items-center justify-center rounded-full bg-brand-500 text-sm font-semibold text-white">{{ $initials }}</span>
                        <span class="hidden max-w-40 truncate text-sm font-medium text-gray-700 sm:block">{{ $user->name }}</span>
                        <x-icon name="chevron-down" class="size-4 text-gray-500" />
                    </button>
                    <div x-show="open" x-cloak x-transition role="menu"
                         class="absolute right-0 z-50 mt-2 w-64 rounded-2xl border border-gray-200 bg-white p-3 shadow-lg">
                        <div class="border-b border-gray-100 px-2 pb-3">
                            <p class="truncate text-sm font-medium text-gray-800">{{ $user->name }}</p>
                            <p class="truncate text-xs text-gray-500">{{ $user->email }}</p>
                            <p class="mt-1 text-xs text-gray-500">Role: <span class="font-medium text-gray-700">{{ $user->role?->name ?? 'Belum ada role' }}</span></p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="pt-2">
                            @csrf
                            <button type="submit" role="menuitem" class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                                <x-icon name="logout" class="size-5 text-gray-500" /> Keluar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>
@endauth
