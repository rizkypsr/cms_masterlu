<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class PenggunaController extends Controller
{
    public function index()
    {
        $penggunas = User::orderBy('created_at', 'desc')->get();

        return Inertia::render('Pengguna/Index', [
            'penggunas' => $penggunas,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:6',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Pengguna berhasil ditambahkan');
    }

    public function update(Request $request, User $pengguna)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $pengguna->id,
            'username' => 'required|string|max:255|unique:users,username,' . $pengguna->id,
            'password' => 'nullable|string|min:6',
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => $validated['username'],
        ];

        // Only update password if provided
        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $pengguna->update($data);

        return back()->with('success', 'Pengguna berhasil diupdate');
    }

    public function destroy(User $pengguna)
    {
        $pengguna->delete();

        return back()->with('success', 'Pengguna berhasil dihapus');
    }
}
