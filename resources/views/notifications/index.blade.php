@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-lg font-semibold">Notifikasi</h1>
        <form method="POST" action="{{ url('/notifications/read-all') }}">
            @csrf
            <button type="submit" class="rounded-lg bg-white px-3 py-2 text-sm shadow-sm hover:bg-slate-50">Tandai semua terbaca</button>
        </form>
    </div>

    <ul class="mt-3 space-y-2">
        @forelse ($notifications as $notification)
            @php($data = $notification->data)
            <li class="rounded-xl p-3 shadow-sm {{ $notification->read_at ? 'bg-white' : 'border-l-4 border-red-500 bg-red-50' }}">
                <div class="flex items-start justify-between gap-2">
                    <p class="font-semibold">{{ $data['title'] ?? 'Notifikasi' }}</p>
                    @unless ($notification->read_at)
                        <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">Baru</span>
                    @endunless
                </div>
                <p class="text-sm text-slate-600">
                    @if (isset($data['temperature'])) 🌡️ {{ number_format($data['temperature'], 1, ',', '.') }}°C @endif
                    @if (isset($data['humidity'])) · 💧 {{ number_format($data['humidity'], 0) }}% @endif
                    @if (isset($data['limit'])) · batas {{ number_format($data['limit'], 1, ',', '.') }} @endif
                    · ⏱️ {{ \Illuminate\Support\Carbon::parse($data['occurred_at'])->timezone(config('app.timezone'))->format('d M Y H:i') }} WIB
                </p>
                <p class="mt-1 text-sm">{{ $data['advice'] ?? '' }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                    <a href="{{ url($data['url'] ?? '/dashboard') }}" class="font-medium text-emerald-700">🔗 Buka Dashboard Monitoring</a>
                    @unless ($notification->read_at)
                        <form method="POST" action="{{ url('/notifications/'.$notification->id.'/read') }}">
                            @csrf
                            <button type="submit" class="text-slate-500 underline">Tandai terbaca</button>
                        </form>
                    @endunless
                </div>
            </li>
        @empty
            <li class="rounded-xl bg-white p-4 text-slate-500 shadow-sm">Belum ada notifikasi.</li>
        @endforelse
    </ul>

    <div class="mt-4">{{ $notifications->links() }}</div>
@endsection
