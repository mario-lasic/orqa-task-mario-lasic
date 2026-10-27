@extends('layouts.app')

@section('content')
    <h1>Moj profil</h1>
    @include('partials.storage-space', ['user' => $user])
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <form action="{{ route('profile.update') }}" method="post">
        @csrf
        @method('PATCH')
        <div class="mb-3">
            <label for="name" class="form-label">Ime</label>
            <input type="text" name="name"  id="name" class="form-control" required maxlength="255" value="{{ old('name', $user->name) }}">
        </div>
        <div class="mb-3">
            <label for="last_name" class="form-label">Prezime</label>
            <input type="text" name="last_name"  id="last_name" class="form-control" required maxlength="255" value="{{ old('last_name', $user->last_name) }}">
        </div>
        <div class="mb-3">
            <label for="address" class="form-label">Adresa</label>
            <input type="text" name="address"  id="address" class="form-control" required maxlength="255" value="{{ old('address', $user->address) }}">
        </div>
        <button type="submit" class="btn btn-primary">Spremi promjene</button>
    </form>
@endsection
