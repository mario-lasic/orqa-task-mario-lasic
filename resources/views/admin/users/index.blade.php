@extends('layouts/app')
@section('content')
    <h1>Korisnici</h1>
    <div class="table-responsive w-50">
        <table class="table table-striped">
            <tr>
                <th>Korisničko ime</th>
                <th>Ime</th>
                <th>Prezime</th>
                <th>Prostor</th>
            </tr>
            @forelse($users as $user)
                <tr>
                    <td>{{ $user->username }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->last_name }}</td>
                    <td>@include('partials.storage-space', ['user' => $user])</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Nema korisnika s ulogom user.</td>
                </tr>
            @endforelse
        </table>
        {{ $users->links() }}
    </div>
@endsection
