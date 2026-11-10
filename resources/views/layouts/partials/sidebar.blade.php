{{-- Latar gelap di belakang sidebar (hanya HP): klik untuk menutup --}}
<div x-show="sidebarOpen" x-cloak x-transition.opacity @click="sidebarOpen = false"
     class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden print:hidden"></div>

<aside id="sidebar" data-open="false" :data-open="sidebarOpen" aria-label="Menu utama"
       class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-gray-200 bg-white transition-transform duration-200 data-[open=true]:translate-x-0 lg:translate-x-0 print:hidden">
    <div class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-gray-100 px-5">
        <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-500 text-white"><x-icon name="thermometer" class="size-5" /></span>
            <span class="truncate text-base font-semibold text-gray-800">{{ config('app.name') }}</span>
        </a>
        <button type="button" @click="sidebarOpen = false" aria-label="Tutup menu" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 lg:hidden">
            <x-icon name="close" />
        </button>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-4 py-5">
        @canany(['dashboard.view', 'incident.view', 'report.view', 'notification.view'])
            <div>
                <p class="mb-2 px-3 text-xs font-medium tracking-wide text-gray-400 uppercase">Menu</p>
                <div class="space-y-1">
                    @can('dashboard.view')
                        <x-nav-link :href="url('/dashboard')" icon="dashboard" :active="request()->routeIs('dashboard')">Dashboard</x-nav-link>
                    @endcan
                    @can('incident.view')
                        <x-nav-link :href="url('/incidents')" icon="incident" :active="request()->routeIs('incidents.*')">Riwayat Insiden</x-nav-link>
                    @endcan
                    @can('report.view')
                        <x-nav-link :href="url('/reports')" icon="report" :active="request()->routeIs('reports.*')">Laporan</x-nav-link>
                    @endcan
                    @can('notification.view')
                        <x-nav-link :href="url('/notifications')" icon="bell" :active="request()->routeIs('notifications.*')">Notifikasi</x-nav-link>
                    @endcan
                </div>
            </div>
        @endcanany

        @canany(['zone.view', 'warehouse.manage'])
            <div>
                <p class="mb-2 px-3 text-xs font-medium tracking-wide text-gray-400 uppercase">Pengaturan</p>
                <div class="space-y-1">
                    @can('zone.view')
                        <x-nav-link :href="url('/zones')" icon="zone" :active="request()->routeIs('zones.index', 'zones.edit', 'zones.update')">Batas Zona</x-nav-link>
                    @endcan
                    @can('warehouse.manage')
                        <x-nav-link :href="url('/warehouses')" icon="building" :active="request()->routeIs('warehouses.*', 'zones.manage*', 'zones.token', 'zones.destroy')">Gudang</x-nav-link>
                    @endcan
                </div>
            </div>
        @endcanany

        @canany(['user.manage', 'role.manage', 'auditlog.view'])
            <div>
                <p class="mb-2 px-3 text-xs font-medium tracking-wide text-gray-400 uppercase">Administrasi</p>
                <div class="space-y-1">
                    @can('user.manage')
                        <x-nav-link :href="url('/users')" icon="users" :active="request()->routeIs('users.*')">User</x-nav-link>
                    @endcan
                    @can('role.manage')
                        <x-nav-link :href="url('/roles')" icon="shield" :active="request()->routeIs('roles.*')">Role</x-nav-link>
                    @endcan
                    @can('auditlog.view')
                        <x-nav-link :href="url('/audit-logs')" icon="log" :active="request()->routeIs('audit-logs.*')">Log Perubahan</x-nav-link>
                    @endcan
                </div>
            </div>
        @endcanany
    </nav>
</aside>
