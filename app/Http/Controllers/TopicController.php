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
            if (! isset($validated['seq'])) {
                // No position specified, add to end
                $maxSeq = TopicContent::where('topics_category_id', $categoryId)->max('seq') ?? 0;
                $validated['seq'] = $maxSeq + 1;
            } else {
                // Position specified, use shift-based insertion
                $newPosition = $validated['seq'];

                // Get total count
                $totalCount = TopicContent::where('topics_category_id', $categoryId)->count();

                // Validate and adjust position
                if ($newPosition > $totalCount + 1) {
                    $newPosition = $totalCount + 1;
                    $validated['seq'] = $newPosition;
                }

                // Shift existing items to make room for new item
                TopicContent::where('topics_category_id', $categoryId)
                    ->where('seq', '>=', $newPosition)
                    ->increment('seq');
            }

            TopicContent::create($validated);
        });

        return redirect()->back();
    }

    public function updateContent(Request $request, $id)
    {
        $content = TopicContent::findOrFail($id);

        $validated = $request->validate([
            'seq' => 'nullable|integer|min:1',
        ]);

        if (isset($validated['seq']) && $validated['seq'] != $content->seq) {
            $newPosition = $validated['seq'];
            $oldPosition = $content->seq;

            DB::transaction(function () use ($content, $newPosition, $oldPosition, $id) {
                // Get total count and validate new position
                $totalCount = TopicContent::where('topics_category_id', $content->topics_category_id)->count();

                if ($newPosition > $totalCount) {
                    // If position exceeds total, move to last position
                    $newPosition = $totalCount;
                }

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

    public function detail(Request $request, $categoryId)
    {
        $category = Category::first();
        $topicCategory = TopicCategory::with('topic')->findOrFail($categoryId);

        $contents = TopicContent::where('topics_category_id', $categoryId)
            ->orderBy('seq')
            ->get();

        $items = [];
        foreach ($contents as $content) {
            $subtitle = AudioSubtitle::with('audio')->find($content->id_header);
            if ($subtitle) {
                // Convert timestamp from milliseconds to HH:MM:SS format
                $ms = is_numeric($subtitle->timestamp) ? (int) $subtitle->timestamp : 0;
                $totalSeconds = floor($ms / 1000);
                $hours = floor($totalSeconds / 3600);
                $minutes = floor(($totalSeconds % 3600) / 60);
                $seconds = $totalSeconds % 60;
                $formattedTime = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

                // Use subtitle title if available, otherwise use audio title
                $displayTitle = $subtitle->title ?: ($subtitle->audio ? $subtitle->audio->title : 'Untitled');

                $items[] = [
                    'id' => $content->id,
                    'type' => 'audio',
                    'title' => $displayTitle.' - '.$formattedTime,
                    'seq' => $content->seq,
                    'content' => $subtitle,
                ];
            }
        }

        // Get all available audio subtitle items for selection (no filtering)
        $availableItems = AudioSubtitle::with('audio')
            ->orderBy('audio_id')
            ->orderBy('timestamp')
            ->get()
            ->map(function ($subtitle) {
                // Convert timestamp from milliseconds to HH:MM:SS format
                $ms = is_numeric($subtitle->timestamp) ? (int) $subtitle->timestamp : 0;
                $totalSeconds = floor($ms / 1000);
                $hours = floor($totalSeconds / 3600);
                $minutes = floor(($totalSeconds % 3600) / 60);
                $seconds = $totalSeconds % 60;
                $formattedTime = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

                // Use subtitle title if available, otherwise use audio title
                $displayTitle = $subtitle->title ?: ($subtitle->audio ? $subtitle->audio->title : 'Untitled');

                return [
                    'id' => $subtitle->id,
                    'type' => 'audio',
                    'title' => $displayTitle.' - '.$formattedTime,
                    'audio_id' => $subtitle->audio_id,
                ];
            });

        return Inertia::render('Topic/Detail', [
            'category' => $category,
            'topicCategory' => $topicCategory,
            'items' => $items,
            'availableItems' => $availableItems,
        ]);
    }
}
