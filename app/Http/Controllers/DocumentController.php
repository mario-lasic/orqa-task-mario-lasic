<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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
}
