@extends('layouts.app')

@section('title', 'Quelle anlegen – '.config('app.name'))

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight">Quelle anlegen</h1>
        <p class="mt-1 text-sm text-stone-600">Name, Typ und absoluter Ordnerpfad.</p>
    </div>

    <form
        method="POST"
        action="{{ route('sources.store') }}"
        class="max-w-xl space-y-4 border border-stone-300 bg-white p-4"
    >
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium">Name</label>
            <input
                id="name"
                name="name"
                type="text"
                value="{{ old('name') }}"
                required
                class="mt-1 w-full border border-stone-400 px-3 py-2 text-sm"
            >
            @error('name')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="type" class="block text-sm font-medium">Typ</label>
            <select
                id="type"
                name="type"
                required
                class="mt-1 w-full border border-stone-400 px-3 py-2 text-sm"
            >
                <option value="markdown_vault" @selected(old('type', 'markdown_vault') === 'markdown_vault')>
                    markdown_vault
                </option>
            </select>
            @error('type')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="path" class="block text-sm font-medium">Pfad (absolut)</label>
            <input
                id="path"
                name="path"
                type="text"
                value="{{ old('path') }}"
                required
                placeholder="/vault/PHP und Laravel"
                class="mt-1 w-full border border-stone-400 px-3 py-2 font-mono text-sm"
            >
            @error('path')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-3 pt-2">
            <button
                type="submit"
                class="bg-stone-900 px-3 py-2 text-sm font-medium text-white hover:bg-stone-700"
            >
                Speichern
            </button>
            <a href="{{ route('sources.index') }}" class="px-3 py-2 text-sm text-stone-700 hover:underline">
                Abbrechen
            </a>
        </div>
    </form>
@endsection
