@extends('layouts.app')

@section('title', 'Laporan')

@section('content')
    @php
        $input = 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-base';
        $n = fn ($value) => $value === null ? '–' : number_format($value, 1, ',', '.');
        $duration = function (int $minutes): string {
            if ($minutes < 60) {
                return $minutes.' mnt';
            }
            $rest = $minutes % 60;

            return intdiv($minutes, 60).' jam'.($rest > 0 ? " {$rest} mnt" : '');
        };
        $today = now();
    @endphp

    {{-- Judul khusus cetak (menu dan formulir disembunyikan saat dicetak) --}}
    <div class="hidden print:block">
        <h1 class="text-xl font-semibold">Laporan sensor {{ $filters->warehouse?->name }}</h1>
        @if ($filters->zone)
            <p class="text-sm">Zona: {{ $filters->zone->name }}</p>
        @endif
        <p class="text-sm">Periode: {{ $filters->period->label() }}</p>
        <p class="text-sm">Dicetak oleh {{ auth()->user()->name }} pada {{ now()->format('d-m-Y H:i') }}</p>
    </div>

    <div class="print:hidden">
        <h1 class="text-lg font-semibold">Laporan</h1>
        <p class="text-sm text-slate-500">Riwayat suhu dan kelembapan per zona. Data disimpan 90 hari.</p>
    </div>

    @if ($warehouses->isEmpty())
        <p class="mt-3 rounded-xl bg-white p-4 text-slate-500 shadow-sm">Anda belum ditugaskan ke gudang mana pun. Hubungi administrator.</p>
    @else
        <form method="GET" action="{{ route('reports.index') }}" class="mt-3 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm print:hidden">
            <label class="block text-sm">
                <span class="mb-1 block font-medium">Gudang</span>
                <select name="gudang" class="{{ $input }}" onchange="this.form.zona.selectedIndex = -1; this.form.submit()">
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected($filters->warehouse?->id === $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm">
                <span class="mb-1 block font-medium">Zona (untuk grafik dan data mentah)</span>
                <select name="zona" class="{{ $input }}">
                    @foreach ($zones as $zone)
                        <option value="{{ $zone->id }}" @selected($filters->zone?->id === $zone->id)>{{ $zone->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm">
                <span class="mb-1 block font-medium">Periode</span>
                <select name="periode" class="{{ $input }}"
                        onchange="document.getElementById('custom-dates').hidden = this.value !== 'custom'">
                    @foreach (\App\Support\ReportPeriod::PRESETS as $key => $label)
                        <option value="{{ $key }}" @selected($filters->periodKey === $key)>{{ $label }}</option>
                    @endforeach
                    <option value="custom" @selected($filters->periodKey === 'custom')>Tanggal sendiri</option>
                </select>
            </label>
            <div id="custom-dates" class="flex flex-wrap gap-3" @if ($filters->periodKey !== 'custom') hidden @endif>
                <label class="block text-sm">
                    <span class="mb-1 block font-medium">Dari</span>
                    <input type="date" name="dari" value="{{ $filters->from }}" min="{{ $today->copy()->subDays(89)->format('Y-m-d') }}" max="{{ $today->format('Y-m-d') }}" class="{{ $input }}">
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block font-medium">Sampai</span>
                    <input type="date" name="sampai" value="{{ $filters->to }}" min="{{ $today->copy()->subDays(89)->format('Y-m-d') }}" max="{{ $today->format('Y-m-d') }}" class="{{ $input }}">
                </label>
            </div>
            <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2 font-semibold text-white hover:bg-emerald-700">Terapkan</button>
        </form>

        @if ($filters->error)
            <p role="alert" class="mt-3 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">{{ $filters->error }}</p>
        @endif

        @if ($rows !== null)
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 print:hidden">
                <p class="text-sm text-slate-500">{{ $filters->warehouse->name }} · {{ $filters->period->label() }}</p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('reports.summary-csv', $downloadQuery) }}" class="rounded-lg bg-white px-3 py-1.5 text-sm font-medium shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">Unduh ringkasan (CSV)</a>
                    @if ($filters->zone)
                        <a href="{{ route('reports.readings-csv', $downloadQuery) }}" class="rounded-lg bg-white px-3 py-1.5 text-sm font-medium shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">Unduh data mentah (CSV)</a>
                    @endif
                    <button type="button" onclick="window.print()" class="rounded-lg bg-white px-3 py-1.5 text-sm font-medium shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">Cetak / Simpan PDF</button>
                </div>
            </div>

            <section class="mt-3 rounded-xl bg-white p-4 shadow-sm">
                <h2 class="font-semibold">Ringkasan per zona</h2>
                @unless ($hasData)
                    <p class="mt-2 text-sm text-slate-500">Belum ada data pada periode ini.</p>
                @endunless
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full min-w-[56rem] text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="py-2 pr-3">Zona</th>
                                <th class="px-3 py-2 text-right">Pembacaan</th>
                                <th class="px-3 py-2 text-right">Suhu min / maks / rata-rata (°C)</th>
                                <th class="px-3 py-2 text-right">Kelembapan min / maks / rata-rata (%)</th>
                                <th class="px-3 py-2 text-right">Dalam batas</th>
                                <th class="px-3 py-2 text-right">Insiden</th>
                                <th class="px-3 py-2 text-right">Lama insiden</th>
                                <th class="py-2 pl-3 text-right">Sensor offline</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="py-2 pr-3 font-medium">{{ $row['zone']->name }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($row['readings'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right">{{ $n($row['temperature']['min']) }} / {{ $n($row['temperature']['max']) }} / {{ $n($row['temperature']['avg']) }}</td>
                                    <td class="px-3 py-2 text-right">{{ $n($row['humidity']['min']) }} / {{ $n($row['humidity']['max']) }} / {{ $n($row['humidity']['avg']) }}</td>
                                    <td class="px-3 py-2 text-right">{{ $row['in_range_percent'] === null ? '–' : $n($row['in_range_percent']).' %' }}</td>
                                    <td class="px-3 py-2 text-right">{{ $row['incident_count'] }}</td>
                                    <td class="px-3 py-2 text-right">{{ $duration($row['incident_minutes']) }}</td>
                                    <td class="py-2 pl-3 text-right">{{ $duration($row['offline_minutes']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-slate-500">"Dalam batas" dihitung terhadap batas zona saat ini, bukan batas pada saat pembacaan. "Insiden" tidak menghitung sensor offline (punya kolom sendiri).</p>
            </section>

            @if ($chart !== null)
                <section class="mt-4 rounded-xl bg-white p-4 shadow-sm">
                    <h2 class="font-semibold">Grafik {{ $chart['zone'] }}</h2>
                    <div class="mt-2 flex gap-2 print:hidden" aria-label="Pilih metrik">
                        <button type="button" data-metric="temperature" class="chip chip-active">Suhu</button>
                        <button type="button" data-metric="humidity" class="chip">Kelembapan</button>
                    </div>
                    <div class="relative mt-3 h-64 sm:h-80"><canvas id="report-chart"></canvas></div>
                    <p id="chart-empty" @if (count($chart['labels']) > 0) hidden @endif class="mt-2 text-sm text-slate-500">Belum ada data pada periode ini.</p>
                    <p class="mt-2 text-xs text-slate-500">Garis tebal: rata-rata per {{ $chart['bucket_seconds'] >= 3600 ? $chart['bucket_seconds'] / 3600 .' jam' : $chart['bucket_seconds'] / 60 .' menit' }}; garis tipis: nilai terendah dan tertinggi pada rentang itu; garis putus-putus: batas zona saat ini.</p>
                </section>

                <section class="mt-4 rounded-xl bg-white p-4 shadow-sm">
                    <h2 class="font-semibold">Insiden {{ $chart['zone'] }}</h2>
                    <ul class="mt-2 divide-y divide-slate-100 text-sm">
                        @forelse ($incidents as $incident)
                            <li class="flex flex-wrap items-center justify-between gap-x-3 py-2">
                                <span class="font-medium">{{ $incident->reason->label() }}</span>
                                <span class="text-slate-500">
                                    {{ $incident->started_at->format('d M Y H:i') }}
                                    @if ($incident->isOpen()) · masih berlangsung @else · selesai {{ $incident->ended_at->format('d M H:i') }} @endif
                                    · {{ $incident->durationLabel() }}
                                    @if ($incident->reason !== \App\Enums\IncidentReason::SensorOffline) · puncak {{ $n($incident->peak_value) }} {{ $incident->reason->unit() }} @endif
                                </span>
                            </li>
                        @empty
                            <li class="py-2 text-slate-500">Tidak ada insiden pada periode ini.</li>
                        @endforelse
                    </ul>
                </section>

                <script id="report-data" type="application/json">{!! json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
            @endif
        @endif
    @endif
@endsection

@push('scripts')
    @vite(['resources/js/reports.js'])
@endpush
