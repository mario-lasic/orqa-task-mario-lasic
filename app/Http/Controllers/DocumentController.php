<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $documents = $request->user()->documents()->orderByDesc('id')->paginate(5);

        return view('documents.index', ['documents' => $documents]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'document' => 'required|file|max:2048',
        ]);
        $file = $request->file('document');
        $sizeBytes = $file->getSize();
        $originalName = $file->getClientOriginalName();
        if (mb_strlen($originalName) > 255) {
            throw ValidationException::withMessages([
                'document' => 'Naziv datoteke smije sadržavati najviše 255 znakova.',
            ]);
        }
        $extension = strtolower($file->getClientOriginalExtension());
        $storedPath = null;

        try {
            DB::transaction(function () use ($request, $file, $sizeBytes, $originalName, $extension, &$storedPath) {
                $user = User::query()
                    ->whereKey($request->user()->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($sizeBytes > $user->remainingStorageBytes()) {
                    throw ValidationException::withMessages([
                        'document' => 'Nema dovoljno slobodnog prostora za ovu datoteku.',
                    ]);
                }

                $storedPath = $file->store("documents/{$user->id}", 'local');
                if (! $storedPath) {
                    throw new \RuntimeException('Spremanje datoteke nije uspjelo.');
                }

                $user->documents()->create([
                    'original_name' => $originalName,
                    'path' => $storedPath,
                    'size_bytes' => $sizeBytes,
                    'extension' => $extension !== '' ? $extension : null,
                ]);
            });
        } catch (\Throwable $exception) {
            if (is_string($storedPath) && $storedPath !== '') {
                try {
                    if (! Storage::disk('local')->delete($storedPath)) {
                        report(
                            new \RuntimeException('Čišćenje datoteke nakon pogreške nije uspjelo.')
                        );
                    }
                } catch (\Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }

        return redirect()->route('documents.index')
            ->with('success', 'Datoteka je uspješno učitana.');
    }

    public function download(Document $document): StreamedResponse
    {
        Gate::authorize('download', $document);
        if (! Storage::disk('local')->exists($document->path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $document->path,
            $document->original_name
        );
    }

    public function destroy(Document $document): RedirectResponse
    {
        Gate::authorize('delete', $document);
        DB::transaction(function () use ($document) {
            User::query()
                ->whereKey($document->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedDocument = Document::query()
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('delete', $lockedDocument);

            if (! $lockedDocument->delete()) {
                throw new \RuntimeException('Brisanje zapisa dokumenta nije uspjelo.');
            }
            if (! Storage::disk('local')->delete($lockedDocument->path)) {
                throw ValidationException::withMessages(['document' => 'Brisanje datoteke nije uspjelo. Pokušajte ponovno.']);
            }
        });

        return back()->with('success', 'Dokument je uspješno obrisan.');
    }
}
