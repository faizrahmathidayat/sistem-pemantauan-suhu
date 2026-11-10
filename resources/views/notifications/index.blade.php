@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')
    <x-page-header title="Notifikasi" description="Peringatan saat suhu atau kelembapan keluar batas, atau sensor berhenti mengirim data.">
        <form method="POST" action="{{ url('/notifications/read-all') }}" class="w-full sm:w-auto">
            @csrf
            <button type="submit" class="btn btn-secondary w-full sm:w-auto">Tandai semua terbaca</button>
        </form>
    </x-page-header>

    <ul class="space-y-3">
        @forelse ($notifications as $notification)
            @php($data = $notification->data)
            <li @if (! $notification->read_at) data-unread="true" @endif
                class="rounded-2xl border bg-white p-4 sm:p-5 {{ $notification->read_at ? 'border-gray-200' : 'border-error-100 border-l-4 border-l-error-600 bg-error-50/40' }}">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-base font-semibold text-gray-800">{{ $data['title'] ?? 'Notifikasi' }}</p>
                    @unless ($notification->read_at)
                        <x-badge variant="error" class="shrink-0">Baru</x-badge>
                    @endunless
                </div>
                <p class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600">
                    @if (isset($data['temperature']))
                        <span class="inline-flex items-center gap-1"><x-icon name="thermometer" class="size-4 text-gray-500" /> {{ number_format($data['temperature'], 1, ',', '.') }}°C</span>
                    @endif
                    @if (isset($data['humidity']))
                        <span class="inline-flex items-center gap-1"><x-icon name="droplet" class="size-4 text-gray-500" /> {{ number_format($data['humidity'], 0) }}%</span>
                    @endif
                    @if (isset($data['limit']))
                        <span>batas {{ number_format($data['limit'], 1, ',', '.') }}</span>
                    @endif
                    <span class="inline-flex items-center gap-1"><x-icon name="clock" class="size-4 text-gray-500" /> {{ \Illuminate\Support\Carbon::parse($data['occurred_at'])->timezone(config('app.timezone'))->format('d M Y H:i') }} WIB</span>
                </p>
                <p class="mt-2 text-sm text-gray-700">{{ $data['advice'] ?? '' }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                    <a href="{{ url($data['url'] ?? '/dashboard') }}" class="font-medium text-brand-500 hover:underline">Buka Dashboard Monitoring</a>
                    @unless ($notification->read_at)
                        <form method="POST" action="{{ url('/notifications/'.$notification->id.'/read') }}">
                            @csrf
                            <button type="submit" class="text-gray-500 underline hover:text-gray-700">Tandai terbaca</button>
                        </form>
                    @endunless
                </div>
            </li>
        @empty
            <li class="rounded-2xl border border-gray-200 bg-white"><x-empty title="Belum ada notifikasi." icon="bell" /></li>
        @endforelse
    </ul>

    <div class="mt-6">{{ $notifications->links() }}</div>
@endsection
