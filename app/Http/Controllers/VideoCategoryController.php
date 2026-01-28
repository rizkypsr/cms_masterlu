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
    public function daftarIsi()
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

        return Inertia::render('Video/Index', [
            'categories' => $categories,
            'lang' => 'CH',
            'storeUrl' => '/video/daftar-isi/category',
        ]);
    }

    public function topik()
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

        return Inertia::render('Video/Index', [
            'categories' => $categories,
            'lang' => 'ID',
            'storeUrl' => '/video/topik/category',
        ]);
    }

    public function showCategory(Category $category)
    {
        // Load parent relationship
        $category->load('parent');
        
        // Get video categories where sub_category_id = category.id AND parent_id IS NULL
        // Children are video_category items where parent_id = this video_category's id
        $videoCategories = VideoCategory::where('sub_category_id', $category->id)
            ->whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->orderBy('seq')->orderBy('id');
            }])
            ->orderBy('seq')
            ->orderBy('id')
            ->get();

        $lang = $category->languange;
        $storeUrl = $lang === 'CH' 
            ? "/video/daftar-isi/category/{$category->id}/video-category"
            : "/video/topik/category/{$category->id}/video-category";

        return Inertia::render('Video/Category', [
            'videoCategories' => $videoCategories,
            'category' => $category,
            'lang' => $lang,
            'storeUrl' => $storeUrl,
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
        
        if (!$position || $position > $totalCategories) {
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
            
            $currentPosition = $categories->search(fn($c) => $c->id === $category->id) + 1;
            
            if ($newPosition !== $currentPosition) {
                foreach ($categories as $index => $cat) {
                    if ($cat->id !== $category->id) {
                        $cat->update(['seq' => $index + 1]);
                    }
                }
                
                $categories = Category::where('parent_id', $category->parent_id)
                    ->where('type', 'video')
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
                    ->where('type', 'video')
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
        
        if (!$position || $position > $total) {
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
            
            $currentPosition = $videoCategories->search(fn($vc) => $vc->id === $videoCategory->id) + 1;
            
            if ($newPosition !== $currentPosition) {
                foreach ($videoCategories as $index => $vc) {
                    if ($vc->id !== $videoCategory->id) {
                        $vc->update(['seq' => $index + 1]);
                    }
                }
                
                $videoCategories = VideoCategory::where('sub_category_id', $videoCategory->sub_category_id)
                    ->where('id', '!=', $videoCategory->id)
                    ->orderBy('seq')
                    ->orderBy('id')
                    ->get();
                
                $total = $videoCategories->count();
                
                if ($newPosition > $total + 1) {
                    $newPosition = $total + 1;
                }
                
                VideoCategory::where('sub_category_id', $videoCategory->sub_category_id)
                    ->where('id', '!=', $videoCategory->id)
                    ->where('seq', '>=', $newPosition)
                    ->increment('seq');
                
                $data['seq'] = $newPosition;
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
        
        if (!$position || $position > $total) {
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
            
            $currentPosition = $videos->search(fn($v) => $v->id === $video->id) + 1;
            
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
        if (!$mainVideo) {
            $mainVideo = Video::create([
                'video_category_id' => $videoCategory->id,
                'title' => $videoCategory->title,
            ]);
        }

        $position = $request->seq;
        $groupId = $request->group_id;
        $newGroupName = $request->new_group_name;
        
        // If creating a new group
        if (!$groupId && $newGroupName) {
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
            
            if (!$position || $position > $total) {
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
            
            $currentPosition = $subGroups->search(fn($sg) => $sg->id === $subVideo->id) + 1;
            
            if ($newPosition !== $currentPosition) {
                foreach ($subGroups as $index => $sg) {
                    if ($sg->id !== $subVideo->id) {
                        $sg->update(['seq' => $index + 1]);
                    }
                }
                
                $subGroups = VideoSubGroup::where('video_id', $subVideo->video_id)
                    ->where('id', '!=', $subVideo->id)
                    ->orderBy('seq')
                    ->orderBy('id')
                    ->get();
                
                $total = $subGroups->count();
                
                if ($newPosition > $total + 1) {
                    $newPosition = $total + 1;
                }
                
                VideoSubGroup::where('video_id', $subVideo->video_id)
                    ->where('id', '!=', $subVideo->id)
                    ->where('seq', '>=', $newPosition)
                    ->increment('seq');
                
                $data['seq'] = $newPosition;
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
            
            $videos = Video::where('video_sub_group_id', $video->video_sub_group_id)
                ->orderBy('seq')
                ->orderBy('id')
                ->get();
            
            $currentPosition = $videos->search(fn($v) => $v->id === $video->id) + 1;
            
            if ($newPosition !== $currentPosition) {
                foreach ($videos as $index => $v) {
                    if ($v->id !== $video->id) {
                        $v->update(['seq' => $index + 1]);
                    }
                }
                
                $videos = Video::where('video_sub_group_id', $video->video_sub_group_id)
                    ->where('id', '!=', $video->id)
                    ->orderBy('seq')
                    ->orderBy('id')
                    ->get();
                
                $total = $videos->count();
                
                if ($newPosition > $total + 1) {
                    $newPosition = $total + 1;
                }
                
                Video::where('video_sub_group_id', $video->video_sub_group_id)
                    ->where('id', '!=', $video->id)
                    ->where('seq', '>=', $newPosition)
                    ->increment('seq');
                
                $data['seq'] = $newPosition;
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

        VideoSubtitle::create([
            'video_id' => $video->id,
            'timestamp' => $request->timestamp,
            'description' => $request->description,
        ]);

        return back();
    }

    public function updateSubtitle(Request $request, VideoSubtitle $subtitle)
    {
        $request->validate([
            'timestamp' => 'required|string|max:20',
            'description' => 'required|string',
        ]);

        $subtitle->update([
            'timestamp' => $request->timestamp,
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
