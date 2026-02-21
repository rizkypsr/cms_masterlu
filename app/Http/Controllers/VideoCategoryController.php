<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Video;
use App\Models\VideoCategory;
use App\Models\VideoSubGroup;
use App\Models\VideoSubtitle;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VideoCategoryController extends Controller
{
    public function daftarIsi(?Category $category = null)
    {
        $categories = Category::whereNull('parent_id')
            ->where('type', 'video')
            ->where('languange', 'CH')
            ->with(['children' => function ($query) {
                $query->where('languange', 'CH')->orderBy('seq')->orderBy('id');
            }])
            ->orderBy('seq')
            ->orderBy('id')
            ->get();

        // If a category is selected, load video categories
        $videoCategories = [];
        $selectedCategory = null;
        $videoCategoryStoreUrl = null;

        if ($category) {
            $category->load('parent');
            $selectedCategory = $category;

            $videoCategories = VideoCategory::where('sub_category_id', $category->id)
                ->whereNull('parent_id')
                ->with(['children' => function ($query) {
                    $query->orderBy('seq')->orderBy('id');
                }])
                ->orderBy('seq')
                ->orderBy('id')
                ->get();

            $videoCategoryStoreUrl = "/video/daftar-isi/category/{$category->id}/video-category";
        }

        return Inertia::render('Video/Index', [
            'categories' => $categories,
            'lang' => 'CH',
            'storeUrl' => '/video/daftar-isi/category',
            'videoCategories' => $videoCategories,
            'selectedCategory' => $selectedCategory,
            'videoCategoryStoreUrl' => $videoCategoryStoreUrl,
        ]);
    }

    public function topik(?Category $category = null)
    {
        $categories = Category::whereNull('parent_id')
            ->where('type', 'video')
            ->where('languange', 'ID')
            ->with(['children' => function ($query) {
                $query->where('languange', 'ID')->orderBy('seq')->orderBy('id');
            }])
            ->orderBy('seq')
            ->orderBy('id')
            ->get();

        // If a category is selected, load video categories
        $videoCategories = [];
        $selectedCategory = null;
        $videoCategoryStoreUrl = null;

        if ($category) {
            $category->load('parent');
            $selectedCategory = $category;

            $videoCategories = VideoCategory::where('sub_category_id', $category->id)
                ->whereNull('parent_id')
                ->with(['children' => function ($query) {
                    $query->orderBy('seq')->orderBy('id');
                }])
                ->orderBy('seq')
                ->orderBy('id')
                ->get();

            $videoCategoryStoreUrl = "/video/topik/category/{$category->id}/video-category";
        }

        return Inertia::render('Video/Index', [
            'categories' => $categories,
            'lang' => 'ID',
            'storeUrl' => '/video/topik/category',
            'videoCategories' => $videoCategories,
            'selectedCategory' => $selectedCategory,
            'videoCategoryStoreUrl' => $videoCategoryStoreUrl,
        ]);
    }

    public function showVideoCategory(VideoCategory $videoCategory)
    {
        // Load parent relationship
        $videoCategory->load('parent');

        // Get main video for this video_category
        $video = Video::where('video_category_id', $videoCategory->id)
            ->whereNull('parent_id')
            ->first();

        // Get sub-videos: video_sub_group items for this video, with their video children
        $subVideos = [];
        if ($video) {
            $subVideos = VideoSubGroup::where('video_id', $video->id)
                ->with(['videos' => function ($query) {
                    $query->orderBy('seq')->orderBy('id');
                }])
                ->orderBy('seq')
                ->orderBy('id')
                ->get();
        }

        // Get the category to determine language
        $category = Category::find($videoCategory->sub_category_id);
        $lang = $category?->languange ?? 'CH';

        return Inertia::render('Video/Detail', [
            'video' => $video,
            'videoCategory' => $videoCategory,
            'subVideos' => $subVideos,
            'lang' => $lang,
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
            ->where('type', 'video')
            ->where('languange', $lang)
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
                ->where('type', 'video')
                ->where('languange', $lang)
                ->where('seq', '>=', $position)
                ->increment('seq');

            $newSeq = $position;
        }

        Category::create([
            'title' => $request->title,
            'parent_id' => $request->parent_id,
            'seq' => $newSeq,
            'type' => 'video',
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
                ->where('type', 'video')
                ->where('languange', $lang)
                ->orderBy('seq')
                ->orderBy('id')
                ->get();

            $currentPosition = $categories->search(fn ($c) => $c->id === $category->id) + 1;

            if ($newPosition !== $currentPosition && $newPosition <= $categories->count()) {
                // SWAP operation
                $targetCategory = $categories[$newPosition - 1];
                $currentSeq = $category->seq;
                $targetSeq = $targetCategory->seq;

                $category->update(['seq' => $targetSeq]);
                $targetCategory->update(['seq' => $currentSeq]);

                unset($data['seq']);
            } elseif ($newPosition > $categories->count()) {
                // Moving to "Terakhir"
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

    // Video Category CRUD
    public function storeVideoCategory(Request $request, Category $category)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
            'parent_id' => 'nullable|exists:video_category,id',
        ]);

        $position = $request->seq;
        $parentId = $request->parent_id;

        // If parent_id is set, we're adding a child, otherwise a root video category
        if ($parentId) {
            $videoCategories = VideoCategory::where('parent_id', $parentId)
                ->orderBy('seq')
                ->orderBy('id')
                ->get();
        } else {
            $videoCategories = VideoCategory::where('sub_category_id', $category->id)
                ->whereNull('parent_id')
                ->orderBy('seq')
                ->orderBy('id')
                ->get();
        }

        $total = $videoCategories->count();

        foreach ($videoCategories as $index => $vc) {
            $vc->update(['seq' => $index + 1]);
        }

        if (! $position || $position > $total) {
            $newSeq = $total + 1;
        } else {
            if ($parentId) {
                VideoCategory::where('parent_id', $parentId)
                    ->where('seq', '>=', $position)
                    ->increment('seq');
            } else {
                VideoCategory::where('sub_category_id', $category->id)
                    ->whereNull('parent_id')
                    ->where('seq', '>=', $position)
                    ->increment('seq');
            }

            $newSeq = $position;
        }

        VideoCategory::create([
            'title' => $request->title,
            'sub_category_id' => $category->id,
            'parent_id' => $parentId,
            'seq' => $newSeq,
        ]);

        return back();
    }

    public function updateVideoCategory(Request $request, VideoCategory $videoCategory)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
        ]);

        $data = ['title' => $request->title];

        if ($request->has('seq')) {
            $newPosition = $request->seq;

            $videoCategories = VideoCategory::where('sub_category_id', $videoCategory->sub_category_id)
                ->orderBy('seq')
                ->orderBy('id')
                ->get();

            $currentPosition = $videoCategories->search(fn ($vc) => $vc->id === $videoCategory->id) + 1;

            if ($newPosition !== $currentPosition && $newPosition <= $videoCategories->count()) {
                // SWAP operation
                $targetVideoCategory = $videoCategories[$newPosition - 1];
                $currentSeq = $videoCategory->seq;
                $targetSeq = $targetVideoCategory->seq;

                $videoCategory->update(['seq' => $targetSeq]);
                $targetVideoCategory->update(['seq' => $currentSeq]);

                unset($data['seq']);
            } elseif ($newPosition > $videoCategories->count()) {
                // Moving to "Terakhir"
                $remainingVideoCategories = $videoCategories->filter(fn ($vc) => $vc->id !== $videoCategory->id);
                foreach ($remainingVideoCategories->values() as $index => $vc) {
                    $vc->update(['seq' => $index + 1]);
                }

                $data['seq'] = $remainingVideoCategories->count() + 1;
            }
        }

        $videoCategory->update($data);

        return back();
    }

    public function destroyVideoCategory(VideoCategory $videoCategory)
    {
        $videoCategory->delete();

        return back();
    }

    // Video CRUD
    public function storeVideo(Request $request, Category $category)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'video_category_id' => 'required|exists:video_category,id',
            'seq' => 'nullable|integer',
        ]);

        $position = $request->seq;
        $videoCategoryId = $request->video_category_id;

        $videos = Video::where('video_category_id', $videoCategoryId)
            ->orderBy('seq')
            ->orderBy('id')
            ->get();

        $total = $videos->count();

        foreach ($videos as $index => $v) {
            $v->update(['seq' => $index + 1]);
        }

        if (! $position || $position > $total) {
            $newSeq = $total + 1;
        } else {
            Video::where('video_category_id', $videoCategoryId)
                ->where('seq', '>=', $position)
                ->increment('seq');

            $newSeq = $position;
        }

        Video::create([
            'title' => $request->title,
            'video_category_id' => $videoCategoryId,
            'seq' => $newSeq,
        ]);

        return back();
    }

    public function updateVideo(Request $request, Video $video)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
        ]);

        $data = ['title' => $request->title];

        if ($request->has('seq')) {
            $newPosition = $request->seq;

            $videos = Video::where('video_category_id', $video->video_category_id)
                ->orderBy('seq')
                ->orderBy('id')
                ->get();

            $currentPosition = $videos->search(fn ($v) => $v->id === $video->id) + 1;

            if ($newPosition !== $currentPosition) {
                foreach ($videos as $index => $v) {
                    if ($v->id !== $video->id) {
                        $v->update(['seq' => $index + 1]);
                    }
                }

                $videos = Video::where('video_category_id', $video->video_category_id)
                    ->where('id', '!=', $video->id)
                    ->orderBy('seq')
                    ->orderBy('id')
                    ->get();

                $total = $videos->count();

                if ($newPosition > $total + 1) {
                    $newPosition = $total + 1;
                }

                Video::where('video_category_id', $video->video_category_id)
                    ->where('id', '!=', $video->id)
                    ->where('seq', '>=', $newPosition)
                    ->increment('seq');

                $data['seq'] = $newPosition;
            }
        }

        $video->update($data);

        return back();
    }

    public function destroyVideo(Video $video)
    {
        $video->delete();

        return back();
    }

    // Video Detail Page - Create/Update Video
    public function storeOrUpdateVideoDetail(Request $request, VideoCategory $videoCategory)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'synopsis' => 'nullable|string',
            'url' => 'nullable|string',
            'url_audio' => 'nullable|string',
        ]);

        Video::updateOrCreate(
            ['video_category_id' => $videoCategory->id],
            [
                'title' => $request->title,
                'synopsis' => $request->synopsis,
                'url' => $request->url,
                'url_audio' => $request->url_audio,
            ]
        );

        return back();
    }

    // Sub-Video CRUD (video_sub_group and video items in detail page)
    public function storeSubVideo(Request $request, VideoCategory $videoCategory)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'synopsis' => 'nullable|string',
            'url' => 'nullable|string',
            'url_audio' => 'nullable|string',
            'seq' => 'nullable|integer',
            'group_id' => 'nullable|exists:video_sub_group,id',
            'new_group_name' => 'nullable|string|max:255',
        ]);

        // Get main video for this video_category
        $mainVideo = Video::where('video_category_id', $videoCategory->id)
            ->whereNull('parent_id')
            ->first();

        // If no main video exists, create one
        if (! $mainVideo) {
            $mainVideo = Video::create([
                'video_category_id' => $videoCategory->id,
                'title' => $videoCategory->title,
            ]);
        }

        $position = $request->seq;
        $groupId = $request->group_id;
        $newGroupName = $request->new_group_name;

        // If creating a new group
        if (! $groupId && $newGroupName) {
            // Create new video_sub_group
            $subGroups = VideoSubGroup::where('video_id', $mainVideo->id)
                ->orderBy('seq')
                ->orderBy('id')
                ->get();

            $total = $subGroups->count();
            $newGroupSeq = $total + 1;

            $newGroup = VideoSubGroup::create([
                'name' => $newGroupName,
                'video_id' => $mainVideo->id,
                'seq' => $newGroupSeq,
            ]);

            $groupId = $newGroup->id;
        }

        if ($groupId) {
            // Adding a video child to a sub-group
            $videos = Video::where('video_sub_group_id', $groupId)
                ->orderBy('seq')
                ->orderBy('id')
                ->get();

            $total = $videos->count();

            foreach ($videos as $index => $v) {
                $v->update(['seq' => $index + 1]);
            }

            if (! $position || $position > $total) {
                $newSeq = $total + 1;
            } else {
                Video::where('video_sub_group_id', $groupId)
                    ->where('seq', '>=', $position)
                    ->increment('seq');

                $newSeq = $position;
            }

            Video::create([
                'title' => $request->title,
                'synopsis' => $request->synopsis,
                'url' => $request->url,
                'url_audio' => $request->url_audio,
                'video_sub_group_id' => $groupId,
                'parent_id' => $mainVideo->id,
                'seq' => $newSeq,
            ]);
        }

        return back();
    }

    public function updateSubVideo(Request $request, VideoSubGroup $subVideo)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
        ]);

        $data = ['name' => $request->title];

        if ($request->has('seq')) {
            $newPosition = $request->seq;

            $subGroups = VideoSubGroup::where('video_id', $subVideo->video_id)
                ->orderBy('seq')
                ->orderBy('id')
                ->get();

            $currentPosition = $subGroups->search(fn ($sg) => $sg->id === $subVideo->id) + 1;

            if ($newPosition !== $currentPosition && $newPosition <= $subGroups->count()) {
                // SWAP operation
                $targetSubGroup = $subGroups[$newPosition - 1];
                $currentSeq = $subVideo->seq;
                $targetSeq = $targetSubGroup->seq;

                $subVideo->update(['seq' => $targetSeq]);
                $targetSubGroup->update(['seq' => $currentSeq]);

                unset($data['seq']);
            } elseif ($newPosition > $subGroups->count()) {
                // Moving to "Terakhir"
                $remainingSubGroups = $subGroups->filter(fn ($sg) => $sg->id !== $subVideo->id);
                foreach ($remainingSubGroups->values() as $index => $sg) {
                    $sg->update(['seq' => $index + 1]);
                }

                $data['seq'] = $remainingSubGroups->count() + 1;
            }
        }

        $subVideo->update($data);

        return back();
    }

    public function destroySubVideo(VideoSubGroup $subVideo)
    {
        $subVideo->delete();

        return back();
    }

    // Sub-Video Child (Video) CRUD
    public function updateSubVideoChild(Request $request, Video $video)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'synopsis' => 'nullable|string',
            'url' => 'nullable|string',
            'url_audio' => 'nullable|string',
            'seq' => 'nullable|integer',
        ]);

        $data = [
            'title' => $request->title,
            'synopsis' => $request->synopsis,
            'url' => $request->url,
            'url_audio' => $request->url_audio,
        ];

        if ($request->has('seq')) {
            $newPosition = $request->seq;

            // Get all videos in the same group, ordered by current sequence
            $videos = Video::where('video_sub_group_id', $video->video_sub_group_id)
                ->orderBy('seq')
                ->orderBy('id')
                ->get();

            // Find current position (1-based index in the ordered list)
            $currentIndex = $videos->search(fn ($v) => $v->id === $video->id);
            $currentPosition = $currentIndex + 1;

            if ($newPosition !== $currentPosition && $newPosition <= $videos->count()) {
                // SWAP operation: swap positions with the item at the target position
                $targetVideo = $videos[$newPosition - 1]; // Get video at target position (0-based index)
                $currentSeq = $video->seq;
                $targetSeq = $targetVideo->seq;

                // Swap the seq values
                $video->update(['seq' => $targetSeq]);
                $targetVideo->update(['seq' => $currentSeq]);

                // Don't update $data['seq'] since we already updated directly
                unset($data['seq']);
            } elseif ($newPosition > $videos->count()) {
                // Moving to "Terakhir" (last position)
                // Remove current item and normalize
                $remainingVideos = $videos->filter(fn ($v) => $v->id !== $video->id);
                foreach ($remainingVideos->values() as $index => $v) {
                    $v->update(['seq' => $index + 1]);
                }

                // Set to last position
                $data['seq'] = $remainingVideos->count() + 1;
            }
        }

        $video->update($data);

        return back();
    }

    public function destroySubVideoChild(Video $video)
    {
        $video->delete();

        return back();
    }

    // Subtitle CRUD
    public function showSubtitle(Video $video)
    {
        $subtitles = VideoSubtitle::where('video_id', $video->id)
            ->orderBy('timestamp')
            ->get();

        return Inertia::render('Video/Subtitle', [
            'video' => $video,
            'subtitles' => $subtitles,
        ]);
    }

    public function storeSubtitle(Request $request, Video $video)
    {
        $request->validate([
            'timestamp' => 'required|string|max:20',
            'description' => 'required|string',
        ]);

        // Convert timestamp string (HH:MM:SS or MM:SS) to milliseconds
        $timeParts = explode(':', $request->timestamp);
        $milliseconds = 0;

        if (count($timeParts) === 3) {
            // HH:MM:SS format
            $milliseconds = ((int) $timeParts[0] * 3600 + (int) $timeParts[1] * 60 + (int) $timeParts[2]) * 1000;
        } elseif (count($timeParts) === 2) {
            // MM:SS format
            $milliseconds = ((int) $timeParts[0] * 60 + (int) $timeParts[1]) * 1000;
        } else {
            // Assume it's already in milliseconds or seconds
            $milliseconds = (int) $request->timestamp;
            if ($milliseconds < 100000) {
                // Probably in seconds, convert to milliseconds
                $milliseconds *= 1000;
            }
        }

        VideoSubtitle::create([
            'video_id' => $video->id,
            'timestamp' => $milliseconds,
            'description' => $request->description,
        ]);

        return back();
    }

    public function uploadSrtFile(Request $request, Video $video)
    {
        $request->validate([
            'srt_file' => 'required|file|mimes:srt,txt|max:10240', // Max 10MB
        ]);

        try {
            $file = $request->file('srt_file');

            if (! $file) {
                return back()->withErrors(['error' => 'No file uploaded']);
            }

            $content = file_get_contents($file->getRealPath());

            if (empty($content)) {
                return back()->withErrors(['error' => 'File is empty']);
            }

            // Parse SRT file using mantas-done/subtitles
            $subtitles = (new \Done\Subtitles\Subtitles)->loadFromString($content);

            // Convert to internal format and save
            $internalFormat = $subtitles->getInternalFormat();

            if (empty($internalFormat)) {
                return back()->withErrors(['error' => 'No subtitles found in file']);
            }

            $count = 0;
            foreach ($internalFormat as $subtitle) {
                // timestamp is stored as bigint (milliseconds)
                // Convert start time from seconds to milliseconds
                $timestamp = (int) ($subtitle['start'] * 1000);

                // Get subtitle text (join lines if multiple)
                $description = is_array($subtitle['lines'])
                    ? implode("\n", $subtitle['lines'])
                    : $subtitle['lines'];

                VideoSubtitle::create([
                    'video_id' => $video->id,
                    'timestamp' => $timestamp,
                    'description' => $description,
                ]);

                $count++;
            }

            return back()->with('success', "SRT file uploaded successfully! {$count} subtitles added.");
        } catch (\Exception $e) {
            \Log::error('SRT Upload Error: '.$e->getMessage());
            \Log::error($e->getTraceAsString());

            return back()->withErrors(['error' => 'Failed to parse SRT file: '.$e->getMessage()]);
        }
    }

    public function exportSrtFile(Video $video)
    {
        $subtitles = VideoSubtitle::where('video_id', $video->id)
            ->orderBy('timestamp')
            ->get();

        if ($subtitles->isEmpty()) {
            return back()->withErrors(['error' => 'No subtitles to export']);
        }

        // Create SRT content
        $srtContent = '';
        $index = 1;

        foreach ($subtitles as $subtitle) {
            // Convert milliseconds to SRT time format (HH:MM:SS,mmm)
            $milliseconds = $subtitle->timestamp;
            $seconds = floor($milliseconds / 1000);
            $ms = $milliseconds % 1000;
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $secs = $seconds % 60;

            $startTime = sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $secs, $ms);

            // For end time, add 2 seconds (or use next subtitle's timestamp if available)
            $endMilliseconds = $milliseconds + 2000;
            $endSeconds = floor($endMilliseconds / 1000);
            $endMs = $endMilliseconds % 1000;
            $endHours = floor($endSeconds / 3600);
            $endMinutes = floor(($endSeconds % 3600) / 60);
            $endSecs = $endSeconds % 60;

            $endTime = sprintf('%02d:%02d:%02d,%03d', $endHours, $endMinutes, $endSecs, $endMs);

            // Strip HTML tags from description for SRT export
            $text = strip_tags($subtitle->description);

            $srtContent .= "{$index}\n";
            $srtContent .= "{$startTime} --> {$endTime}\n";
            $srtContent .= "{$text}\n\n";

            $index++;
        }

        // Generate filename
        $filename = 'video_'.$video->id.'_subtitles.srt';

        return response($srtContent, 200)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function updateSubtitle(Request $request, VideoSubtitle $subtitle)
    {
        $request->validate([
            'timestamp' => 'required|string|max:20',
            'description' => 'required|string',
        ]);

        // Convert timestamp string (HH:MM:SS or MM:SS) to milliseconds
        $timeParts = explode(':', $request->timestamp);
        $milliseconds = 0;

        if (count($timeParts) === 3) {
            // HH:MM:SS format
            $milliseconds = ((int) $timeParts[0] * 3600 + (int) $timeParts[1] * 60 + (int) $timeParts[2]) * 1000;
        } elseif (count($timeParts) === 2) {
            // MM:SS format
            $milliseconds = ((int) $timeParts[0] * 60 + (int) $timeParts[1]) * 1000;
        } else {
            // Assume it's already in milliseconds or seconds
            $milliseconds = (int) $request->timestamp;
            if ($milliseconds < 100000) {
                // Probably in seconds, convert to milliseconds
                $milliseconds *= 1000;
            }
        }

        $subtitle->update([
            'timestamp' => $milliseconds,
            'description' => $request->description,
        ]);

        return back();
    }

    public function destroySubtitle(VideoSubtitle $subtitle)
    {
        $subtitle->delete();

        return back();
    }

    public function destroyAllSubtitles(Video $video)
    {
        VideoSubtitle::where('video_id', $video->id)->delete();

        return back();
    }
}
