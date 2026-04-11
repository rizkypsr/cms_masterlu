<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Unduh;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnduhController extends Controller
{
    public function index()
    {
        $categories = Category::whereNull('parent_id')
            ->where('type', 'unduh')
            ->orderBy('seq')
            ->get();

        foreach ($categories as $category) {
            // Get unduh items for this category
            $category->unduh_items = Unduh::where('unduh_category_id', $category->id)
                ->orderBy('seq')
                ->get();
            
            // Get child categories
            $category->children = Category::where('parent_id', $category->id)
                ->where('type', 'unduh')
                ->orderBy('seq')
                ->get();
            
            // Get unduh items for each child category
            foreach ($category->children as $child) {
                $child->unduh_items = Unduh::where('unduh_category_id', $child->id)
                    ->orderBy('seq')
                    ->get();
            }
        }

        return Inertia::render('Unduh/Index', [
            'categories' => $categories,
        ]);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'required|integer|min:1',
            'parent_id' => 'nullable|exists:category,id',
        ]);

        Category::create([
            'title' => $validated['title'],
            'seq' => $validated['seq'],
            'parent_id' => $validated['parent_id'] ?? null,
            'type' => 'unduh',
        ]);

        return back()->with('success', 'Kategori berhasil ditambahkan');
    }

    public function updateCategory(Request $request, Category $category)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'required|integer|min:1',
            'parent_id' => 'nullable|exists:category,id',
        ]);

        $category->update([
            'title' => $validated['title'],
            'seq' => $validated['seq'],
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        return back()->with('success', 'Kategori berhasil diupdate');
    }

    public function destroyCategory(Category $category)
    {
        $category->delete();

        return back()->with('success', 'Kategori berhasil dihapus');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'unduh_category_id' => 'required|exists:category,id',
            'title' => 'required|string',
            'cover' => 'nullable|file|image|max:10240',
            'url' => 'nullable|string',
            'link_url' => 'nullable|string',
            'seq' => 'required|integer|min:1',
        ]);

        // Handle cover upload
        $coverPath = null;
        if ($request->hasFile('cover')) {
            $file = $request->file('cover');
            $filename = md5(uniqid() . time()) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('assets/upload/unduh'), $filename);
            // Save full URL instead of just filename
            $coverPath = url('assets/upload/unduh/' . $filename);
        }

        // Auto-detect if it's a PDF based on URL or link_url
        $isPdf = false;
        if ($validated['url'] && str_ends_with(strtolower($validated['url']), '.pdf')) {
            $isPdf = true;
        } elseif ($validated['link_url'] && str_ends_with(strtolower($validated['link_url']), '.pdf')) {
            $isPdf = true;
        }

        Unduh::create([
            'unduh_category_id' => $validated['unduh_category_id'],
            'title' => $validated['title'],
            'is_pdf' => $isPdf,
            'cover' => $coverPath,
            'url' => $validated['url'] ?? null,
            'link_url' => $validated['link_url'] ?? null,
            'seq' => $validated['seq'],
        ]);

        return back()->with('success', 'Unduh berhasil ditambahkan');
    }

    public function update(Request $request, Unduh $unduh)
    {
        $validated = $request->validate([
            'unduh_category_id' => 'required|exists:category,id',
            'title' => 'required|string',
            'cover' => 'nullable|file|image|max:10240',
            'url' => 'nullable|string',
            'link_url' => 'nullable|string',
            'seq' => 'required|integer|min:1',
        ]);

        // Handle cover upload
        $coverPath = $unduh->cover;
        if ($request->hasFile('cover')) {
            // Delete old cover if exists
            if ($unduh->cover) {
                // Extract filename from URL
                $oldFilename = basename(parse_url($unduh->cover, PHP_URL_PATH));
                $oldPath = public_path('assets/upload/unduh/' . $oldFilename);
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $file = $request->file('cover');
            $filename = md5(uniqid() . time()) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('assets/upload/unduh'), $filename);
            // Save full URL instead of just filename
            $coverPath = url('assets/upload/unduh/' . $filename);
        }

        // Auto-detect if it's a PDF based on URL or link_url
        $isPdf = false;
        $url = $validated['url'] ?? $unduh->url;
        $linkUrl = $validated['link_url'] ?? $unduh->link_url;
        
        if ($url && str_ends_with(strtolower($url), '.pdf')) {
            $isPdf = true;
        } elseif ($linkUrl && str_ends_with(strtolower($linkUrl), '.pdf')) {
            $isPdf = true;
        }

        $unduh->update([
            'unduh_category_id' => $validated['unduh_category_id'],
            'title' => $validated['title'],
            'is_pdf' => $isPdf,
            'cover' => $coverPath,
            'url' => $validated['url'] ?? null,
            'link_url' => $validated['link_url'] ?? null,
            'seq' => $validated['seq'],
        ]);

        return back()->with('success', 'Unduh berhasil diupdate');
    }

    public function destroy(Unduh $unduh)
    {
        // Delete cover file if exists
        if ($unduh->cover) {
            // Extract filename from URL
            $filename = basename(parse_url($unduh->cover, PHP_URL_PATH));
            $filePath = public_path('assets/upload/unduh/' . $filename);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $unduh->delete();

        return back()->with('success', 'Unduh berhasil dihapus');
    }
}
