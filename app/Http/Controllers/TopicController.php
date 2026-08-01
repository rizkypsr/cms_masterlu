<?php

namespace App\Http\Controllers;

use App\Models\AudioSubtitle;
use App\Models\Category;
use App\Models\Topic;
use App\Models\TopicCategory;
use App\Models\TopicContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TopicController extends Controller
{
    public function index(Request $request)
    {
        // Topic uses first category (or you can hardcode a specific category)
        $category = Category::first();

        if (! $category) {
            abort(404);
        }

        $topics = Topic::where('category_id', $category->id)
            ->orderBy('seq')
            ->get();

        $selectedTopicId = $request->query('topic_id');
        $selectedTopic = null;
        $topicCategories = [];

        if ($selectedTopicId) {
            $selectedTopic = Topic::find($selectedTopicId);
            if ($selectedTopic) {
                $topicCategories = TopicCategory::where('topics_id', $selectedTopicId)
                    ->whereNull('parent_id')
                    ->with(['children' => function ($query) {
                        $query->orderBy('seq');
                    }])
                    ->orderBy('seq')
                    ->get();
            }
        }

        return Inertia::render('Topic/Index', [
            'category' => $category,
            'topics' => $topics,
            'selectedTopic' => $selectedTopic,
            'topicCategories' => $topicCategories,
        ]);
    }

    public function store(Request $request)
    {
        $category = Category::first();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer|min:1',
        ]);

        $validated['category_id'] = $category->id;

        DB::transaction(function () use ($validated, $category) {
            if (! isset($validated['seq'])) {
                // No position specified, add to end
                $maxSeq = Topic::where('category_id', $category->id)->max('seq') ?? 0;
                $validated['seq'] = $maxSeq + 1;
            } else {
                // Position specified, use shift-based insertion
                $newPosition = $validated['seq'];

                // Get total count
                $totalCount = Topic::where('category_id', $category->id)->count();

                // Validate and adjust position
                if ($newPosition > $totalCount + 1) {
                    $newPosition = $totalCount + 1;
                    $validated['seq'] = $newPosition;
                }

                // Shift existing items to make room for new item
                Topic::where('category_id', $category->id)
                    ->where('seq', '>=', $newPosition)
                    ->increment('seq');
            }

            Topic::create($validated);
        });

        return redirect()->back();
    }

    public function update(Request $request, $id)
    {
        $topic = Topic::findOrFail($id);
        $category = Category::first();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer|min:1',
        ]);

        if (isset($validated['seq'])) {
            $targetPosition = $validated['seq']; // This is the visual position (1,2,3,4) from frontend

            DB::transaction(function () use ($targetPosition, $category, $id) {
                // Get all items ordered by seq to determine positions
                $allItems = Topic::where('category_id', $category->id)
                    ->orderBy('seq')
                    ->lockForUpdate()
                    ->get();

                // Find current position of the item being moved
                $currentIndex = $allItems->search(fn ($item) => $item->id == $id);
                if ($currentIndex === false) {
                    return;
                }

                $currentPosition = $currentIndex + 1; // Convert to 1-indexed position
                $totalCount = $allItems->count();

                // Validate and adjust target position
                if ($targetPosition > $totalCount) {
                    $targetPosition = $totalCount;
                }

                // No-op if position unchanged
                if ($targetPosition === $currentPosition) {
                    return;
                }

                // Get the item being moved
                $movingItem = $allItems[$currentIndex];

                if ($targetPosition > $currentPosition) {
                    // Moving DOWN: Get seq of item at target position
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    // Shift items up (decrement) in the range
                    Topic::where('category_id', $category->id)
                        ->whereBetween('seq', [$movingItem->seq + 1, $targetSeq])
                        ->decrement('seq');

                    // Move item to target seq
                    $movingItem->seq = $targetSeq;
                } else {
                    // Moving UP: Get seq of item at target position
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    // Shift items down (increment) in the range
                    Topic::where('category_id', $category->id)
                        ->whereBetween('seq', [$targetSeq, $movingItem->seq - 1])
                        ->increment('seq');

                    // Move item to target seq
                    $movingItem->seq = $targetSeq;
                }

                $movingItem->save();
            });

            // Remove seq from validated to prevent double update
            unset($validated['seq']);
        }

        $topic->fill($validated);
        $topic->save();

        return redirect()->back();
    }

    public function destroy($id)
    {
        $topic = Topic::findOrFail($id);
        $category = Category::first();

        // Get all topic categories for this topic
        $topicCategories = TopicCategory::where('topics_id', $topic->id)->get();

        foreach ($topicCategories as $topicCategory) {
            // Delete all contents for this topic category
            TopicContent::where('topics_category_id', $topicCategory->id)->delete();
        }

        // Delete all topic categories for this topic
        TopicCategory::where('topics_id', $topic->id)->delete();

        Topic::where('category_id', $category->id)
            ->where('seq', '>', $topic->seq)
            ->decrement('seq');

        $topic->delete();

        return redirect()->back();
    }

    public function storeCategory(Request $request, $topicId)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer|min:1',
            'have_child' => 'required|integer',
            'parent_id' => 'nullable|integer',
        ]);

        $validated['topics_id'] = $topicId;

        // Determine if this is a child or parent category
        $isChild = isset($validated['parent_id']) && $validated['parent_id'] !== null;

        DB::transaction(function () use ($validated, $topicId, $isChild) {
            if (! isset($validated['seq'])) {
                // No position specified, add to end
                if ($isChild) {
                    $maxSeq = TopicCategory::where('topics_id', $topicId)
                        ->where('parent_id', $validated['parent_id'])
                        ->max('seq') ?? 0;
                } else {
                    $maxSeq = TopicCategory::where('topics_id', $topicId)
                        ->whereNull('parent_id')
                        ->max('seq') ?? 0;
                }
                $validated['seq'] = $maxSeq + 1;
            } else {
                // Position specified, use shift-based insertion
                $newPosition = $validated['seq'];

                // Get total count
                if ($isChild) {
                    $totalCount = TopicCategory::where('topics_id', $topicId)
                        ->where('parent_id', $validated['parent_id'])
                        ->count();
                } else {
                    $totalCount = TopicCategory::where('topics_id', $topicId)
                        ->whereNull('parent_id')
                        ->count();
                }

                // Validate and adjust position
                if ($newPosition > $totalCount + 1) {
                    $newPosition = $totalCount + 1;
                    $validated['seq'] = $newPosition;
                }

                // Shift existing items to make room for new item
                if ($isChild) {
                    TopicCategory::where('topics_id', $topicId)
                        ->where('parent_id', $validated['parent_id'])
                        ->where('seq', '>=', $newPosition)
                        ->increment('seq');
                } else {
                    TopicCategory::where('topics_id', $topicId)
                        ->whereNull('parent_id')
                        ->where('seq', '>=', $newPosition)
                        ->increment('seq');
                }
            }

            TopicCategory::create($validated);
        });

        return redirect()->back();
    }

    public function updateCategory(Request $request, $id)
    {
        $category = TopicCategory::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer|min:1',
        ]);

        if (isset($validated['seq'])) {
            $targetPosition = $validated['seq']; // This is the visual position (1,2,3,4) from frontend

            // Determine if this is a child or parent category
            $isChild = $category->parent_id !== null;

            DB::transaction(function () use ($category, $targetPosition, $isChild, $id) {
                // Build base query with row-level locking
                if ($isChild) {
                    $allItems = TopicCategory::where('topics_id', $category->topics_id)
                        ->where('parent_id', $category->parent_id)
                        ->orderBy('seq')
                        ->lockForUpdate()
                        ->get();
                } else {
                    $allItems = TopicCategory::where('topics_id', $category->topics_id)
                        ->whereNull('parent_id')
                        ->orderBy('seq')
                        ->lockForUpdate()
                        ->get();
                }

                // Find current position of the item being moved
                $currentIndex = $allItems->search(fn ($item) => $item->id == $id);
                if ($currentIndex === false) {
                    return;
                }

                $currentPosition = $currentIndex + 1; // Convert to 1-indexed position
                $totalCount = $allItems->count();

                // Validate and adjust target position
                if ($targetPosition > $totalCount) {
                    $targetPosition = $totalCount;
                }

                // No-op if position unchanged
                if ($targetPosition === $currentPosition) {
                    return;
                }

                // Get the item being moved
                $movingItem = $allItems[$currentIndex];

                if ($targetPosition > $currentPosition) {
                    // Moving DOWN: Get seq of item at target position
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    // Shift items up (decrement) in the range
                    if ($isChild) {
                        TopicCategory::where('topics_id', $category->topics_id)
                            ->where('parent_id', $category->parent_id)
                            ->whereBetween('seq', [$movingItem->seq + 1, $targetSeq])
                            ->decrement('seq');
                    } else {
                        TopicCategory::where('topics_id', $category->topics_id)
                            ->whereNull('parent_id')
                            ->whereBetween('seq', [$movingItem->seq + 1, $targetSeq])
                            ->decrement('seq');
                    }

                    // Move item to target seq
                    $movingItem->seq = $targetSeq;
                } else {
                    // Moving UP: Get seq of item at target position
                    $targetSeq = $allItems[$targetPosition - 1]->seq;

                    // Shift items down (increment) in the range
                    if ($isChild) {
                        TopicCategory::where('topics_id', $category->topics_id)
                            ->where('parent_id', $category->parent_id)
                            ->whereBetween('seq', [$targetSeq, $movingItem->seq - 1])
                            ->increment('seq');
                    } else {
                        TopicCategory::where('topics_id', $category->topics_id)
                            ->whereNull('parent_id')
                            ->whereBetween('seq', [$targetSeq, $movingItem->seq - 1])
                            ->increment('seq');
                    }

                    // Move item to target seq
                    $movingItem->seq = $targetSeq;
                }

                $movingItem->save();
            });

            // Remove seq from validated to prevent double update
            unset($validated['seq']);
        }

        // Update other fields (title, etc.)
        $category->fill($validated);
        $category->save();

        return redirect()->back();
    }

    public function destroyCategory($id)
    {
        $category = TopicCategory::findOrFail($id);

        // Delete all contents for this topic category
        TopicContent::where('topics_category_id', $category->id)->delete();

        // Determine if this is a child or parent category
        $isChild = $category->parent_id !== null;

        if ($isChild) {
            TopicCategory::where('topics_id', $category->topics_id)
                ->where('parent_id', $category->parent_id)
                ->where('seq', '>', $category->seq)
                ->decrement('seq');
        } else {
            TopicCategory::where('topics_id', $category->topics_id)
                ->whereNull('parent_id')
                ->where('seq', '>', $category->seq)
                ->decrement('seq');
        }

        $category->delete();

        return redirect()->back();
    }

    public function storeContent(Request $request, $categoryId)
    {
        $validated = $request->validate([
            'id_header' => 'required|integer',
            'seq' => 'nullable|integer|min:1',
        ]);

        $validated['topics_category_id'] = $categoryId;
        $validated['type'] = 'audio'; // Always audio

        DB::transaction(function () use ($validated, $categoryId) {
            // The form numbers positions 1..N from the rendered list, so seq
            // has to actually be 1..N for that number to mean the same thing
            // here. Existing rows can start at 2 or have gaps, which is what
            // made "last" land second-to-last.
            $total = $this->resequenceContents($categoryId);

            $position = $validated['seq'] ?? ($total + 1);
            $position = max(1, min((int) $position, $total + 1));

            TopicContent::where('topics_category_id', $categoryId)
                ->where('seq', '>=', $position)
                ->increment('seq');

            $validated['seq'] = $position;

            TopicContent::create($validated);
        });

        return redirect()->back();
    }

    /**
     * Renumber a category's contents to a gap-free 1..N in their current order.
     *
     * Position and seq are treated as the same number throughout ordering, so
     * this keeps that true — and repairs rows that drifted apart previously.
     *
     * @return int the number of rows
     */
    private function resequenceContents($categoryId): int
    {
        $rows = TopicContent::where('topics_category_id', $categoryId)
            ->orderBy('seq')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($rows as $index => $row) {
            $expected = $index + 1;

            if ((int) $row->seq !== $expected) {
                $row->update(['seq' => $expected]);
            }
        }

        return $rows->count();
    }

    public function updateContent(Request $request, $id)
    {
        $content = TopicContent::findOrFail($id);

        $validated = $request->validate([
            'seq' => 'nullable|integer|min:1',
        ]);

        if (isset($validated['seq'])) {
            $requested = (int) $validated['seq'];

            DB::transaction(function () use ($content, $requested, $id) {
                // Same reason as storeContent: the form's position only lines
                // up with seq once seq is a gap-free 1..N.
                $totalCount = $this->resequenceContents($content->topics_category_id);

                // Re-read: renumbering may have moved this row.
                $content->refresh();
                $oldPosition = (int) $content->seq;
                $newPosition = max(1, min($requested, $totalCount));

                // No-op if position unchanged
                if ($newPosition === $oldPosition) {
                    return;
                }

                // Lock the item being moved
                TopicContent::where('id', $id)->lockForUpdate()->first();

                if ($newPosition > $oldPosition) {
                    // Moving DOWN: Shift items up (decrement) in the range
                    TopicContent::where('topics_category_id', $content->topics_category_id)
                        ->whereBetween('seq', [$oldPosition + 1, $newPosition])
                        ->decrement('seq');
                } else {
                    // Moving UP: Shift items down (increment) in the range
                    TopicContent::where('topics_category_id', $content->topics_category_id)
                        ->whereBetween('seq', [$newPosition, $oldPosition - 1])
                        ->increment('seq');
                }

                // Update the moved item to new position
                $content->seq = $newPosition;
                $content->save();
            });
        } else {
            $content->save();
        }

        return redirect()->back();
    }

    public function destroyContent($id)
    {
        $content = TopicContent::findOrFail($id);

        TopicContent::where('topics_category_id', $content->topics_category_id)
            ->where('seq', '>', $content->seq)
            ->decrement('seq');

        $content->delete();

        return redirect()->back();
    }

    public function bulkDeleteContent(Request $request)
    {
        $request->validate([
            'content_ids' => 'required|array',
            'content_ids.*' => 'exists:topics_content,id',
        ]);

        TopicContent::whereIn('id', $request->content_ids)->delete();

        return back();
    }

    public function detail(Request $request, $categoryId)
    {
        $category = Category::first();
        $topicCategory = TopicCategory::with('topic')->findOrFail($categoryId);

        $contents = TopicContent::where('topics_category_id', $categoryId)
            ->orderBy('seq')
            ->get();

        // Fetched in one go rather than per row — this used to issue a query
        // for every content item on the page.
        $subtitles = AudioSubtitle::with('audio')
            ->whereIn('id', $contents->pluck('id_header')->filter()->all())
            ->get()
            ->keyBy('id');

        $items = [];
        foreach ($contents as $content) {
            $subtitle = $subtitles->get($content->id_header);
            if ($subtitle) {
                // Convert timestamp from seconds to HH:MM:SS format
                $totalSeconds = is_numeric($subtitle->timestamp) ? (int) $subtitle->timestamp : 0;
                $hours = floor($totalSeconds / 3600);
                $minutes = floor(($totalSeconds % 3600) / 60);
                $seconds = $totalSeconds % 60;
                $formattedTime = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

                // Use subtitle title if available, otherwise use audio title
                $displayTitle = $subtitle->title ?: ($subtitle->audio ? $subtitle->audio->title : 'Untitled');

                $items[] = [
                    'id' => $content->id,
                    'type' => 'audio',
                    'title' => $displayTitle,
                    'waktu' => $formattedTime,
                    'timestamp' => $formattedTime,
                    'source' => $subtitle->audio ? $subtitle->audio->title : 'Unknown',
                    'seq' => $content->seq,
                    'content' => $subtitle,
                ];
            }
        }

        return Inertia::render('Topic/Detail', [
            'category' => $category,
            'topicCategory' => $topicCategory,
            'items' => $items,
            // Optional: left out of the initial load and only fetched when the
            // picker asks for it. Sending every audio_subtitle row up front
            // meant ~160 MB and a 5 MB payload on a table of ~18k rows, which
            // exhausted PHP's memory limit before Laravel could render at all.
            'availableItems' => Inertia::optional(fn () => $this->availableSubtitles($request)),
            'availableFilters' => [
                'search' => trim((string) $request->string('item_search')),
            ],
        ]);
    }

    /**
     * Searchable, paginated pool of audio subtitles for the item picker.
     */
    private function availableSubtitles(Request $request)
    {
        $search = trim((string) $request->string('item_search'));

        return AudioSubtitle::query()
            ->with('audio:id,title')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('audio', fn ($a) => $a->where('title', 'like', "%{$search}%"));
                });
            })
            ->orderBy('audio_id')
            ->orderBy('timestamp')
            ->paginate(20, ['id', 'audio_id', 'title', 'description', 'timestamp'], 'item_page')
            ->withQueryString()
            ->through(function (AudioSubtitle $subtitle): array {
                $totalSeconds = is_numeric($subtitle->timestamp) ? (int) $subtitle->timestamp : 0;

                return [
                    'id' => $subtitle->id,
                    'type' => 'audio',
                    'title' => $subtitle->title ?: ($subtitle->audio->title ?? 'Untitled'),
                    'timestamp' => sprintf(
                        '%02d:%02d:%02d',
                        floor($totalSeconds / 3600),
                        floor(($totalSeconds % 3600) / 60),
                        $totalSeconds % 60
                    ),
                    'description' => $subtitle->description ?? '',
                    'source' => $subtitle->audio->title ?? 'Unknown',
                    'audio_id' => $subtitle->audio_id,
                ];
            });
    }
}
