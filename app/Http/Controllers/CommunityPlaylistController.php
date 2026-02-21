<?php

namespace App\Http\Controllers;

use App\Models\Audio;
use App\Models\AudioSubtitle;
use App\Models\Book;
use App\Models\CommunityPlaylist;
use App\Models\CommunityPlaylistItem;
use App\Models\Video;
use App\Models\VideoCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommunityPlaylistController extends Controller
{
    public function index(Request $request)
    {
        $query = CommunityPlaylist::with(['user', 'items']);

        // Filter by pinned status
        if ($request->has('pinned')) {
            $query->where('is_pinned', $request->boolean('pinned'));
        }

        // Search by title or user name
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('nama', 'like', "%{$search}%");
                    });
            });
        }

        // Order by pinned first, then by created date
        $playlists = $query->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('CommunityPlaylist/Index', [
            'playlists' => $playlists,
            'filters' => [
                'search' => $request->search,
                'pinned' => $request->pinned,
            ],
        ]);
    }

    public function show(CommunityPlaylist $playlist)
    {
        $playlist->load(['user', 'items']);

        // Transform items to include content and display info
        $playlist->items->transform(function ($item) {
            $content = null;
            $displayTitle = 'Unknown Item';
            $navigateUrl = null;

            switch ($item->type) {
                case CommunityPlaylistItem::TYPE_AUDIO:
                    if (isset($item->data['subtitleId']) && $item->data['subtitleId'] !== null) {
                        // Has subtitle - navigate to subtitle detail page
                        $subtitle = AudioSubtitle::with('audio')->find($item->data['subtitleId']);

                        if ($subtitle && $subtitle->audio) {
                            $content = $subtitle;
                            $title = $subtitle->title ?: $subtitle->audio->title ?: 'Untitled';

                            // Format timestamp
                            if ($subtitle->timestamp) {
                                $ms = is_numeric($subtitle->timestamp) ? (int) $subtitle->timestamp : 0;
                                $totalSeconds = floor($ms / 1000);
                                $hours = floor($totalSeconds / 3600);
                                $minutes = floor(($totalSeconds % 3600) / 60);
                                $seconds = $totalSeconds % 60;
                                $time = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
                                $displayTitle = $title.' - '.$time;
                            } else {
                                $displayTitle = $title;
                            }

                            // Navigate to audio subtitle page using audio ID
                            $navigateUrl = "/audio/subtitle/{$subtitle->audio->id}";
                        }
                    } elseif (isset($item->data['audioId'])) {
                        // No subtitle or subtitleId is null - show audio title
                        $audio = Audio::with('subGroup.category')->find($item->data['audioId']);
                        if ($audio) {
                            $content = $audio;
                            $displayTitle = $audio->title ?: 'Untitled Audio';

                            // Navigate to audio subtitle page
                            $navigateUrl = "/audio/subtitle/{$audio->id}";
                        }
                    }
                    break;

                case CommunityPlaylistItem::TYPE_VIDEO:
                    if (isset($item->data['video_category_id'])) {
                        $videoCategory = VideoCategory::find($item->data['video_category_id']);
                        if ($videoCategory) {
                            $content = $videoCategory;
                            $displayTitle = $videoCategory->title ?: 'Untitled Video Category';
                            $navigateUrl = "/video/video-category/{$videoCategory->id}";
                        }
                    } elseif (isset($item->data['videoId'])) {
                        $video = Video::find($item->data['videoId']);
                        if ($video) {
                            $content = $video;
                            $displayTitle = $video->title ?: 'Untitled Video';
                            // Navigate to video subtitle page if available
                            $navigateUrl = "/video/subtitle/{$video->id}";
                        }
                    }
                    break;

                case CommunityPlaylistItem::TYPE_BOOK:
                    if (isset($item->data['book_id'])) {
                        $book = Book::find($item->data['book_id']);
                        if ($book) {
                            $content = $book;
                            $displayTitle = $book->title ?: 'Untitled Book';
                            $navigateUrl = "/book/{$book->id}";
                        }
                    }
                    break;
            }

            $item->content = $content;
            $item->display_title = $displayTitle;
            $item->navigate_url = $navigateUrl;

            return $item;
        });

        return Inertia::render('CommunityPlaylist/Show', [
            'playlist' => $playlist,
        ]);
    }

    public function togglePin(CommunityPlaylist $playlist)
    {
        $playlist->update([
            'is_pinned' => ! $playlist->is_pinned,
        ]);

        return back()->with('success', $playlist->is_pinned ? 'Playlist pinned successfully' : 'Playlist unpinned successfully');
    }

    public function update(Request $request, CommunityPlaylist $playlist)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $playlist->update($validated);

        return back()->with('success', 'Playlist updated successfully');
    }
}
