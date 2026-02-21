<?php

namespace App\Http\Controllers;

use App\Models\Audio;
use App\Models\AudioSubGroup;
use App\Models\AudioSubtitle;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AudioCategoryController extends Controller
{
    public function daftarIsi(?Category $category = null)
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

        // If a category is selected, load audio sub-groups
        $subGroups = [];
        $selectedCategory = null;

        if ($category) {
            $category->load('parent');
            $selectedCategory = $category;

            $subGroups = AudioSubGroup::where('audio_category_id', $category->id)
                ->with(['audios' => function ($query) {
                    $query->orderBy('seq')->orderBy('id');
                }])
                ->orderBy('seq')
                ->orderBy('id')
                ->get();
        }

        return Inertia::render('Audio/Index', [
            'categories' => $categories,
            'lang' => 'CH',
            'storeUrl' => '/audio/daftar-isi/category',
            'subGroups' => $subGroups,
            'selectedCategory' => $selectedCategory,
        ]);
    }

    public function topik(?Category $category = null)
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

        // If a category is selected, load audio sub-groups
        $subGroups = [];
        $selectedCategory = null;

        if ($category) {
            $category->load('parent');
            $selectedCategory = $category;

            $subGroups = AudioSubGroup::where('audio_category_id', $category->id)
                ->with(['audios' => function ($query) {
                    $query->orderBy('seq')->orderBy('id');
                }])
                ->orderBy('seq')
                ->orderBy('id')
                ->get();
        }

        return Inertia::render('Audio/Index', [
            'categories' => $categories,
            'lang' => 'ID',
            'storeUrl' => '/audio/topik/category',
            'subGroups' => $subGroups,
            'selectedCategory' => $selectedCategory,
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

        if (! $position || $position > $totalCategories) {
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

    // Sub-Group CRUD (audio_sub_group and audio items)
    public function storeSubGroup(Request $request, Category $category)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'url' => 'nullable|string',
            'duration' => 'nullable|string',
            'seq' => 'nullable|integer',
            'group_id' => 'nullable|exists:audio_sub_group,id',
            'new_group_name' => 'nullable|string|max:255',
            'have_child' => 'nullable|boolean',
        ]);

        $position = $request->seq;
        $groupId = $request->group_id;
        $newGroupName = $request->new_group_name;
        $haveChild = $request->have_child ?? 1; // Default to 1 (has children)

        // If creating a new group
        if (! $groupId && $newGroupName) {
            $subGroups = AudioSubGroup::where('audio_category_id', $category->id)
                ->orderBy('seq')
                ->orderBy('id')
                ->get();

            $total = $subGroups->count();

            // Normalize existing sequences
            foreach ($subGroups as $index => $sg) {
                $sg->update(['seq' => $index + 1]);
            }

            // Determine new sequence position
            if (! $position || $position > $total) {
                $newGroupSeq = $total + 1;
            } else {
                // Shift sequences at or after the new position
                AudioSubGroup::where('audio_category_id', $category->id)
                    ->where('seq', '>=', $position)
                    ->increment('seq');

                $newGroupSeq = $position;
            }

            $newGroup = AudioSubGroup::create([
                'name' => $newGroupName,
                'audio_category_id' => $category->id,
                'seq' => $newGroupSeq,
                'have_child' => $haveChild,
            ]);

            $groupId = $newGroup->id;
        }

        if ($groupId) {
            // Adding an audio to a sub-group (only if title is provided)
            if ($request->title) {
                $audios = Audio::where('audio_sub_group_id', $groupId)
                    ->orderBy('seq')
                    ->orderBy('id')
                    ->get();

                $total = $audios->count();

                foreach ($audios as $index => $a) {
                    $a->update(['seq' => $index + 1]);
                }

                if (! $position || $position > $total) {
                    $newSeq = $total + 1;
                } else {
                    Audio::where('audio_sub_group_id', $groupId)
                        ->where('seq', '>=', $position)
                        ->increment('seq');

                    $newSeq = $position;
                }

                Audio::create([
                    'title' => $request->title,
                    'url' => $request->url,
                    'duration' => $request->duration,
                    'audio_sub_group_id' => $groupId,
                    'seq' => $newSeq,
                ]);
            }
        }

        return back();
    }

    public function updateSubGroup(Request $request, AudioSubGroup $subGroup)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'seq' => 'nullable|integer',
        ]);

        $data = ['name' => $request->title];

        if ($request->has('seq')) {
            $newPosition = $request->seq;

            $subGroups = AudioSubGroup::where('audio_category_id', $subGroup->audio_category_id)
                ->orderBy('seq')
                ->orderBy('id')
                ->get();

            $currentPosition = $subGroups->search(fn ($sg) => $sg->id === $subGroup->id) + 1;

            if ($newPosition !== $currentPosition && $newPosition <= $subGroups->count()) {
                // SWAP operation
                $targetSubGroup = $subGroups[$newPosition - 1];
                $currentSeq = $subGroup->seq;
                $targetSeq = $targetSubGroup->seq;

                $subGroup->update(['seq' => $targetSeq]);
                $targetSubGroup->update(['seq' => $currentSeq]);

                unset($data['seq']);
            } elseif ($newPosition > $subGroups->count()) {
                // Moving to "Terakhir"
                $remainingSubGroups = $subGroups->filter(fn ($sg) => $sg->id !== $subGroup->id);
                foreach ($remainingSubGroups->values() as $index => $sg) {
                    $sg->update(['seq' => $index + 1]);
                }

                $data['seq'] = $remainingSubGroups->count() + 1;
            }
        }

        $subGroup->update($data);

        return back();
    }

    public function destroySubGroup(AudioSubGroup $subGroup)
    {
        $subGroup->delete();

        return back();
    }

    // Audio Child CRUD
    public function updateAudioChild(Request $request, Audio $audio)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'nullable|string',
            'duration' => 'nullable|string',
            'seq' => 'nullable|integer',
        ]);

        $data = [
            'title' => $request->title,
            'url' => $request->url,
            'duration' => $request->duration,
        ];

        if ($request->has('seq')) {
            $newPosition = $request->seq;

            $audios = Audio::where('audio_sub_group_id', $audio->audio_sub_group_id)
                ->orderBy('seq')
                ->orderBy('id')
                ->get();

            $currentPosition = $audios->search(fn ($a) => $a->id === $audio->id) + 1;

            if ($newPosition !== $currentPosition && $newPosition <= $audios->count()) {
                // SWAP operation
                $targetAudio = $audios[$newPosition - 1];
                $currentSeq = $audio->seq;
                $targetSeq = $targetAudio->seq;

                $audio->update(['seq' => $targetSeq]);
                $targetAudio->update(['seq' => $currentSeq]);

                unset($data['seq']);
            } elseif ($newPosition > $audios->count()) {
                // Moving to "Terakhir"
                $remainingAudios = $audios->filter(fn ($a) => $a->id !== $audio->id);
                foreach ($remainingAudios->values() as $index => $a) {
                    $a->update(['seq' => $index + 1]);
                }

                $data['seq'] = $remainingAudios->count() + 1;
            }
        }

        $audio->update($data);

        return back();
    }

    public function destroyAudioChild(Audio $audio)
    {
        $audio->delete();

        return back();
    }

    // Subtitle CRUD
    public function showSubtitle(Audio $audio)
    {
        $subtitles = AudioSubtitle::where('audio_id', $audio->id)
            ->orderBy('timestamp')
            ->get();

        return Inertia::render('Audio/Subtitle', [
            'audio' => $audio,
            'subtitles' => $subtitles,
        ]);
    }

    public function storeSubtitle(Request $request, Audio $audio)
    {
        $request->validate([
            'timestamp' => 'required|string|max:20',
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'script' => 'nullable|string',
        ]);

        // Convert timestamp string (HH:MM:SS or MM:SS) to seconds
        $timeParts = explode(':', $request->timestamp);
        $seconds = 0;

        if (count($timeParts) === 3) {
            // HH:MM:SS format
            $seconds = ((int) $timeParts[0] * 3600 + (int) $timeParts[1] * 60 + (int) $timeParts[2]);
        } elseif (count($timeParts) === 2) {
            // MM:SS format
            $seconds = ((int) $timeParts[0] * 60 + (int) $timeParts[1]);
        } else {
            // Assume it's already in seconds
            $seconds = (int) $request->timestamp;
        }

        AudioSubtitle::create([
            'audio_id' => $audio->id,
            'timestamp' => $seconds,
            'title' => $request->title,
            'description' => $request->description,
            'script' => $request->script,
        ]);

        return back();
    }

    public function uploadSrtFile(Request $request, Audio $audio)
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

                AudioSubtitle::create([
                    'audio_id' => $audio->id,
                    'timestamp' => $timestamp,
                    'description' => $description,
                ]);

                $count++;
            }

            return back()->with('success', "SRT file uploaded successfully! {$count} subtitles added.");
        } catch (\Exception $e) {
            Log::error('SRT Upload Error: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return back()->withErrors(['error' => 'Failed to parse SRT file: '.$e->getMessage()]);
        }
    }

    public function exportSrtFile(Audio $audio)
    {
        $subtitles = AudioSubtitle::where('audio_id', $audio->id)
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

            // Use script field for audio, strip HTML tags for SRT export
            $text = strip_tags($subtitle->script ?: $subtitle->description ?: '');

            $srtContent .= "{$index}\n";
            $srtContent .= "{$startTime} --> {$endTime}\n";
            $srtContent .= "{$text}\n\n";

            $index++;
        }

        // Generate filename
        $filename = 'audio_'.$audio->id.'_subtitles.srt';

        return response($srtContent, 200)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function updateSubtitle(Request $request, AudioSubtitle $subtitle)
    {
        $request->validate([
            'timestamp' => 'required|string|max:20',
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'script' => 'nullable|string',
        ]);

        // Convert timestamp string (HH:MM:SS or MM:SS) to seconds
        $timeParts = explode(':', $request->timestamp);
        $seconds = 0;

        if (count($timeParts) === 3) {
            // HH:MM:SS format
            $seconds = ((int) $timeParts[0] * 3600 + (int) $timeParts[1] * 60 + (int) $timeParts[2]);
        } elseif (count($timeParts) === 2) {
            // MM:SS format
            $seconds = ((int) $timeParts[0] * 60 + (int) $timeParts[1]);
        } else {
            // Assume it's already in seconds
            $seconds = (int) $request->timestamp;
        }

        $subtitle->update([
            'timestamp' => $seconds,
            'title' => $request->title,
            'description' => $request->description,
            'script' => $request->script,
        ]);

        return back();
    }

    public function destroySubtitle(AudioSubtitle $subtitle)
    {
        $subtitle->delete();

        return back();
    }

    public function destroyAllSubtitles(Audio $audio)
    {
        AudioSubtitle::where('audio_id', $audio->id)->delete();

        return back();
    }
}
