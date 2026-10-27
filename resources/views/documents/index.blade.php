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
            </tr>
            @forelse($documents as $document)
                <tr>
                    <td>{{ $document->original_name }}</td>
                    <td>{{ $document->size_bytes }}</td>
                    <td>{{ $document->created_at }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">Još nema datoteka.</td>
                </tr>
            @endforelse
        </table>
        {{ $documents->links() }}
    </div>
@endsection
