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
        $publicBookmarks = PublicBookmark::with(['pengguna'])
            ->where('is_active', true)
            ->orderBy('is_pinned', 'desc')
            ->orderBy('seq')
            ->get()
            ->map(function ($pb) {
                return [
                    'id' => $pb->id,
                    'title' => $pb->title,
                    'name' => $pb->name,
                    'seq' => $pb->seq,
                    'is_pinned' => $pb->is_pinned,
                    'pengguna' => $pb->pengguna ? [
                        'id' => $pb->pengguna->id,
                        'name' => $pb->pengguna->nama,
                    ] : null,
                ];
            });

        // Get all users who have bookmarks
        $users = Pengguna::whereHas('bookmarks', function($query) {
            $query->whereNotNull('parent_id');
        })->get(['id', 'nama']);

        return Inertia::render('PublicBookmark/Index', [
            'publicBookmarks' => $publicBookmarks,
            'users' => $users,
        ]);
    }

    public function getUserBookmarks($penggunaId)
    {
        $bookmarks = Bookmark::where('pengguna_id', $penggunaId)
            ->whereNotNull('parent_id')
            ->get(['id', 'title']);

        return response()->json($bookmarks);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'name' => 'nullable|string|max:255',
            'pengguna_id' => 'required|exists:pengguna,id',
            'seq' => 'nullable|integer|min:1',
        ]);

        $targetSeq = $request->seq ?? (PublicBookmark::max('seq') ?? 0) + 1;

        DB::transaction(function () use ($request, $targetSeq) {
            // Shift existing items if needed
            PublicBookmark::where('seq', '>=', $targetSeq)
                ->increment('seq');

            PublicBookmark::create([
                'title' => $request->title,
                'name' => $request->name,
                'pengguna_id' => $request->pengguna_id,
                'seq' => $targetSeq,
                'is_active' => true,
                'is_pinned' => false,
            ]);
        });

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
            'name' => 'nullable|string|max:255',
            'seq' => 'required|integer|min:1',
        ]);

        $targetPosition = $request->seq;

        DB::transaction(function () use ($request, $publicBookmark, $targetPosition) {
            // Update name
            $publicBookmark->update([
                'name' => $request->name,
            ]);

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
