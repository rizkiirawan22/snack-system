<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return response()->json(User::latest()->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:6',
            'role'      => 'required|in:admin,pegawai',
            'is_active' => 'boolean',
        ]);

        $data['password'] = bcrypt($data['password']);
        $user = User::create($data);

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => "required|email|unique:users,email,{$user->id}",
            'password'  => 'nullable|string|min:6',
            'role'      => 'required|in:admin,pegawai',
            'is_active' => 'boolean',
        ]);

        if (empty($data['password'])) unset($data['password']);
        else $data['password'] = bcrypt($data['password']);

        $user->update($data);

        return response()->json($user);
    }

    public function destroy(User $user)
    {
        if ($user->id === request()->user()->id) {
            return response()->json(['message' => 'Tidak bisa hapus akun sendiri.'], 422);
        }

        $user->delete();
        return response()->json(['message' => 'Pengguna dihapus.']);
    }
}
