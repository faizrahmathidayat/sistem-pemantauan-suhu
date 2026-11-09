@extends('layouts.app')

@section('title', 'Log Perubahan')

@section('content')
    <h1 class="text-lg font-semibold">Log Perubahan</h1>
    <p class="text-sm text-slate-500">Siapa mengubah apa, kapan, beserta nilai sebelum dan sesudahnya.</p>

    @if ($logs->isEmpty())
        <p class="mt-4 rounded-xl bg-white p-4 text-slate-500 shadow-sm">Belum ada perubahan tercatat.</p>
    @else
        <ul class="mt-3 space-y-2">
            @foreach ($logs as $log)
                <li class="rounded-xl bg-white p-3 shadow-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                        <p class="font-semibold">{{ $log->user_name }}
                            <span class="font-normal text-slate-500">· {{ $log->eventLabel() }}</span>
                        </p>
                        <time datetime="{{ $log->created_at->toIso8601String() }}" class="text-xs text-slate-500">
                            {{ $log->created_at->timezone(config('app.timezone'))->format('d M Y H:i') }}
                        </time>
                    </div>
                    <p class="text-sm text-slate-600">{{ $log->subject_label }}</p>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($log->changeLines() as $line)
                            <li class="flex flex-wrap items-baseline gap-x-2">
                                <span class="text-slate-500">{{ $line['label'] }}:</span>
                                <span class="text-slate-500 line-through">{{ $line['from'] }}</span>
                                <span aria-hidden="true">→</span>
                                <span class="font-medium">{{ $line['to'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $logs->links() }}</div>
    @endif
@endsection
