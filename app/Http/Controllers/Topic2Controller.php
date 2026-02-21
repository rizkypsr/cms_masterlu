<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Topic2;
use App\Models\Topic2Chapter;
use App\Models\Topic2Content;
use Illuminate\Http\Request;
use Inertia\Inertia;

class Topic2Controller extends Controller
{
    public function index($topic2 = null)
    {
        // If topic2 is a string/int (ID), fetch the model
        if ($topic2 && ! ($topic2 instanceof Topic2)) {
            $topic2 = Topic2::find($topic2);
        }

        $categories = Category::whereNull('parent_id')
            ->where('type', 'topic2')
            ->orderBy('seq')
            ->orderBy('id')
            ->get();

        // Load topics2 for each category
        foreach ($categories as $cat) {
            $cat->topics2 = Topic2::where('book_category_id', $cat->id)
                ->orderBy('seq')
                ->get();
        }

        $chapters = [];
        $selectedTopic2 = null;

        if ($topic2) {
            $topic2->load('bookCategory');
            $selectedTopic2 = $topic2;

            $chapters = Topic2Chapter::where('topics2_id', $topic2->id)
                ->whereNull('parent_id')
                ->with(['children' => function ($query) {
                    $query->with(['children' => function ($q) {
                        $q->orderBy('seq');
                    }])->orderBy('seq');
                }])
                ->orderBy('seq')
                ->get();
        }

        return Inertia::render('Topic2/Index', [
            'categories' => $categories,
            'storeUrl' => '/topic2/category',
            'chapters' => $chapters,
            'selectedTopic2' => $selectedTopic2,
        ]);
    }

