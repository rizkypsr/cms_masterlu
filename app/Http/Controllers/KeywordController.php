<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Keyword;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KeywordController extends Controller
{
    public function index()
    {
        $keywords = Keyword::with('category')->get();
        $categories = Category::where('type', 'keyword')->orderBy('seq')->get();

        return Inertia::render('Keyword/Index', [
            'keywords' => $keywords,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'keyword_category_id' => 'required|exists:category,id',
            'kata_kunci' => 'required|string',
        ]);

        Keyword::create($validated);

        return back()->with('success', 'Keyword berhasil ditambahkan');
    }

    public function update(Request $request, Keyword $keyword)
    {
        $validated = $request->validate([
            'keyword_category_id' => 'required|exists:category,id',
            'kata_kunci' => 'required|string',
        ]);

        $keyword->update($validated);

        return back()->with('success', 'Keyword berhasil diupdate');
    }

    public function destroy(Keyword $keyword)
    {
        $keyword->delete();

        return back()->with('success', 'Keyword berhasil dihapus');
    }

    // Category management methods
    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'required|integer|min:1',
        ]);

        Category::create([
            'title' => $validated['title'],
            'seq' => $validated['seq'],
            'type' => 'keyword',
        ]);

        return back()->with('success', 'Kategori berhasil ditambahkan');
    }

    public function updateCategory(Request $request, Category $category)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'required|integer|min:1',
        ]);

        $category->update($validated);

        return back()->with('success', 'Kategori berhasil diupdate');
    }

    public function destroyCategory(Category $category)
    {
        $category->delete();

        return back()->with('success', 'Kategori berhasil dihapus');
    }
}
