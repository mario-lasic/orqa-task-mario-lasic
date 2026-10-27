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
                class="form-control">
        </div>
        <button type="submit" class="btn btn-primary">Učitaj datoteku</button>
    </form>
    <div class="table-responsive w-50">
        <table class="table table-striped">
            <tr>
                <th>Naziv</th>
                <th>Veličina (B)</th>
                <th>Stvoreno</th>
                <th>Radnje</th>
            </tr>
            @forelse($documents as $document)
                <tr>
                    <td>{{ $document->original_name }}</td>
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
@endsection
