@extends('layouts.app')

@section('title', 'Dashboard')

@section('banner')
    {{-- Banner kritis: menempel di atas layar selama ada insiden terbuka. Diisi dashboard.js --}}
    <div id="banners" class="sticky top-0 z-40"></div>
@endsection

@section('content')
    <div id="dashboard"
         data-data-url="{{ url('/dashboard/data') }}"
         data-readings-url="{{ url('/zones/__ID__/readings') }}"
         data-ack-url="{{ url('/incidents/__ID__/acknowledge') }}">

        <p id="scheduler-warning" hidden class="mb-3 rounded-lg bg-amber-100 px-3 py-2 text-sm text-amber-900">
            ⚠️ Pemeriksaan sensor offline tidak berjalan. Jalankan <code>php artisan schedule:work</code>.
        </p>
        <p id="connection-warning" hidden class="mb-3 rounded-lg bg-slate-200 px-3 py-2 text-sm text-slate-700">
            Koneksi ke server terputus, mencoba lagi…
        </p>

        @unless ($snapshot['has_access'])
            <p id="no-access-notice" class="mb-3 rounded-lg bg-slate-200 px-3 py-2 text-sm text-slate-700">
                Anda belum ditugaskan ke gudang mana pun. Hubungi administrator.
            </p>
        @endunless

        {{-- Chip gudang (status terburuk + jumlah insiden terbuka); diisi dashboard.js --}}
        <div id="warehouse-chips" class="mb-3 flex gap-2 overflow-x-auto whitespace-nowrap pb-1" aria-label="Pilih gudang"></div>

        {{-- Kartu zona: satu kolom di HP (zona bahaya paling atas), tiga kolom di desktop --}}
        <section id="zones" class="grid gap-3 md:grid-cols-3"></section>

        <section class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Tren 24 jam terakhir</h2>
            <div class="mt-2 flex gap-2 overflow-x-auto whitespace-nowrap pb-1" id="chart-zone-chips" aria-label="Pilih zona"></div>
            <div class="mt-2 flex gap-2 overflow-x-auto whitespace-nowrap pb-1" id="chart-metric-chips" aria-label="Pilih metrik">
                <button type="button" data-metric="temperature" class="chip chip-active">Suhu</button>
                <button type="button" data-metric="humidity" class="chip">Kelembapan</button>
            </div>
            <div class="relative mt-3 h-56 sm:h-72"><canvas id="trend-chart"></canvas></div>
            <p id="chart-empty" hidden class="mt-2 text-sm text-slate-500">Belum ada data pada rentang ini.</p>
        </section>

        <section class="mt-4 rounded-xl bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Insiden terbaru</h2>
                <a href="{{ url('/incidents') }}" class="text-sm text-emerald-700">Lihat semua</a>
            </div>
            <ul class="mt-2 divide-y divide-slate-100 text-sm">
                @forelse ($recentIncidents as $incident)
                    <li class="flex flex-wrap items-center justify-between gap-x-3 py-2">
                        <span class="font-medium">{{ $incident->zone->warehouse->name }} › {{ $incident->zone->name }} · {{ $incident->reason->label() }}</span>
                        <span class="text-slate-500">
                            {{ $incident->started_at->format('d M H:i') }}
                            @if ($incident->isOpen()) · berlangsung @else · selesai {{ $incident->ended_at->format('H:i') }} @endif
                        </span>
                        <span class="w-full text-slate-500">
                            @if ($incident->acknowledger) ✅ Ditangani oleh {{ $incident->acknowledger->name }} @else 🔴 Belum ditangani @endif
                        </span>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">Belum ada insiden.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <script id="dashboard-data" type="application/json">{!! json_encode($snapshot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@push('scripts')
    @vite(['resources/js/dashboard.js'])
@endpush
