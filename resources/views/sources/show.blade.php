@extends('layouts.app')

@section('title', $source->name.' – '.config('app.name'))

@php
    $statusStyles = [
        'indexed' => 'text-emerald-700',
        'pending' => 'text-amber-700',
        'indexing' => 'text-amber-700',
        'failed' => 'text-red-700',
    ];
@endphp

@section('content')
    <div class="mb-6">
        <a href="{{ route('sources.index') }}" class="text-sm text-stone-600 hover:text-stone-950">&larr; Quellen</a>
        <div class="mt-2 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ $source->name }}</h1>
                <p class="mt-1 font-mono text-xs text-stone-600 break-all">{{ $source->path }}</p>
            </div>
            <span class="text-sm {{ $source->enabled ? 'text-emerald-700' : 'text-stone-500' }}">
                {{ $source->enabled ? 'aktiv' : 'inaktiv' }}
            </span>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap gap-4 border border-stone-300 bg-white px-4 py-3 text-sm">
        <span class="font-medium">{{ $documents->count() }} Dokument(e)</span>
        @foreach (['indexed', 'pending', 'indexing', 'failed'] as $status)
            @if ($statusCounts->get($status, 0) > 0)
                <span class="{{ $statusStyles[$status] }}">
                    {{ $statusCounts->get($status) }} {{ $status }}
                </span>
            @endif
        @endforeach
    </div>

    @if ($documents->isEmpty())
        <p class="border border-dashed border-stone-400 bg-white px-4 py-8 text-center text-sm text-stone-600">
            Noch keine Dokumente. Läuft {{ $source->enabled ? 'ein `corpus:sync`' : 'diese Quelle noch nicht aktiv' }}?
        </p>
    @else
        <div class="overflow-x-auto border border-stone-300 bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-stone-300 bg-stone-50 text-stone-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Pfad</th>
                        <th class="px-3 py-2 font-medium">Titel</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 font-medium">Chunks</th>
                        <th class="px-3 py-2 font-medium">Indexiert am</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($documents as $document)
                        <tr class="border-b border-stone-200 last:border-0 align-top">
                            <td class="px-3 py-2 font-mono text-xs break-all">{{ $document->path }}</td>
                            <td class="px-3 py-2">{{ $document->title }}</td>
                            <td class="px-3 py-2">
                                <span class="{{ $statusStyles[$document->sync_status->value] }}">
                                    {{ $document->sync_status->value }}
                                </span>
                                @if ($document->sync_status->value === 'failed' && $document->last_error)
                                    <p class="mt-1 text-xs text-red-700">{{ $document->last_error }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $document->chunk_count ?? '–' }}</td>
                            <td class="px-3 py-2 text-xs text-stone-600">
                                {{ $document->indexed_at?->format('Y-m-d H:i') ?? '–' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
