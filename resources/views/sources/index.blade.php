@extends('layouts.app')

@section('title', 'Quellen – '.config('app.name'))

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Quellen</h1>
            <p class="mt-1 text-sm text-stone-600">Ordner als Wissensquellen verwalten.</p>
        </div>
        <a
            href="{{ route('sources.create') }}"
            class="inline-flex items-center bg-stone-900 px-3 py-2 text-sm font-medium text-white hover:bg-stone-700"
        >
            Quelle anlegen
        </a>
    </div>

    @if ($sources->isEmpty())
        <p class="border border-dashed border-stone-400 bg-white px-4 py-8 text-center text-sm text-stone-600">
            Noch keine Quellen. Lege einen Vault-Ordner an oder starte den Dev-Seed.
        </p>
    @else
        <div class="overflow-x-auto border border-stone-300 bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-stone-300 bg-stone-50 text-stone-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Name</th>
                        <th class="px-3 py-2 font-medium">Typ</th>
                        <th class="px-3 py-2 font-medium">Pfad</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 font-medium">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sources as $source)
                        <tr class="border-b border-stone-200 last:border-0">
                            <td class="px-3 py-2 font-medium">{{ $source->name }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $source->type->value }}</td>
                            <td class="px-3 py-2 font-mono text-xs break-all">{{ $source->path }}</td>
                            <td class="px-3 py-2">
                                @if ($source->enabled)
                                    <span class="text-emerald-700">aktiv</span>
                                @else
                                    <span class="text-stone-500">inaktiv</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('sources.toggle', $source) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="border border-stone-400 px-2 py-1 text-xs hover:bg-stone-100"
                                        >
                                            {{ $source->enabled ? 'Deaktivieren' : 'Aktivieren' }}
                                        </button>
                                    </form>
                                    <form
                                        method="POST"
                                        action="{{ route('sources.destroy', $source) }}"
                                        onsubmit="return confirm('Quelle wirklich löschen?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="border border-red-400 px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                        >
                                            Löschen
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
