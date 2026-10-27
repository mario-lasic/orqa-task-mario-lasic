<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $documents = Document::query()
            ->with('user')
            ->orderByDesc('id')
            ->paginate(5);

        if ($documents->currentPage() > $documents->lastPage()) {
            session()->keep('success');

            return redirect()->route('admin.documents.index', [
                'page' => $documents->lastPage(),
            ]);
        }

        return view('admin.documents.index', [
            'documents' => $documents,
        ]);
    }
}
