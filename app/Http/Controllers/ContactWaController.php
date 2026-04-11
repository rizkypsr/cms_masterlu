<?php

namespace App\Http\Controllers;

use App\Models\ContactWa;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContactWaController extends Controller
{
    public function index()
    {
        $contacts = ContactWa::where('status', 1)
            ->orderBy('id', 'asc')
            ->get();

        return Inertia::render('ContactWa/Index', [
            'contacts' => $contacts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'no_wa' => 'required|string|max:100',
        ]);

        ContactWa::create([
            'nama' => $validated['nama'],
            'no_wa' => $validated['no_wa'],
            'status' => 1,
        ]);

        return back()->with('success', 'Kontak WA berhasil ditambahkan');
    }

    public function update(Request $request, ContactWa $contactWa)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'no_wa' => 'required|string|max:100',
        ]);

        $contactWa->update([
            'nama' => $validated['nama'],
            'no_wa' => $validated['no_wa'],
        ]);

        return back()->with('success', 'Kontak WA berhasil diupdate');
    }

    public function destroy(ContactWa $contactWa)
    {
        // Soft delete by setting status to 0
        $contactWa->update([
            'status' => 0,
        ]);

        return back()->with('success', 'Kontak WA berhasil dihapus');
    }
}
