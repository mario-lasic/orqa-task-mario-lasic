<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'user');
            })
            ->withSum('documents', 'size_bytes')
            ->orderBy('username')
            ->paginate(5);

        return view('admin.users.index', [
            'users' => $users,
        ]);
    }
}
