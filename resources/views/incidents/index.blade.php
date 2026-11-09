@extends('layouts.app')

@section('title', 'Riwayat Insiden')

@section('content')
    <h1 class="text-lg font-semibold">Riwayat Insiden</h1>
    <p class="text-sm text-slate-500">Kapan suhu atau kelembapan keluar batas, berapa lama, dan siapa yang menanganinya.</p>

    @php
        $peak = fn ($i) => $i->peak_value === null ? '–' : number_format($i->peak_value, 1, ',', '.').$i->reason->unit();
    @endphp

    @if ($incidents->isEmpty())
        <p class="mt-4 rounded-xl bg-white p-4 text-slate-500 shadow-sm">Belum ada insiden.</p>
    @else
        {{-- Desktop: tabel --}}
        <div class="mt-3 hidden overflow-hidden rounded-xl bg-white shadow-sm md:block">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Zona</th><th class="px-4 py-2">Jenis</th><th class="px-4 py-2">Mulai</th>
                        <th class="px-4 py-2">Selesai</th><th class="px-4 py-2">Durasi</th><th class="px-4 py-2">Puncak</th>
                        <th class="px-4 py-2">Penanganan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($incidents as $incident)
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $incident->zone->name }}</td>
                            <td class="px-4 py-2">{{ $incident->reason->label() }}</td>
                            <td class="px-4 py-2">{{ $incident->started_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-2">{{ $incident->ended_at?->format('d M Y H:i') ?? 'berlangsung' }}</td>
                            <td class="px-4 py-2">{{ $incident->durationLabel() }}</td>
                            <td class="px-4 py-2">{{ $peak($incident) }}</td>
                            <td class="px-4 py-2">
                                @if ($incident->acknowledger) ✅ Ditangani oleh {{ $incident->acknowledger->name }} @else 🔴 Belum ditangani @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile: daftar kartu (zona + durasi di atas, status penanganan di bawah) --}}
        <ul class="mt-3 space-y-2 md:hidden">
            @foreach ($incidents as $incident)
                <li class="rounded-xl bg-white p-3 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-semibold">{{ $incident->zone->name }}</span>
                        <span class="text-sm font-medium text-slate-600">{{ $incident->durationLabel() }}</span>
                    </div>
                    <p class="text-sm text-slate-600">{{ $incident->reason->label() }} · puncak {{ $peak($incident) }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $incident->started_at->format('d M H:i') }} → {{ $incident->ended_at?->format('d M H:i') ?? 'berlangsung' }}
                    </p>
                    <p class="mt-1 text-sm">
                        @if ($incident->acknowledger) ✅ Ditangani oleh {{ $incident->acknowledger->name }} @else 🔴 Belum ditangani @endif
                    </p>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $incidents->links() }}</div>
    @endif
@endsection