    public function showContent(Topic2Chapter $chapter)
    {
        $chapter->load(['topic2', 'parent']);

        $contents = Topic2Content::where('topics2_chapters_id', $chapter->id)
            ->orderBy('page')
            ->get();

        return Inertia::render('Topic2/Content', [
            'chapter' => $chapter,
            'contents' => $contents,
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
            ->where('type', 'topic2')
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
                ->where('type', 'topic2')
                ->where('seq', '>=', $position)
                ->increment('seq');

            $newSeq = $position;
        }

        Category::create([
            'title' => $request->title,
            'parent_id' => $request->parent_id,
            'seq' => $newSeq,
            'type' => 'topic2',
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
                ->where('type', 'topic2')
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

    // Topic2 CRUD (without file uploads)
    public function storeTopic2(Request $request, Category $category)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
        ]);

        $position = $request->seq;

        $topics2 = Topic2::where('book_category_id', $category->id)
            ->orderBy('seq')
            ->get();

        $total = $topics2->count();

        foreach ($topics2 as $index => $topic2) {
            $topic2->update(['seq' => $index + 1]);
        }

        if (! $position || $position > $total) {
            $newSeq = $total + 1;
        } else {
            Topic2::where('book_category_id', $category->id)
                ->where('seq', '>=', $position)
                ->increment('seq');

            $newSeq = $position;
        }

        Topic2::create([
            'title' => $request->title,
            'book_category_id' => $category->id,
            'seq' => $newSeq,
        ]);

        return back();
    }

    public function updateTopic2(Request $request, Topic2 $topic2)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
        ]);

        $data = ['title' => $request->title];

        if ($request->has('seq')) {
            $newPosition = $request->seq;

            $topics2 = Topic2::where('book_category_id', $topic2->book_category_id)
                ->orderBy('seq')
                ->get();

            $currentPosition = $topics2->search(fn ($t) => $t->id === $topic2->id) + 1;

            if ($newPosition !== $currentPosition && $newPosition <= $topics2->count()) {
                $targetTopic2 = $topics2[$newPosition - 1];
                $currentSeq = $topic2->seq;
                $targetSeq = $targetTopic2->seq;

                $topic2->update(['seq' => $targetSeq]);
                $targetTopic2->update(['seq' => $currentSeq]);

                unset($data['seq']);
            } elseif ($newPosition > $topics2->count()) {
                $remainingTopics2 = $topics2->filter(fn ($t) => $t->id !== $topic2->id);
                foreach ($remainingTopics2->values() as $index => $t) {
                    $t->update(['seq' => $index + 1]);
                }

                $data['seq'] = $remainingTopics2->count() + 1;
            }
        }

        $topic2->update($data);

        return back();
    }

    public function destroyTopic2(Topic2 $topic2)
    {
        $topic2->delete();

        return back();
    }

    // Chapter CRUD
    public function storeChapter(Request $request, Topic2 $topic2)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
            'parent_id' => 'nullable|exists:topics2_chapters,id',
            'have_child' => 'required|integer',
        ]);

        $position = $request->seq;
        $parentId = $request->parent_id;

        if ($parentId) {
            $chapters = Topic2Chapter::where('parent_id', $parentId)
                ->orderBy('seq')
                ->get();
        } else {
            $chapters = Topic2Chapter::where('topics2_id', $topic2->id)
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
                Topic2Chapter::where('parent_id', $parentId)
                    ->where('seq', '>=', $position)
                    ->increment('seq');
            } else {
                Topic2Chapter::where('topics2_id', $topic2->id)
                    ->whereNull('parent_id')
                    ->where('seq', '>=', $position)
                    ->increment('seq');
            }

            $newSeq = $position;
        }

        Topic2Chapter::create([
            'title' => $request->title,
            'topics2_id' => $topic2->id,
            'parent_id' => $parentId,
            'seq' => $newSeq,
            'have_child' => $request->have_child,
        ]);

        return back();
    }

    public function updateChapter(Request $request, Topic2Chapter $chapter)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
        ]);

        $data = ['title' => $request->title];

        if ($request->has('seq')) {
            $newPosition = $request->seq;

            if ($chapter->parent_id) {
                $chapters = Topic2Chapter::where('parent_id', $chapter->parent_id)
                    ->orderBy('seq')
                    ->get();
            } else {
                $chapters = Topic2Chapter::where('topics2_id', $chapter->topics2_id)
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

    public function destroyChapter(Topic2Chapter $chapter)
    {
        $chapter->delete();

        return back();
    }

    // Content CRUD
    public function storeContent(Request $request, Topic2Chapter $chapter)
    {
        $request->validate([
            'page' => 'required|integer',
            'content' => 'required|string',
        ]);

        // Check if page number already exists
        $existingContent = Topic2Content::where('topics2_chapters_id', $chapter->id)
            ->where('page', $request->page)
            ->first();

        if ($existingContent) {
            // Find the highest page number
            $maxPage = Topic2Content::where('topics2_chapters_id', $chapter->id)
                ->max('page') ?? 0;

            // Move existing content to next available page
            $existingContent->update(['page' => $maxPage + 1]);
        }

        Topic2Content::create([
            'topics2_chapters_id' => $chapter->id,
            'page' => $request->page,
            'content' => $request->content,
        ]);

        return back();
    }

    public function updateContent(Request $request, Topic2Content $content)
    {
        $request->validate([
            'page' => 'required|integer',
            'content' => 'required|string',
        ]);

        // Check if the new page number is different and already exists
        if ($request->page !== $content->page) {
            $existingContent = Topic2Content::where('topics2_chapters_id', $content->topics2_chapters_id)
                ->where('page', $request->page)
                ->where('id', '!=', $content->id)
                ->first();

            if ($existingContent) {
                // Swap page numbers
                $oldPage = $content->page;
                $newPage = $request->page;

                // Temporarily set to a high number to avoid unique constraint issues
                $tempPage = Topic2Content::where('topics2_chapters_id', $content->topics2_chapters_id)
                    ->max('page') + 1000;

                $content->update(['page' => $tempPage]);
                $existingContent->update(['page' => $oldPage]);
                $content->update(['page' => $newPage]);
            } else {
                $content->update(['page' => $request->page]);
            }
        }

        $content->update(['content' => $request->content]);

        return back();
    }

    public function destroyContent(Topic2Content $content)
    {
        $content->delete();

        return back();
    }
}
