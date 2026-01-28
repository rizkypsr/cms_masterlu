<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AudioCategoryController extends Controller
{
    public function daftarIsi()
    {
        $categories = Category::whereNull('parent_id')
            ->where('type', 'audio')
            ->where('languange', 'CH')
            ->with(['children' => function ($query) {
                $query->where('languange', 'CH')->orderBy('seq')->orderBy('id');
            }])
            ->orderBy('seq')
            ->orderBy('id')
            ->get();

        return Inertia::render('Audio/Index', [
            'categories' => $categories,
            'lang' => 'CH',
            'storeUrl' => '/audio/daftar-isi/category',
        ]);
    }

    public function topik()
    {
        $categories = Category::whereNull('parent_id')
            ->where('type', 'audio')
            ->where('languange', 'ID')
            ->with(['children' => function ($query) {
                $query->where('languange', 'ID')->orderBy('seq')->orderBy('id');
            }])
            ->orderBy('seq')
            ->orderBy('id')
            ->get();

        return Inertia::render('Audio/Index', [
            'categories' => $categories,
            'lang' => 'ID',
            'storeUrl' => '/audio/topik/category',
        ]);
    }

    public function storeDaftarIsi(Request $request)
    {
        return $this->store($request, 'CH');
    }

    public function storeTopik(Request $request)
    {
        return $this->store($request, 'ID');
    }

    private function store(Request $request, string $lang)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:category,id',
            'seq' => 'nullable|integer',
        ]);

        $position = $request->seq;
        
        $categories = Category::where('parent_id', $request->parent_id)
            ->where('type', 'audio')
            ->where('languange', $lang)
            ->orderBy('seq')
            ->orderBy('id')
            ->get();
        
        $totalCategories = $categories->count();
        
        foreach ($categories as $index => $cat) {
            $cat->update(['seq' => $index + 1]);
        }
        
        if (!$position || $position > $totalCategories) {
            $newSeq = $totalCategories + 1;
        } else {
            Category::where('parent_id', $request->parent_id)
                ->where('type', 'audio')
                ->where('languange', $lang)
                ->where('seq', '>=', $position)
                ->increment('seq');
            
            $newSeq = $position;
        }

        Category::create([
            'title' => $request->title,
            'parent_id' => $request->parent_id,
            'seq' => $newSeq,
            'type' => 'audio',
            'languange' => $lang,
        ]);

        return back();
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
        ]);

        $data = ['title' => $request->title];
        $lang = $category->languange;
        
        if ($request->has('seq')) {
            $newPosition = $request->seq;
            
            $categories = Category::where('parent_id', $category->parent_id)
                ->where('type', 'audio')
                ->where('languange', $lang)
                ->orderBy('seq')
                ->orderBy('id')
                ->get();
            
            $currentPosition = $categories->search(fn($c) => $c->id === $category->id) + 1;
            
            if ($newPosition !== $currentPosition) {
                foreach ($categories as $index => $cat) {
                    if ($cat->id !== $category->id) {
                        $cat->update(['seq' => $index + 1]);
                    }
                }
                
                $categories = Category::where('parent_id', $category->parent_id)
                    ->where('type', 'audio')
                    ->where('languange', $lang)
                    ->where('id', '!=', $category->id)
                    ->orderBy('seq')
                    ->orderBy('id')
                    ->get();
                
                $totalCategories = $categories->count();
                
                if ($newPosition > $totalCategories + 1) {
                    $newPosition = $totalCategories + 1;
                }
                
                Category::where('parent_id', $category->parent_id)
                    ->where('type', 'audio')
                    ->where('languange', $lang)
                    ->where('id', '!=', $category->id)
                    ->where('seq', '>=', $newPosition)
                    ->increment('seq');
                
                $data['seq'] = $newPosition;
            }
        }

        $category->update($data);

        return back();
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return back();
    }
}
