<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Topic3;
use App\Models\Topic3Chapter;
use App\Models\Topic3Content;
use App\Models\Topic3ContentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $validated = $request->only(['title', 'seq']);

        if (isset($validated['seq'])) {
            $targetPosition = $validated['seq'];

            DB::transaction(function () use ($targetPosition, $category) {
                $allItems = Category::where('parent_id', $category->parent_id)
                    ->where('type', 'topic3')
                    ->orderBy('seq')
                    ->lockForUpdate()
                    ->get();

                $currentIndex = $allItems->search(fn ($item) => $item->id == $category->id);
                if ($currentIndex === false) {
                    return;
                }

                $currentPosition = $currentIndex + 1;
                $totalCount = $allItems->count();

                if ($targetPosition > $totalCount) {
                    $targetPosition = $totalCount;
                }

                if ($targetPosition === $currentPosition) {
                    return;
                }

                $movingItem = $allItems[$currentIndex];

                if ($targetPosition > $currentPosition) {
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    Category::where('parent_id', $category->parent_id)
                        ->where('type', 'topic3')
                        ->whereBetween('seq', [$movingItem->seq + 1, $targetSeq])
                        ->decrement('seq');

                    $movingItem->seq = $targetSeq;
                } else {
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    Category::where('parent_id', $category->parent_id)
                        ->where('type', 'topic3')
                        ->whereBetween('seq', [$targetSeq, $movingItem->seq - 1])
                        ->increment('seq');

                    $movingItem->seq = $targetSeq;
                }

                $movingItem->save();
            });

            unset($validated['seq']);
        }

        $category->fill($validated);
        $category->save();

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

        $validated = $request->only(['title', 'seq']);

        if (isset($validated['seq'])) {
            $targetPosition = $validated['seq'];

            DB::transaction(function () use ($targetPosition, $topic3) {
                $allItems = Topic3::where('book_category_id', $topic3->book_category_id)
                    ->orderBy('seq')
                    ->lockForUpdate()
                    ->get();

                $currentIndex = $allItems->search(fn ($item) => $item->id == $topic3->id);
                if ($currentIndex === false) {
                    return;
                }

                $currentPosition = $currentIndex + 1;
                $totalCount = $allItems->count();

                if ($targetPosition > $totalCount) {
                    $targetPosition = $totalCount;
                }

                if ($targetPosition === $currentPosition) {
                    return;
                }

                $movingItem = $allItems[$currentIndex];

                if ($targetPosition > $currentPosition) {
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    Topic3::where('book_category_id', $topic3->book_category_id)
                        ->whereBetween('seq', [$movingItem->seq + 1, $targetSeq])
                        ->decrement('seq');

                    $movingItem->seq = $targetSeq;
                } else {
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    Topic3::where('book_category_id', $topic3->book_category_id)
                        ->whereBetween('seq', [$targetSeq, $movingItem->seq - 1])
                        ->increment('seq');

                    $movingItem->seq = $targetSeq;
                }

                $movingItem->save();
            });

            unset($validated['seq']);
        }

        $topic3->fill($validated);
        $topic3->save();

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

        $validated = $request->only(['title', 'seq']);

        if (isset($validated['seq'])) {
            $targetPosition = $validated['seq'];

            DB::transaction(function () use ($targetPosition, $chapter) {
                if ($chapter->parent_id) {
                    $allItems = Topic3Chapter::where('parent_id', $chapter->parent_id)
                        ->orderBy('seq')
                        ->lockForUpdate()
                        ->get();
                } else {
                    $allItems = Topic3Chapter::where('topics3_id', $chapter->topics3_id)
                        ->whereNull('parent_id')
                        ->orderBy('seq')
                        ->lockForUpdate()
                        ->get();
                }

                $currentIndex = $allItems->search(fn ($item) => $item->id == $chapter->id);
                if ($currentIndex === false) {
                    return;
                }

                $currentPosition = $currentIndex + 1;
                $totalCount = $allItems->count();

                if ($targetPosition > $totalCount) {
                    $targetPosition = $totalCount;
                }

                if ($targetPosition === $currentPosition) {
                    return;
                }

                $movingItem = $allItems[$currentIndex];

                if ($targetPosition > $currentPosition) {
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    if ($chapter->parent_id) {
                        Topic3Chapter::where('parent_id', $chapter->parent_id)
                            ->whereBetween('seq', [$movingItem->seq + 1, $targetSeq])
                            ->decrement('seq');
                    } else {
                        Topic3Chapter::where('topics3_id', $chapter->topics3_id)
                            ->whereNull('parent_id')
                            ->whereBetween('seq', [$movingItem->seq + 1, $targetSeq])
                            ->decrement('seq');
                    }

                    $movingItem->seq = $targetSeq;
                } else {
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    if ($chapter->parent_id) {
                        Topic3Chapter::where('parent_id', $chapter->parent_id)
                            ->whereBetween('seq', [$targetSeq, $movingItem->seq - 1])
                            ->increment('seq');
                    } else {
                        Topic3Chapter::where('topics3_id', $chapter->topics3_id)
                            ->whereNull('parent_id')
                            ->whereBetween('seq', [$targetSeq, $movingItem->seq - 1])
                            ->increment('seq');
                    }

                    $movingItem->seq = $targetSeq;
                }

                $movingItem->save();
            });

            unset($validated['seq']);
        }

        $chapter->fill($validated);
        $chapter->save();

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

    // Video selection for chapter
    public function videoDetail(Request $request, Topic3Chapter $chapter)
    {
        $chapter->load(['topic3' => function ($query) {
            $query->with('bookCategory');
        }]);

        $videos = \App\Models\Topic3Video::where('topics3_chapters_id', $chapter->id)
            ->orderBy('seq')
            ->get();

        $items = [];
        foreach ($videos as $video) {
            $videoCategoryData = \App\Models\VideoCategory::find($video->video_category_id);
            if ($videoCategoryData) {
                $items[] = [
                    'id' => $video->id,
                    'type' => 'video',
                    'title' => $videoCategoryData->title,
                    'seq' => $video->seq,
                    'video_category' => $videoCategoryData,
                    'video_category_id' => $videoCategoryData->id,
                ];
            }
        }

        // Get all available video categories where parent_id is not null
        $availableItems = \App\Models\VideoCategory::whereNotNull('parent_id')
            ->orderBy('parent_id')
            ->orderBy('seq')
            ->get()
            ->map(function ($videoCategory) {
                return [
                    'id' => $videoCategory->id,
                    'type' => 'video',
                    'title' => $videoCategory->title,
                    'parent_id' => $videoCategory->parent_id,
                ];
            });

        return Inertia::render('Topic3/VideoDetail', [
            'chapter' => $chapter,
            'items' => $items,
            'availableItems' => $availableItems,
        ]);
    }

    public function storeVideo(Request $request, Topic3Chapter $chapter)
    {
        $request->validate([
            'video_category_id' => 'required|exists:video_category,id',
            'seq' => 'nullable|integer',
        ]);

        $position = $request->seq;

        $videos = \App\Models\Topic3Video::where('topics3_chapters_id', $chapter->id)
            ->orderBy('seq')
            ->get();

        $total = $videos->count();

        foreach ($videos as $index => $video) {
            $video->update(['seq' => $index + 1]);
        }

        if (! $position || $position > $total) {
            $newSeq = $total + 1;
        } else {
            \App\Models\Topic3Video::where('topics3_chapters_id', $chapter->id)
                ->where('seq', '>=', $position)
                ->increment('seq');

            $newSeq = $position;
        }

        \App\Models\Topic3Video::create([
            'topics3_chapters_id' => $chapter->id,
            'video_category_id' => $request->video_category_id,
            'seq' => $newSeq,
        ]);

        return back();
    }

    public function updateVideo(Request $request, $id)
    {
        $topic3Video = \App\Models\Topic3Video::findOrFail($id);

        $request->validate([
            'seq' => 'nullable|integer',
        ]);

        if (isset($request->seq)) {
            $targetPosition = $request->seq;

            DB::transaction(function () use ($topic3Video, $targetPosition) {
                $allItems = \App\Models\Topic3Video::where('topics3_chapters_id', $topic3Video->topics3_chapters_id)
                    ->orderBy('seq')
                    ->lockForUpdate()
                    ->get();

                $currentIndex = $allItems->search(fn ($v) => $v->id === $topic3Video->id);
                if ($currentIndex === false) {
                    return;
                }

                $currentPosition = $currentIndex + 1;
                $totalCount = $allItems->count();

                if ($targetPosition > $totalCount) {
                    $targetPosition = $totalCount;
                }

                if ($targetPosition === $currentPosition) {
                    return;
                }

                $movingItem = $allItems[$currentIndex];

                if ($targetPosition > $currentPosition) {
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    \App\Models\Topic3Video::where('topics3_chapters_id', $topic3Video->topics3_chapters_id)
                        ->whereBetween('seq', [$movingItem->seq + 1, $targetSeq])
                        ->decrement('seq');

                    $movingItem->seq = $targetSeq;
                } else {
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    \App\Models\Topic3Video::where('topics3_chapters_id', $topic3Video->topics3_chapters_id)
                        ->whereBetween('seq', [$targetSeq, $movingItem->seq - 1])
                        ->increment('seq');

                    $movingItem->seq = $targetSeq;
                }

                $movingItem->save();
            });
        }

        return back();
    }

    public function destroyVideo($id)
    {
        $topic3Video = \App\Models\Topic3Video::findOrFail($id);
        $topic3Video->delete();

        return back();
    }
}
