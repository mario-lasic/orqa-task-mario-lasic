@extends('layouts/app')
@section('content')
    <h1>Sve datoteke</h1>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="table-responsive w-50">
        <table class="table table-striped">
            <tr>
                <th>Naziv</th>
                <th>Veličina (B)</th>
                <th>Stvoreno</th>
                <th>Added By (Vlasnik)</th>
                <th>Radnje</th>
            </tr>
            @forelse($documents as $document)
                <tr>
                    <td>{{ $document->original_name }}</td>
                    <td>{{ $document->size_bytes }}</td>
                    <td>{{ $document->created_at }}</td>
                    <td>{{ $document->user->username }}</td>
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
                    <td colspan="5">Još nema datoteka.</td>
                </tr>
            @endforelse
        </table>
        {{ $documents->links() }}
    </div>
@endsection
