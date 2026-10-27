@extends('layouts/app')
@section('content')
    <h1>Moje datoteke</h1>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <form action="{{ route('documents.store') }}" method="post" enctype="multipart/form-data" class="mb-4">
        @csrf
        <div class="mb-3">
            <label for="document" class="form-label">Odaberi datoteku</label>
            <input type="file" name="document" id="document" required
                class="form-control" aria-describedby="document-help">
            <div id="document-help" class="form-text">
                Najveća veličina pojedinačne datoteke je 2 MiB.
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Učitaj datoteku</button>
    </form>
    <div class="table-responsive">
        <table class="table table-striped">
            <tr>
                <th>Naziv</th>
                <th>Veličina (B)</th>
                <th>Stvoreno</th>
                <th>Radnje</th>
            </tr>
            @forelse($documents as $document)
                <tr>
                    <td class="text-break">
                        @include('partials.document-icon', ['document' => $document])
                        {{ $document->original_name }}
                    </td>
                    <td>{{ $document->size_bytes }}</td>
                    <td>{{ $document->created_at }}</td>
                    <td>
                        @can('download', $document)
                            <a href="{{ route('documents.download', $document) }}"
                                class="btn btn-sm btn-outline-primary">
                                Preuzmi
                            </a>
                        @endcan
                        @can('delete', $document)
                            <form action="{{ route('documents.destroy', $document) }}"
                                method="POST"
                                class="d-inline">
                                @csrf
                                @method('DELETE')

                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    Obriši
                                </button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Još nema datoteka.</td>
                </tr>
            @endforelse
        </table>
        {{ $documents->links() }}
    </div>
    @include('partials.storage-space', ['user' => auth()->user()])
@endsection
