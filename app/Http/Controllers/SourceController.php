<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreSourceRequest;
use App\Models\Document;
use App\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SourceController extends Controller
{
    public function index(): View
    {
        $sources = Source::query()
            ->orderBy('name')
            ->get();

        return view('sources.index', [
            'sources' => $sources,
        ]);
    }

    public function show(Source $source): View
    {
        $documents = $source->documents()
            ->orderBy('path')
            ->get();

        /** @var Collection<string, int> $statusCounts */
        $statusCounts = $documents->countBy(
            fn (Document $document): string => $document->sync_status->value,
        );

        return view('sources.show', [
            'source' => $source,
            'documents' => $documents,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function create(): View
    {
        return view('sources.create');
    }

    public function store(StoreSourceRequest $request): RedirectResponse
    {
        Source::query()->create([
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
            'path' => $request->canonicalPath(),
            'enabled' => true,
        ]);

        return redirect()
            ->route('sources.index')
            ->with('status', 'Quelle angelegt.');
    }

    public function toggle(Source $source): RedirectResponse
    {
        $source->update([
            'enabled' => ! $source->enabled,
        ]);

        $message = $source->enabled
            ? 'Quelle aktiviert.'
            : 'Quelle deaktiviert.';

        return redirect()
            ->route('sources.index')
            ->with('status', $message);
    }

    public function destroy(Source $source): RedirectResponse
    {
        $source->delete();

        return redirect()
            ->route('sources.index')
            ->with('status', 'Quelle gelöscht.');
    }
}
