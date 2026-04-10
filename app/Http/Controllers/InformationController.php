<?php

namespace App\Http\Controllers;

use App\Models\Information;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InformationController extends Controller
{
    public function index()
    {
        $information = Information::all();

        return Inertia::render('Information/Index', [
            'information' => $information,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string',
            'type' => 'required|string|unique:information,type',
        ]);

        Information::create($validated);

        return back()->with('success', 'Information berhasil ditambahkan');
    }

    public function update(Request $request, Information $information)
    {
        $validated = $request->validate([
            'description' => 'required|string',
            'type' => 'required|string|unique:information,type,' . $information->id,
        ]);

        $information->update($validated);

        return back()->with('success', 'Information berhasil diupdate');
    }

    public function destroy(Information $information)
    {
        $information->delete();

        return back()->with('success', 'Information berhasil dihapus');
    }
}
