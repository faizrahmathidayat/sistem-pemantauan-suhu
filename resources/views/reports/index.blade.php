@extends('layouts.app')

@section('title', 'Laporan')

@section('content')
    @php
        $n = fn ($value) => $value === null ? '–' : number_format($value, 1, ',', '.');
        $duration = function (int $minutes): string {
            if ($minutes < 60) {
                return $minutes.' mnt';
            }
            $rest = $minutes % 60;

            return intdiv($minutes, 60).' jam'.($rest > 0 ? " {$rest} mnt" : '');
        };
        // Persentase pembacaan dalam batas: hijau ≥ 98 %, kuning ≥ 90 %, merah di bawahnya.
        $percent = fn ($value) => $value === null
            ? ['gray', '–']
            : [$value >= 98 ? 'success' : ($value >= 90 ? 'warning' : 'error'), $n($value).' %'];
        $today = now();
        $earliest = $today->copy()->subDays(89)->format('Y-m-d');
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
        <x-page-header title="Laporan" description="Riwayat suhu dan kelembapan per zona. Data disimpan 90 hari." />
    </div>

    @if ($warehouses->isEmpty())
        <x-card>
            <x-empty title="Anda belum ditugaskan ke gudang mana pun." icon="building">Hubungi administrator.</x-empty>
        </x-card>
    @else
        <x-card class="print:hidden">
            <form method="GET" action="{{ route('reports.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                <div>
                    <label for="filter-gudang" class="form-label">Gudang</label>
                    <select id="filter-gudang" name="gudang" class="form-input" onchange="this.form.zona.selectedIndex = -1; this.form.submit()">
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected($filters->warehouse?->id === $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-zona" class="form-label">Zona (untuk grafik dan data mentah)</label>
                    <select id="filter-zona" name="zona" class="form-input">
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}" @selected($filters->zone?->id === $zone->id)>{{ $zone->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-periode" class="form-label">Periode</label>
                    <select id="filter-periode" name="periode" class="form-input"
                            onchange="document.getElementById('custom-dates').hidden = this.value !== 'custom'">
                        @foreach (\App\Support\ReportPeriod::PRESETS as $key => $label)
                            <option value="{{ $key }}" @selected($filters->periodKey === $key)>{{ $label }}</option>
                        @endforeach
                        <option value="custom" @selected($filters->periodKey === 'custom')>Tanggal sendiri</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-full">Terapkan</button>
                <div id="custom-dates" class="grid gap-4 sm:col-span-2 sm:grid-cols-2 lg:col-span-4" @if ($filters->periodKey !== 'custom') hidden @endif>
                    <div>
                        <label for="filter-dari" class="form-label">Dari</label>
                        <input id="filter-dari" type="date" name="dari" value="{{ $filters->from }}" min="{{ $earliest }}" max="{{ $today->format('Y-m-d') }}" class="form-input">
                    </div>
                    <div>
                        <label for="filter-sampai" class="form-label">Sampai</label>
                        <input id="filter-sampai" type="date" name="sampai" value="{{ $filters->to }}" min="{{ $earliest }}" max="{{ $today->format('Y-m-d') }}" class="form-input">
                    </div>
                </div>
            </form>
        </x-card>

        @if ($filters->error)
            <x-alert variant="error" role="alert" class="mt-4 print:hidden">{{ $filters->error }}</x-alert>
        @endif

        @if ($rows !== null)
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
                <p class="text-sm text-gray-500">{{ $filters->warehouse->name }} · {{ $filters->period->label() }}</p>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                    <a href="{{ route('reports.summary-csv', $downloadQuery) }}" class="btn btn-secondary btn-sm"><x-icon name="download" class="size-4" /> Unduh ringkasan (CSV)</a>
                    @if ($filters->zone)
                        <a href="{{ route('reports.readings-csv', $downloadQuery) }}" class="btn btn-secondary btn-sm"><x-icon name="download" class="size-4" /> Unduh data mentah (CSV)</a>
                    @endif
                    <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm"><x-icon name="print" class="size-4" /> Cetak / Simpan PDF</button>
                </div>
            </div>

            <x-card title="Ringkasan per zona" flush class="mt-4">
                @unless ($hasData)
                    <p class="px-5 pt-4 text-sm text-gray-500 sm:px-6">Belum ada data pada periode ini.</p>
                @endunless

                {{-- Layar lebar dan cetak: tabel --}}
                <div class="hidden xl:block print:block">
                    <x-table>
                        <thead class="border-b border-gray-100">
                            <tr>
                                <th class="table-th">Zona</th>
                                <th class="table-th text-right">Pembacaan</th>
                                <th class="table-th text-right">Suhu min / maks / rata-rata (°C)</th>
                                <th class="table-th text-right">Kelembapan min / maks / rata-rata (%)</th>
                                <th class="table-th text-right">Dalam batas</th>
                                <th class="table-th text-right">Insiden</th>
                                <th class="table-th text-right">Lama insiden</th>
                                <th class="table-th text-right">Sensor offline</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($rows as $row)
                                @php([$tone, $text] = $percent($row['in_range_percent']))
                                <tr>
                                    <td class="table-td font-medium text-gray-800">{{ $row['zone']->name }}</td>
                                    <td class="table-td text-right">{{ number_format($row['readings'], 0, ',', '.') }}</td>
                                    <td class="table-td text-right whitespace-nowrap">{{ $n($row['temperature']['min']) }} / {{ $n($row['temperature']['max']) }} / {{ $n($row['temperature']['avg']) }}</td>
                                    <td class="table-td text-right whitespace-nowrap">{{ $n($row['humidity']['min']) }} / {{ $n($row['humidity']['max']) }} / {{ $n($row['humidity']['avg']) }}</td>
                                    <td class="table-td text-right"><x-badge :variant="$tone">{{ $text }}</x-badge></td>
                                    <td class="table-td text-right">{{ $row['incident_count'] }}</td>
                                    <td class="table-td text-right whitespace-nowrap">{{ $duration($row['incident_minutes']) }}</td>
                                    <td class="table-td text-right whitespace-nowrap">{{ $duration($row['offline_minutes']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                </div>

                {{-- HP dan layar sedang: satu kartu per zona --}}
                <ul data-mobile-list class="grid gap-3 p-4 sm:p-5 md:grid-cols-2 xl:hidden print:hidden">
                    @foreach ($rows as $row)
                        @php([$tone, $text] = $percent($row['in_range_percent']))
                        <li class="rounded-xl border border-gray-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <p class="min-w-0 truncate text-base font-semibold text-gray-800">{{ $row['zone']->name }}</p>
                                <x-badge :variant="$tone" class="shrink-0">{{ $text }}</x-badge>
                            </div>
                            <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                                <div><dt class="text-xs text-gray-500">Pembacaan</dt><dd class="font-medium text-gray-800">{{ number_format($row['readings'], 0, ',', '.') }}</dd></div>
                                <div><dt class="text-xs text-gray-500">Insiden</dt><dd class="font-medium text-gray-800">{{ $row['incident_count'] }}</dd></div>
                                <div class="col-span-2"><dt class="text-xs text-gray-500">Suhu min / maks / rata-rata (°C)</dt><dd class="font-medium text-gray-800">{{ $n($row['temperature']['min']) }} / {{ $n($row['temperature']['max']) }} / {{ $n($row['temperature']['avg']) }}</dd></div>
                                <div class="col-span-2"><dt class="text-xs text-gray-500">Kelembapan min / maks / rata-rata (%)</dt><dd class="font-medium text-gray-800">{{ $n($row['humidity']['min']) }} / {{ $n($row['humidity']['max']) }} / {{ $n($row['humidity']['avg']) }}</dd></div>
                                <div><dt class="text-xs text-gray-500">Lama insiden</dt><dd class="font-medium text-gray-800">{{ $duration($row['incident_minutes']) }}</dd></div>
                                <div><dt class="text-xs text-gray-500">Sensor offline</dt><dd class="font-medium text-gray-800">{{ $duration($row['offline_minutes']) }}</dd></div>
                            </dl>
                        </li>
                    @endforeach
                </ul>

                <p class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500 sm:px-6">"Dalam batas" dihitung terhadap batas zona saat ini, bukan batas pada saat pembacaan. "Insiden" tidak menghitung sensor offline (punya kolom sendiri).</p>
            </x-card>

            @if ($chart !== null)
                <x-card :title="'Grafik '.$chart['zone']" class="mt-6">
                    <div class="flex gap-2 print:hidden" aria-label="Pilih metrik">
                        <button type="button" data-metric="temperature" class="chip chip-active">Suhu</button>
                        <button type="button" data-metric="humidity" class="chip">Kelembapan</button>
                    </div>
                    <div class="relative mt-4 h-64 sm:h-80"><canvas id="report-chart"></canvas></div>
                    <p id="chart-empty" @if (count($chart['labels']) > 0) hidden @endif class="mt-2 text-sm text-gray-500">Belum ada data pada periode ini.</p>
                    <p class="mt-3 text-xs text-gray-500">Garis tebal: rata-rata per {{ $chart['bucket_seconds'] >= 3600 ? $chart['bucket_seconds'] / 3600 .' jam' : $chart['bucket_seconds'] / 60 .' menit' }}; garis tipis: nilai terendah dan tertinggi pada rentang itu; garis putus-putus: batas zona saat ini.</p>
                </x-card>

                <x-card :title="'Insiden '.$chart['zone']" flush class="mt-6">
                    <ul class="divide-y divide-gray-100 text-sm">
                        @forelse ($incidents as $incident)
                            <li class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <span class="font-medium text-gray-800">{{ $incident->reason->label() }}</span>
                                <span class="text-gray-500">
                                    {{ $incident->started_at->format('d M Y H:i') }}
                                    @if ($incident->isOpen()) · masih berlangsung @else · selesai {{ $incident->ended_at->format('d M H:i') }} @endif
                                    · {{ $incident->durationLabel() }}
                                    @if ($incident->reason !== \App\Enums\IncidentReason::SensorOffline) · puncak {{ $n($incident->peak_value) }} {{ $incident->reason->unit() }} @endif
                                </span>
                            </li>
                        @empty
                            <li class="px-5 py-4 text-gray-500 sm:px-6">Tidak ada insiden pada periode ini.</li>
                        @endforelse
                    </ul>
                </x-card>

                <script id="report-data" type="application/json">{!! json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
            @endif
        @endif
    @endif
@endsection

@push('scripts')
    @vite(['resources/js/reports.js'])
@endpush
