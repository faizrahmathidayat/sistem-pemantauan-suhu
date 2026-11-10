@extends('layouts.app')

@section('title', 'Log Perubahan')

@section('content')
    <x-page-header title="Log Perubahan" description="Siapa mengubah apa, kapan, beserta nilai sebelum dan sesudahnya." />

    @if ($logs->isEmpty())
        <x-card>
            <x-empty title="Belum ada perubahan tercatat." icon="log" />
        </x-card>
    @else
        {{-- Layar lebar (≥ lg, di sebelah sidebar): tabel --}}
        <x-card flush class="hidden lg:block">
            <x-table>
                <thead class="border-b border-gray-100">
                    <tr>
                        <th class="table-th">Waktu</th>
                        <th class="table-th">Pengguna</th>
                        <th class="table-th">Perubahan</th>
                        <th class="table-th">Rincian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($logs as $log)
                        <tr class="align-top">
                            <td class="table-td whitespace-nowrap">
                                <time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</time>
                            </td>
                            <td class="table-td font-medium text-gray-800">{{ $log->user_name }}</td>
                            <td class="table-td">
                                <x-badge variant="brand">{{ $log->eventLabel() }}</x-badge>
                                <span class="mt-1 block text-gray-700">{{ $log->subject_label }}</span>
                            </td>
                            <td class="table-td">
                                <ul class="space-y-1">
                                    @foreach ($log->changeLines() as $line)
                                        <li class="flex flex-wrap items-baseline gap-x-2">
                                            <span>{{ $line['label'] }}:</span>
                                            <span class="text-gray-400 line-through">{{ $line['from'] }}</span>
                                            <span aria-hidden="true">→</span>
                                            <span class="font-medium text-gray-800">{{ $line['to'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>

        {{-- HP dan layar sedang: daftar kartu --}}
        <ul data-mobile-list class="grid gap-3 md:grid-cols-2 lg:hidden">
            @foreach ($logs as $log)
                <li class="rounded-2xl border border-gray-200 bg-white p-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                        <p class="text-base font-semibold text-gray-800">{{ $log->user_name }}</p>
                        <time datetime="{{ $log->created_at->toIso8601String() }}" class="text-xs text-gray-500">
                            {{ $log->created_at->timezone(config('app.timezone'))->format('d M Y H:i') }}
                        </time>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <x-badge variant="brand">{{ $log->eventLabel() }}</x-badge>
                        <span class="text-sm text-gray-700">{{ $log->subject_label }}</span>
                    </div>
                    <ul class="mt-3 space-y-1 text-sm text-gray-500">
                        @foreach ($log->changeLines() as $line)
                            <li class="flex flex-wrap items-baseline gap-x-2">
                                <span>{{ $line['label'] }}:</span>
                                <span class="text-gray-400 line-through">{{ $line['from'] }}</span>
                                <span aria-hidden="true">→</span>
                                <span class="font-medium text-gray-800">{{ $line['to'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $logs->links() }}</div>
    @endif
@endsection
