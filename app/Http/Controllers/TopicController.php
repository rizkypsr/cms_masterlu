<?php

namespace App\Http\Controllers;

use App\Models\AudioSubtitle;
use App\Models\Category;
use App\Models\Topic;
use App\Models\TopicCategory;
use App\Models\TopicContent;
use Illuminate\Http\Request;
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
            'seq' => 'nullable|integer',
        ]);

        $validated['category_id'] = $category->id;

        if (! isset($validated['seq'])) {
            $maxSeq = Topic::where('category_id', $category->id)->max('seq') ?? 0;
            $validated['seq'] = $maxSeq + 1;
        } else {
            $existingTopic = Topic::where('category_id', $category->id)
                ->where('seq', $validated['seq'])
                ->first();

            if ($existingTopic) {
                $existingTopic->seq = -1;
                $existingTopic->save();

                Topic::create($validated);

                $existingTopic->seq = $validated['seq'];
                $existingTopic->save();

                return redirect()->back();
            }
        }

        Topic::create($validated);

        return redirect()->back();
    }

    public function update(Request $request, $id)
    {
        $topic = Topic::findOrFail($id);
        $category = Category::first();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
        ]);

        if (isset($validated['seq']) && $validated['seq'] != $topic->seq) {
            $existingTopic = Topic::where('category_id', $category->id)
                ->where('seq', $validated['seq'])
                ->where('id', '!=', $id)
                ->first();

            if ($existingTopic) {
                $existingTopic->seq = $topic->seq;
                $existingTopic->save();

                $topic->seq = $validated['seq'];
            } else {
                $topic->seq = $validated['seq'];
            }
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
            'seq' => 'nullable|integer',
            'have_child' => 'required|integer',
            'parent_id' => 'nullable|integer',
        ]);

        $validated['topics_id'] = $topicId;

        // Determine if this is a child or parent category
        $isChild = isset($validated['parent_id']) && $validated['parent_id'] !== null;

        if (! isset($validated['seq'])) {
            if ($isChild) {
                // Get max seq for children of this parent
                $maxSeq = TopicCategory::where('topics_id', $topicId)
                    ->where('parent_id', $validated['parent_id'])
                    ->max('seq') ?? 0;
            } else {
                // Get max seq for parent categories
                $maxSeq = TopicCategory::where('topics_id', $topicId)
                    ->whereNull('parent_id')
                    ->max('seq') ?? 0;
            }
            $validated['seq'] = $maxSeq + 1;
        } else {
            // Handle SWAP logic
            if ($isChild) {
                $existingCategory = TopicCategory::where('topics_id', $topicId)
                    ->where('parent_id', $validated['parent_id'])
                    ->where('seq', $validated['seq'])
                    ->first();
            } else {
                $existingCategory = TopicCategory::where('topics_id', $topicId)
                    ->whereNull('parent_id')
                    ->where('seq', $validated['seq'])
                    ->first();
            }

            if ($existingCategory) {
                $existingCategory->seq = -1;
                $existingCategory->save();

                TopicCategory::create($validated);

                $existingCategory->seq = $validated['seq'];
                $existingCategory->save();

                return redirect()->back();
            }
        }

        TopicCategory::create($validated);

        return redirect()->back();
    }

    public function updateCategory(Request $request, $id)
    {
        $category = TopicCategory::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
        ]);

        if (isset($validated['seq']) && $validated['seq'] != $category->seq) {
            // Determine if this is a child or parent category
            $isChild = $category->parent_id !== null;

            if ($isChild) {
                $existingCategory = TopicCategory::where('topics_id', $category->topics_id)
                    ->where('parent_id', $category->parent_id)
                    ->where('seq', $validated['seq'])
                    ->where('id', '!=', $id)
                    ->first();
            } else {
                $existingCategory = TopicCategory::where('topics_id', $category->topics_id)
                    ->whereNull('parent_id')
                    ->where('seq', $validated['seq'])
                    ->where('id', '!=', $id)
                    ->first();
            }

            if ($existingCategory) {
                $existingCategory->seq = $category->seq;
                $existingCategory->save();

                $category->seq = $validated['seq'];
            } else {
                $category->seq = $validated['seq'];
            }
        }

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
            'seq' => 'nullable|integer',
        ]);

        $validated['topics_category_id'] = $categoryId;
        $validated['type'] = 'audio'; // Always audio

        if (! isset($validated['seq'])) {
            $maxSeq = TopicContent::where('topics_category_id', $categoryId)->max('seq') ?? 0;
            $validated['seq'] = $maxSeq + 1;
        } else {
            $existingContent = TopicContent::where('topics_category_id', $categoryId)
                ->where('seq', $validated['seq'])
                ->first();

            if ($existingContent) {
                $existingContent->seq = -1;
                $existingContent->save();

                TopicContent::create($validated);

                $existingContent->seq = $validated['seq'];
                $existingContent->save();

                return redirect()->back();
            }
        }

        TopicContent::create($validated);

        return redirect()->back();
    }

    public function updateContent(Request $request, $id)
    {
        $content = TopicContent::findOrFail($id);

        $validated = $request->validate([
            'seq' => 'nullable|integer',
        ]);

        if (isset($validated['seq']) && $validated['seq'] != $content->seq) {
            $existingContent = TopicContent::where('topics_category_id', $content->topics_category_id)
                ->where('seq', $validated['seq'])
                ->where('id', '!=', $id)
                ->first();

            if ($existingContent) {
                $existingContent->seq = $content->seq;
                $existingContent->save();

                $content->seq = $validated['seq'];
            } else {
                $content->seq = $validated['seq'];
            }
        }

        $content->save();

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
