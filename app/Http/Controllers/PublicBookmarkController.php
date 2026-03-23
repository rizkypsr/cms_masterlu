<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Pengguna;
use App\Models\PublicBookmark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PublicBookmarkController extends Controller
{
    public function index()
    {
        $publicBookmarks = PublicBookmark::with(['bookmark.pengguna'])
            ->where('is_active', true)
            ->orderBy('is_pinned', 'desc')
            ->orderBy('seq')
            ->get()
            ->map(function ($pb) {
                return [
                    'id' => $pb->id,
                    'bookmark_id' => $pb->bookmark_id,
                    'seq' => $pb->seq,
                    'is_pinned' => $pb->is_pinned,
                    'bookmark' => $pb->bookmark ? [
                        'id' => $pb->bookmark->id,
                        'title' => $pb->bookmark->title,
                        'type' => $pb->bookmark->type,
                        'data' => $pb->bookmark->data,
                        'pengguna' => $pb->bookmark->pengguna ? [
                            'id' => $pb->bookmark->pengguna->id,
                            'name' => $pb->bookmark->pengguna->nama,
                        ] : null,
                    ] : null,
                ];
            });

        // Get all bookmarks with user info for the dropdown
        // Exclude bookmarks that are already in public_bookmarks
        $usedBookmarkIds = PublicBookmark::pluck('bookmark_id')->toArray();
        
        $allBookmarks = Bookmark::with('pengguna')
            ->whereNotNull('parent_id')
            ->whereNotIn('id', $usedBookmarkIds)
            ->get()
            ->map(function ($bookmark) {
                return [
                    'id' => $bookmark->id,
                    'title' => $bookmark->title,
                    'type' => $bookmark->type,
                    'data' => $bookmark->data,
                    'user_name' => $bookmark->pengguna ? $bookmark->pengguna->nama : 'Unknown',
                ];
            });

        return Inertia::render('PublicBookmark/Index', [
            'publicBookmarks' => $publicBookmarks,
            'allBookmarks' => $allBookmarks,
        ]);
    }

    public function getUserBookmarks($penggunaId)
    {
        $bookmarks = Bookmark::where('pengguna_id', $penggunaId)
            ->whereNull('parent_id')
            ->get(['id', 'title']);

        return response()->json($bookmarks);
    }

    public function store(Request $request)
    {
        $request->validate([
            'bookmark_id' => 'required|exists:bookmark,id',
        ]);

        // Get the max seq
        $maxSeq = PublicBookmark::max('seq') ?? 0;

        PublicBookmark::create([
            'bookmark_id' => $request->bookmark_id,
            'seq' => $maxSeq + 1,
            'is_active' => true,
            'is_pinned' => false,
        ]);

        return back();
    }

    public function togglePin(PublicBookmark $publicBookmark)
    {
        $publicBookmark->update([
            'is_pinned' => !$publicBookmark->is_pinned,
        ]);

        return back();
    }

    public function updateSeq(Request $request, PublicBookmark $publicBookmark)
    {
        $request->validate([
            'seq' => 'required|integer|min:1',
        ]);

        $targetPosition = $request->seq;

        DB::transaction(function () use ($publicBookmark, $targetPosition) {
            $allItems = PublicBookmark::where('is_active', true)
                ->orderBy('seq')
                ->lockForUpdate()
                ->get();

            $currentIndex = $allItems->search(fn($pb) => $pb->id === $publicBookmark->id);
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

                PublicBookmark::where('is_active', true)
                    ->whereBetween('seq', [$movingItem->seq + 1, $targetSeq])
                    ->decrement('seq');

                $movingItem->seq = $targetSeq;
            } else {
                $targetSeq = $allItems[$targetPosition - 1]->seq;

                PublicBookmark::where('is_active', true)
                    ->whereBetween('seq', [$targetSeq, $movingItem->seq - 1])
                    ->increment('seq');

                $movingItem->seq = $targetSeq;
            }

            $movingItem->save();
        });

        return back();
    }

    public function destroy(PublicBookmark $publicBookmark)
    {
        $publicBookmark->delete();

        return back();
    }
}
