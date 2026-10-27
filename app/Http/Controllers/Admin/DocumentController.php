<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(): View
    {
        $documents = Document::query()
            ->with('user')
            ->orderByDesc('id')
            ->paginate(5);

        return view('admin.documents.index', [
            'documents' => $documents,
        ]);
    }
}
