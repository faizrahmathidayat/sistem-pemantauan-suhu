@extends('layouts.app')

@section('title', 'Riwayat Insiden')

@section('content')
    <x-page-header title="Riwayat Insiden" description="Kapan suhu atau kelembapan keluar batas, berapa lama, dan siapa yang menanganinya." />

    @php
        $peak = fn ($i) => $i->peak_value === null ? '–' : number_format($i->peak_value, 1, ',', '.').$i->reason->unit();
    @endphp

    @if ($incidents->isEmpty())
        <x-card>
            <x-empty title="Belum ada insiden." icon="check">Semua zona berjalan dalam batas.</x-empty>
        </x-card>
    @else
        {{-- Layar lebar (≥ xl, setelah sidebar): tabel 7 kolom --}}
        <x-card flush class="hidden xl:block">
            <x-table>
                <thead class="border-b border-gray-100">
                    <tr>
                        <th class="table-th">Zona</th>
                        <th class="table-th">Jenis</th>
                        <th class="table-th">Mulai</th>
                        <th class="table-th">Selesai</th>
                        <th class="table-th">Durasi</th>
                        <th class="table-th">Puncak</th>
                        <th class="table-th">Penanganan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($incidents as $incident)
                        <tr>
                            <td class="table-td">
                                <span class="block font-medium text-gray-800">{{ $incident->zone->name }}</span>
                                <span class="block text-xs">{{ $incident->zone->warehouse->name }}</span>
                            </td>
                            <td class="table-td">{{ $incident->reason->label() }}</td>
                            <td class="table-td whitespace-nowrap">{{ $incident->started_at->format('d M Y H:i') }}</td>
                            <td class="table-td whitespace-nowrap">
                                @if ($incident->isOpen()) <x-badge variant="warning">Berlangsung</x-badge> @else {{ $incident->ended_at->format('d M Y H:i') }} @endif
                            </td>
                            <td class="table-td whitespace-nowrap">{{ $incident->durationLabel() }}</td>
                            <td class="table-td whitespace-nowrap">{{ $peak($incident) }}</td>
                            <td class="table-td">
                                @if ($incident->acknowledger)
                                    <x-badge variant="success">Ditangani oleh {{ $incident->acknowledger->name }}</x-badge>
                                @else
                                    <x-badge variant="error">Belum ditangani</x-badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>

        {{-- HP dan layar sedang: daftar kartu (zona dan durasi di atas, status penanganan di bawah) --}}
        <ul data-mobile-list class="grid gap-3 md:grid-cols-2 xl:hidden">
            @foreach ($incidents as $incident)
                <li class="rounded-2xl border border-gray-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold text-gray-800">{{ $incident->zone->name }}</p>
                            <p class="truncate text-xs text-gray-500">{{ $incident->zone->warehouse->name }}</p>
                        </div>
                        <span class="shrink-0 text-sm font-medium text-gray-600">{{ $incident->durationLabel() }}</span>
                    </div>
                    <p class="mt-2 text-sm text-gray-700">{{ $incident->reason->label() }} · puncak {{ $peak($incident) }}</p>
                    <p class="text-xs text-gray-500">
                        {{ $incident->started_at->format('d M H:i') }} → {{ $incident->ended_at?->format('d M H:i') ?? 'berlangsung' }}
                    </p>
                    <div class="mt-3">
                        @if ($incident->acknowledger)
                            <x-badge variant="success">Ditangani oleh {{ $incident->acknowledger->name }}</x-badge>
                        @else
                            <x-badge variant="error">Belum ditangani</x-badge>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $incidents->links() }}</div>
    @endif
@endsection
