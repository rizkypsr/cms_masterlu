<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Unduh;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $targetPosition = $validated['seq'];
        $parentId = $validated['parent_id'] ?? null;

        DB::transaction(function () use ($validated, $targetPosition, $parentId) {
            // Load all categories in the same level ordered by seq
            $query = Category::where('type', 'unduh');
            
            if ($parentId) {
                $query->where('parent_id', $parentId);
            } else {
                $query->whereNull('parent_id');
            }
            
            $allItems = $query->orderBy('seq')->lockForUpdate()->get();
            $totalCount = $allItems->count();

            // If inserting at a position other than last, shift items
            if ($targetPosition <= $totalCount) {
                $targetSeq = $allItems[$targetPosition - 1]->seq;
                
                // Shift items down (increment seq)
                $query = Category::where('type', 'unduh');
                if ($parentId) {
                    $query->where('parent_id', $parentId);
                } else {
                    $query->whereNull('parent_id');
                }
                $query->where('seq', '>=', $targetSeq)->increment('seq');
                
                $newSeq = $targetSeq;
            } else {
                // Inserting at last position
                $newSeq = $totalCount > 0 ? $allItems->last()->seq + 1 : 1;
            }

            Category::create([
                'title' => $validated['title'],
                'seq' => $newSeq,
                'parent_id' => $parentId,
                'type' => 'unduh',
            ]);
        });

        return back()->with('success', 'Kategori berhasil ditambahkan');
    }

    public function updateCategory(Request $request, Category $category)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'required|integer|min:1',
            'parent_id' => 'nullable|exists:category,id',
        ]);

        // Handle reordering with shift-based algorithm
        if (isset($validated['seq'])) {
            $targetPosition = $validated['seq'];

            DB::transaction(function () use ($targetPosition, $category) {
                // Load all categories in the same level ordered by seq
                $query = Category::where('type', 'unduh');
                
                if ($category->parent_id) {
                    $query->where('parent_id', $category->parent_id);
                } else {
                    $query->whereNull('parent_id');
                }
                
                $allItems = $query->orderBy('seq')->lockForUpdate()->get();

                // Find current position
                $currentIndex = $allItems->search(fn ($item) => $item->id == $category->id);
                if ($currentIndex === false) {
                    return;
                }

                $currentPosition = $currentIndex + 1;
                $totalCount = $allItems->count();

                // Validate target position
                if ($targetPosition > $totalCount) {
                    $targetPosition = $totalCount;
                }

                // No-op if position unchanged
                if ($targetPosition === $currentPosition) {
                    return;
                }

                $movingItem = $allItems[$currentIndex];

                if ($targetPosition > $currentPosition) {
                    // Moving DOWN
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    // Shift items up (decrement seq)
                    $query = Category::where('type', 'unduh');
                    if ($category->parent_id) {
                        $query->where('parent_id', $category->parent_id);
                    } else {
                        $query->whereNull('parent_id');
                    }
                    $query->whereBetween('seq', [$movingItem->seq + 1, $targetSeq])->decrement('seq');

                    $movingItem->seq = $targetSeq;
                } else {
                    // Moving UP
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    // Shift items down (increment seq)
                    $query = Category::where('type', 'unduh');
                    if ($category->parent_id) {
                        $query->where('parent_id', $category->parent_id);
                    } else {
                        $query->whereNull('parent_id');
                    }
                    $query->whereBetween('seq', [$targetSeq, $movingItem->seq - 1])->increment('seq');

                    $movingItem->seq = $targetSeq;
                }

                $movingItem->save();
            });

            unset($validated['seq']);
        }

        $category->update([
            'title' => $validated['title'],
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

        $targetPosition = $validated['seq'];
        $categoryId = $validated['unduh_category_id'];

        DB::transaction(function () use ($validated, $targetPosition, $categoryId, $coverPath, $isPdf) {
            // Load all items in the same category ordered by seq
            $allItems = Unduh::where('unduh_category_id', $categoryId)
                ->orderBy('seq')
                ->lockForUpdate()
                ->get();
            
            $totalCount = $allItems->count();

            // If inserting at a position other than last, shift items
            if ($targetPosition <= $totalCount) {
                $targetSeq = $allItems[$targetPosition - 1]->seq;
                
                // Shift items down (increment seq)
                Unduh::where('unduh_category_id', $categoryId)
                    ->where('seq', '>=', $targetSeq)
                    ->increment('seq');
                
                $newSeq = $targetSeq;
            } else {
                // Inserting at last position
                $newSeq = $totalCount > 0 ? $allItems->last()->seq + 1 : 1;
            }

            Unduh::create([
                'unduh_category_id' => $categoryId,
                'title' => $validated['title'],
                'is_pdf' => $isPdf,
                'cover' => $coverPath,
                'url' => $validated['url'] ?? null,
                'link_url' => $validated['link_url'] ?? null,
                'seq' => $newSeq,
            ]);
        });

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

        // Handle reordering with shift-based algorithm
        if (isset($validated['seq'])) {
            $targetPosition = $validated['seq']; // Visual position from frontend

            DB::transaction(function () use ($targetPosition, $unduh) {
                // Step 1: Load all items in the same category ordered by seq
                $allItems = Unduh::where('unduh_category_id', $unduh->unduh_category_id)
                    ->orderBy('seq')
                    ->lockForUpdate()
                    ->get();

                // Step 2: Find current position
                $currentIndex = $allItems->search(fn ($item) => $item->id == $unduh->id);
                if ($currentIndex === false) {
                    return;
                }

                $currentPosition = $currentIndex + 1;
                $totalCount = $allItems->count();

                // Step 3: Validate target position
                if ($targetPosition > $totalCount) {
                    $targetPosition = $totalCount;
                }

                // Step 4: No-op if position unchanged
                if ($targetPosition === $currentPosition) {
                    return;
                }

                $movingItem = $allItems[$currentIndex];

                if ($targetPosition > $currentPosition) {
                    // Moving DOWN
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    // Shift items up (decrement seq)
                    Unduh::where('unduh_category_id', $unduh->unduh_category_id)
                        ->whereBetween('seq', [$movingItem->seq + 1, $targetSeq])
                        ->decrement('seq');

                    $movingItem->seq = $targetSeq;
                } else {
                    // Moving UP
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    // Shift items down (increment seq)
                    Unduh::where('unduh_category_id', $unduh->unduh_category_id)
                        ->whereBetween('seq', [$targetSeq, $movingItem->seq - 1])
                        ->increment('seq');

                    $movingItem->seq = $targetSeq;
                }

                $movingItem->save();
            });

            unset($validated['seq']);
        }

        $unduh->update([
            'unduh_category_id' => $validated['unduh_category_id'],
            'title' => $validated['title'],
            'is_pdf' => $isPdf,
            'cover' => $coverPath,
            'url' => $validated['url'] ?? null,
            'link_url' => $validated['link_url'] ?? null,
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
