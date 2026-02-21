<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Topic3;
use App\Models\Topic3Chapter;
use App\Models\Topic3Content;
use App\Models\Topic3ContentCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class Topic3Controller extends Controller
{
    public function index($topic3 = null)
    {
        // If topic3 is a string/int (ID), fetch the model
        if ($topic3 && ! ($topic3 instanceof Topic3)) {
            $topic3 = Topic3::find($topic3);
        }

        $categories = Category::whereNull('parent_id')
            ->where('type', 'topic3')
            ->orderBy('seq')
            ->orderBy('id')
            ->get();

        // Load topics3 for each category
        foreach ($categories as $cat) {
            $cat->topics3 = Topic3::where('book_category_id', $cat->id)
                ->orderBy('seq')
                ->get();
        }

        $chapters = [];
        $selectedTopic3 = null;

        if ($topic3) {
            $topic3->load('bookCategory');
            $selectedTopic3 = $topic3;

            $chapters = Topic3Chapter::where('topics3_id', $topic3->id)
                ->whereNull('parent_id')
                ->with(['children' => function ($query) {
                    $query->with(['children' => function ($q) {
                        $q->orderBy('seq');
                    }])->orderBy('seq');
                }])
                ->orderBy('seq')
                ->get();
        }

        return Inertia::render('Topic3/Index', [
            'categories' => $categories,
            'storeUrl' => '/topic3/category',
            'chapters' => $chapters,
            'selectedTopic3' => $selectedTopic3,
        ]);
    }

    public function showContent(Topic3Chapter $chapter)
    {
        $chapter->load(['topic3', 'parent']);

        $contents = Topic3Content::where('topics3_chapters_id', $chapter->id)
            ->with('category')
            ->orderBy('page')
            ->get();

        // Get all content categories for dropdown
        $contentCategories = Topic3ContentCategory::orderBy('seq')->orderBy('name')->get();

        return Inertia::render('Topic3/Content', [
            'chapter' => $chapter,
            'contents' => $contents,
            'contentCategories' => $contentCategories,
        ]);
    }

    // Category CRUD
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:category,id',
            'seq' => 'nullable|integer',
        ]);

        $position = $request->seq;

        $categories = Category::where('parent_id', $request->parent_id)
            ->where('type', 'topic3')
            ->orderBy('seq')
            ->orderBy('id')
            ->get();

        $totalCategories = $categories->count();

        foreach ($categories as $index => $cat) {
            $cat->update(['seq' => $index + 1]);
        }

        if (! $position || $position > $totalCategories) {
            $newSeq = $totalCategories + 1;
        } else {
            Category::where('parent_id', $request->parent_id)
                ->where('type', 'topic3')
                ->where('seq', '>=', $position)
                ->increment('seq');

            $newSeq = $position;
        }

        Category::create([
            'title' => $request->title,
            'parent_id' => $request->parent_id,
            'seq' => $newSeq,
            'type' => 'topic3',
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

        if ($request->has('seq')) {
            $newPosition = $request->seq;

            $categories = Category::where('parent_id', $category->parent_id)
                ->where('type', 'topic3')
                ->orderBy('seq')
                ->orderBy('id')
                ->get();

            $currentPosition = $categories->search(fn ($c) => $c->id === $category->id) + 1;

            if ($newPosition !== $currentPosition && $newPosition <= $categories->count()) {
                $targetCategory = $categories[$newPosition - 1];
                $currentSeq = $category->seq;
                $targetSeq = $targetCategory->seq;

                $category->update(['seq' => $targetSeq]);
                $targetCategory->update(['seq' => $currentSeq]);

                unset($data['seq']);
            } elseif ($newPosition > $categories->count()) {
                $remainingCategories = $categories->filter(fn ($c) => $c->id !== $category->id);
                foreach ($remainingCategories->values() as $index => $cat) {
                    $cat->update(['seq' => $index + 1]);
                }

                $data['seq'] = $remainingCategories->count() + 1;
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

    // Topic3 CRUD (without file uploads)
    public function storeTopic3(Request $request, Category $category)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
        ]);

        $position = $request->seq;

        $topics3 = Topic3::where('book_category_id', $category->id)
            ->orderBy('seq')
            ->get();

        $total = $topics3->count();

        foreach ($topics3 as $index => $topic3) {
            $topic3->update(['seq' => $index + 1]);
        }

        if (! $position || $position > $total) {
            $newSeq = $total + 1;
        } else {
            Topic3::where('book_category_id', $category->id)
                ->where('seq', '>=', $position)
                ->increment('seq');

            $newSeq = $position;
        }

        Topic3::create([
            'title' => $request->title,
            'book_category_id' => $category->id,
            'seq' => $newSeq,
        ]);

        return back();
    }

    public function updateTopic3(Request $request, Topic3 $topic3)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
        ]);

        $data = ['title' => $request->title];

        if ($request->has('seq')) {
            $newPosition = $request->seq;

            $topics3 = Topic3::where('book_category_id', $topic3->book_category_id)
                ->orderBy('seq')
                ->get();

            $currentPosition = $topics3->search(fn ($t) => $t->id === $topic3->id) + 1;

            if ($newPosition !== $currentPosition && $newPosition <= $topics3->count()) {
                $targetTopic3 = $topics3[$newPosition - 1];
                $currentSeq = $topic3->seq;
                $targetSeq = $targetTopic3->seq;

                $topic3->update(['seq' => $targetSeq]);
                $targetTopic3->update(['seq' => $currentSeq]);

                unset($data['seq']);
            } elseif ($newPosition > $topics3->count()) {
                $remainingTopics3 = $topics3->filter(fn ($t) => $t->id !== $topic3->id);
                foreach ($remainingTopics3->values() as $index => $t) {
                    $t->update(['seq' => $index + 1]);
                }

                $data['seq'] = $remainingTopics3->count() + 1;
            }
        }

        $topic3->update($data);

        return back();
    }

    public function destroyTopic3(Topic3 $topic3)
    {
        $topic3->delete();

        return back();
    }

    // Chapter CRUD
    public function storeChapter(Request $request, Topic3 $topic3)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
            'parent_id' => 'nullable|exists:topics3_chapters,id',
            'have_child' => 'required|integer',
        ]);

        $position = $request->seq;
        $parentId = $request->parent_id;

        if ($parentId) {
            $chapters = Topic3Chapter::where('parent_id', $parentId)
                ->orderBy('seq')
                ->get();
        } else {
            $chapters = Topic3Chapter::where('topics3_id', $topic3->id)
                ->whereNull('parent_id')
                ->orderBy('seq')
                ->get();
        }

        $total = $chapters->count();

        foreach ($chapters as $index => $chapter) {
            $chapter->update(['seq' => $index + 1]);
        }

        if (! $position || $position > $total) {
            $newSeq = $total + 1;
        } else {
            if ($parentId) {
                Topic3Chapter::where('parent_id', $parentId)
                    ->where('seq', '>=', $position)
                    ->increment('seq');
            } else {
                Topic3Chapter::where('topics3_id', $topic3->id)
                    ->whereNull('parent_id')
                    ->where('seq', '>=', $position)
                    ->increment('seq');
            }

            $newSeq = $position;
        }

        Topic3Chapter::create([
            'title' => $request->title,
            'topics3_id' => $topic3->id,
            'parent_id' => $parentId,
            'seq' => $newSeq,
            'have_child' => $request->have_child,
        ]);

        return back();
    }

    public function updateChapter(Request $request, Topic3Chapter $chapter)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
        ]);

        $data = ['title' => $request->title];

        if ($request->has('seq')) {
            $newPosition = $request->seq;

            if ($chapter->parent_id) {
                $chapters = Topic3Chapter::where('parent_id', $chapter->parent_id)
                    ->orderBy('seq')
                    ->get();
            } else {
                $chapters = Topic3Chapter::where('topics3_id', $chapter->topics3_id)
                    ->whereNull('parent_id')
                    ->orderBy('seq')
                    ->get();
            }

            $currentPosition = $chapters->search(fn ($c) => $c->id === $chapter->id) + 1;

            if ($newPosition !== $currentPosition && $newPosition <= $chapters->count()) {
                $targetChapter = $chapters[$newPosition - 1];
                $currentSeq = $chapter->seq;
                $targetSeq = $targetChapter->seq;

                $chapter->update(['seq' => $targetSeq]);
                $targetChapter->update(['seq' => $currentSeq]);

                unset($data['seq']);
            } elseif ($newPosition > $chapters->count()) {
                $remainingChapters = $chapters->filter(fn ($c) => $c->id !== $chapter->id);
                foreach ($remainingChapters->values() as $index => $c) {
                    $c->update(['seq' => $index + 1]);
                }

                $data['seq'] = $remainingChapters->count() + 1;
            }
        }

        $chapter->update($data);

        return back();
    }

    public function destroyChapter(Topic3Chapter $chapter)
    {
        $chapter->delete();

        return back();
    }

    // Content CRUD (with category support)
    public function storeContent(Request $request, Topic3Chapter $chapter)
    {
        $request->validate([
            'page' => 'required|integer',
            'content' => 'required|string',
            'category_id' => 'nullable|exists:topics3_content_category,id',
            'new_category_name' => 'nullable|string|max:255',
        ]);

        $categoryId = $request->category_id;

        // If new category name is provided, create it
        if ($request->new_category_name) {
            $maxSeq = Topic3ContentCategory::max('seq') ?? 0;
            $newCategory = Topic3ContentCategory::create([
                'name' => $request->new_category_name,
                'seq' => $maxSeq + 1,
            ]);
            $categoryId = $newCategory->id;
        }

        // Check if page number already exists
        $existingContent = Topic3Content::where('topics3_chapters_id', $chapter->id)
            ->where('page', $request->page)
            ->first();

        if ($existingContent) {
            // Find the highest page number
            $maxPage = Topic3Content::where('topics3_chapters_id', $chapter->id)
                ->max('page') ?? 0;

            // Move existing content to next available page
            $existingContent->update(['page' => $maxPage + 1]);
        }

        Topic3Content::create([
            'topics3_chapters_id' => $chapter->id,
            'category_id' => $categoryId,
            'page' => $request->page,
            'content' => $request->content,
        ]);

        return back();
    }

    public function updateContent(Request $request, Topic3Content $content)
    {
        $request->validate([
            'page' => 'required|integer',
            'content' => 'required|string',
            'category_id' => 'nullable|exists:topics3_content_category,id',
            'new_category_name' => 'nullable|string|max:255',
        ]);

        $categoryId = $request->category_id;

        // If new category name is provided, create it
        if ($request->new_category_name) {
            $maxSeq = Topic3ContentCategory::max('seq') ?? 0;
            $newCategory = Topic3ContentCategory::create([
                'name' => $request->new_category_name,
                'seq' => $maxSeq + 1,
            ]);
            $categoryId = $newCategory->id;
        }

        // Check if the new page number is different and already exists
        if ($request->page !== $content->page) {
            $existingContent = Topic3Content::where('topics3_chapters_id', $content->topics3_chapters_id)
                ->where('page', $request->page)
                ->where('id', '!=', $content->id)
                ->first();

            if ($existingContent) {
                // Swap page numbers
                $oldPage = $content->page;
                $newPage = $request->page;

                // Temporarily set to a high number to avoid unique constraint issues
                $tempPage = Topic3Content::where('topics3_chapters_id', $content->topics3_chapters_id)
                    ->max('page') + 1000;

                $content->update(['page' => $tempPage]);
                $existingContent->update(['page' => $oldPage]);
                $content->update(['page' => $newPage]);
            } else {
                $content->update(['page' => $request->page]);
            }
        }

        $content->update([
            'content' => $request->content,
            'category_id' => $categoryId,
        ]);

        return back();
    }

    public function destroyContent(Topic3Content $content)
    {
        $content->delete();

        return back();
    }

    // Content Category CRUD
    public function storeContentCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'seq' => 'nullable|integer',
        ]);

        $maxSeq = Topic3ContentCategory::max('seq') ?? 0;

        Topic3ContentCategory::create([
            'name' => $request->name,
            'description' => $request->description,
            'seq' => $request->seq ?? ($maxSeq + 1),
        ]);

        return back();
    }

    public function updateContentCategory(Request $request, Topic3ContentCategory $category)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'seq' => 'nullable|integer',
        ]);

        $category->update($request->only(['name', 'description', 'seq']));

        return back();
    }

    public function destroyContentCategory(Topic3ContentCategory $category)
    {
        // Check if category is being used
        $usageCount = Topic3Content::where('category_id', $category->id)->count();

        if ($usageCount > 0) {
            return back()->withErrors(['error' => "Cannot delete category. It is being used by {$usageCount} content(s)."]);
        }

        $category->delete();

        return back();
    }

    // Bulk assign category to multiple contents
    public function bulkAssignCategory(Request $request)
    {
        $request->validate([
            'content_ids' => 'required|array',
            'content_ids.*' => 'exists:topics3_contents,id',
            'category_id' => 'nullable|exists:topics3_content_category,id',
            'new_category_name' => 'nullable|string|max:255',
        ]);

        $categoryId = $request->category_id;

        // If new category name is provided, create it
        if ($request->new_category_name) {
            $maxSeq = Topic3ContentCategory::max('seq') ?? 0;
            $newCategory = Topic3ContentCategory::create([
                'name' => $request->new_category_name,
                'seq' => $maxSeq + 1,
            ]);
            $categoryId = $newCategory->id;
        }

        // Update all selected contents
        Topic3Content::whereIn('id', $request->content_ids)
            ->whereNull('category_id') // Only update contents without category
            ->update(['category_id' => $categoryId]);

        return back()->with('success', 'Kategori berhasil ditambahkan ke konten terpilih');
    }
}
