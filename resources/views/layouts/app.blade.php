<!doctype html>
<html lang="hr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>ngCloud</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
            integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <link rel="stylesheet"
            href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.2/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    </head>
    <body class="bg-light d-flex flex-column min-vh-100">
        <header class="bg-dark">
            <nav class="container d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
                <a href="{{ route('home') }}"
                    class="d-flex align-items-center gap-2 text-white text-decoration-none fs-5">
                    <i class="bi bi-cloud text-info" aria-hidden="true"></i>
                    ngCloud
                </a>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    @auth
                        @if (auth()->user()->hasRole('user'))
                            <a href="{{ route('documents.index') }}" class="app-nav-link">Moje
                                datoteke</a>
                            <a href="{{ route('profile.edit') }}" class="app-nav-link">Moj
                                profil</a>
                        @endif
                        @if (auth()->user()->hasRole('super_admin'))
                            <a href="{{ route('admin.documents.index') }}" class="app-nav-link">Sve
                                datoteke</a>
                            <a href="{{ route('admin.users.index') }}" class="app-nav-link">Korisnici</a>
                        @endif
                        <span class="text-white">
                            <i class="bi bi-person-fill" aria-hidden="true"></i>
                            {{ auth()->user()->username }}
                        </span>
                        <form action="{{ route('logout') }}" method="post">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-light">Logout</button>
                        </form>
                    @endauth
                    @guest
                        <a href="{{ route('login') }}" class="btn btn-sm btn-outline-light">Login</a>
                    @endguest
                </div>
            </nav>
        </header>
        <main class="container bg-white py-4 flex-grow-1">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
        <footer class="container py-3 text-secondary">
            <p>©2026</p>
        </footer>
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
            integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
            crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"
            integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y"
            crossorigin="anonymous"></script>
    </body>
</html>
