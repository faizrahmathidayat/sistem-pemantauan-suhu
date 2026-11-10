@extends('layouts.app')

@section('title', 'Dashboard')

@section('banner')
    {{-- Banner kritis: menempel di bawah header selama ada insiden terbuka. Diisi dashboard.js --}}
    <div id="banners"></div>
@endsection

@section('content')
    <div id="dashboard"
         data-data-url="{{ url('/dashboard/data') }}"
         data-readings-url="{{ url('/zones/__ID__/readings') }}"
         data-ack-url="{{ url('/incidents/__ID__/acknowledge') }}">

        <x-page-header title="Dashboard" description="Pantau suhu dan kelembapan semua zona gudang secara langsung." />

        <x-alert variant="warning" id="scheduler-warning" hidden class="mb-4">
            Pemeriksaan sensor offline tidak berjalan. Jalankan <code class="rounded bg-warning-100 px-1">php artisan schedule:work</code>.
        </x-alert>
        <x-alert variant="info" id="connection-warning" hidden class="mb-4">
            Koneksi ke server terputus, mencoba lagi…
        </x-alert>

        @unless ($snapshot['has_access'])
            <x-alert variant="info" id="no-access-notice" class="mb-4">
                Anda belum ditugaskan ke gudang mana pun. Hubungi administrator.
            </x-alert>
        @endunless

        {{-- Chip gudang (status terburuk + jumlah insiden terbuka); diisi dashboard.js --}}
        <div id="warehouse-chips" class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 whitespace-nowrap md:mx-0 md:px-0" aria-label="Pilih gudang"></div>

        {{-- Kartu zona: satu kolom di HP (zona bahaya paling atas, diurutkan server), tiga kolom di desktop --}}
        <section id="zones" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"></section>

        <x-card title="Tren 24 jam terakhir" class="mt-6">
            <x-slot:actions>
                @can('report.view')
                    <a href="{{ route('reports.index') }}" class="text-sm font-medium text-brand-500 hover:underline">Riwayat lengkap</a>
                @endcan
            </x-slot:actions>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex max-w-full gap-2 overflow-x-auto pb-1 whitespace-nowrap" id="chart-zone-chips" aria-label="Pilih zona"></div>
                <div class="flex gap-2" id="chart-metric-chips" aria-label="Pilih metrik">
                    <button type="button" data-metric="temperature" class="chip chip-active">Suhu</button>
                    <button type="button" data-metric="humidity" class="chip">Kelembapan</button>
                </div>
            </div>
            <div class="relative mt-4 h-64 sm:h-80"><canvas id="trend-chart"></canvas></div>
            <p id="chart-empty" hidden class="mt-2 text-sm text-gray-500">Belum ada data pada rentang ini.</p>
        </x-card>

        <x-card title="Insiden terbaru" class="mt-6" flush>
            <x-slot:actions>
                <a href="{{ url('/incidents') }}" class="text-sm font-medium text-brand-500 hover:underline">Lihat semua</a>
            </x-slot:actions>

            <ul class="divide-y divide-gray-100">
                @forelse ($recentIncidents as $incident)
                    <li class="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-800">{{ $incident->zone->warehouse->name }} › {{ $incident->zone->name }}</p>
                            <p class="text-sm text-gray-500">
                                {{ $incident->reason->label() }} · {{ $incident->started_at->format('d M H:i') }}
                                @if ($incident->isOpen()) · berlangsung @else · selesai {{ $incident->ended_at->format('H:i') }} @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @unless ($incident->isOpen())
                                <x-badge variant="gray">Selesai</x-badge>
                            @endunless
                            @if ($incident->acknowledger)
                                <x-badge variant="success">Ditangani oleh {{ $incident->acknowledger->name }}</x-badge>
                            @else
                                <x-badge variant="error">Belum ditangani</x-badge>
                            @endif
                        </div>
                    </li>
                @empty
                    <li><x-empty title="Belum ada insiden." icon="check" /></li>
                @endforelse
            </ul>
        </x-card>
    </div>

    <script id="dashboard-data" type="application/json">{!! json_encode($snapshot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@push('scripts')
    @vite(['resources/js/dashboard.js'])
@endpush
