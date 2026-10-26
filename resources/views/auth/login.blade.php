@extends('layouts/app')
@section('content')
    <h1>Prijava</h1>
    <form method="post" action="{{ route('login') }}">
        @csrf
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" name="username" id="username" required value="{{ old('username') }}"
                class="form-control">
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" name="password" id="password" required class="form-control">
        </div>
        <button type="submit" class="btn btn-primary">Prijavi se</button>
    </form>
@endsection
