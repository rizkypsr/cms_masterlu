<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Image\Image;

class SliderController extends Controller
{
    public function index()
    {
        $sliders = Media::orderBy('seq', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return Inertia::render('Slider/Index', [
            'sliders' => $sliders,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'required|file|image|max:15360', // 15MB
            'seq' => 'required|integer|min:1',
        ]);

        // Handle image upload with Spatie Image
        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = md5(uniqid() . time()) . '.webp';
            $destinationPath = public_path('assets/upload/slider');
            
            // Create directory if it doesn't exist
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            
            $fullPath = $destinationPath . '/' . $filename;
            
            // Process image: resize to 950x350 and convert to WebP
            Image::load($file->getPathname())
                ->resize(950, 350)
                ->format('webp')
                ->save($fullPath);
            
            // Save full URL
            $imagePath = url('assets/upload/slider/' . $filename);
        }

        $targetPosition = $validated['seq'];

        DB::transaction(function () use ($validated, $targetPosition, $imagePath) {
            // Load all items ordered by seq
            $allItems = Media::orderBy('seq')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            
            $totalCount = $allItems->count();

            // If inserting at a position other than last, shift items
            if ($targetPosition <= $totalCount) {
                $targetSeq = $allItems[$targetPosition - 1]->seq;
                
                // Shift items down (increment seq)
                Media::where('seq', '>=', $targetSeq)->increment('seq');
                
                $newSeq = $targetSeq;
            } else {
                // Inserting at last position
                $newSeq = $totalCount > 0 ? $allItems->last()->seq + 1 : 1;
            }

            Media::create([
                'name' => $validated['name'],
                'url' => $imagePath,
                'seq' => $newSeq,
            ]);
        });

        return back()->with('success', 'Slider berhasil ditambahkan');
    }

    public function update(Request $request, Media $slider)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'seq' => 'required|integer|min:1',
        ]);

        // Handle reordering with shift-based algorithm
        if (isset($validated['seq'])) {
            $targetPosition = $validated['seq'];

            DB::transaction(function () use ($targetPosition, $slider) {
                // Load all items ordered by seq, id
                $allItems = Media::orderBy('seq')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                // Find current position
                $currentIndex = $allItems->search(fn ($item) => $item->id == $slider->id);
                if ($currentIndex === false) {
                    return;
                }

                $currentPosition = $currentIndex + 1;
                $totalCount = $allItems->count();

                // Validate target position
                if ($targetPosition > $totalCount) {
                    $targetPosition = $totalCount;
                }

                // No-op if position unchanged
                if ($targetPosition === $currentPosition) {
                    return;
                }

                $movingItem = $allItems[$currentIndex];
                $targetItem = $allItems[$targetPosition - 1];

                if ($targetPosition > $currentPosition) {
                    // Moving DOWN
                    $targetSeq = $targetItem->seq;

                    if ($movingItem->seq == $targetSeq) {
                        $movingItem->seq = $targetSeq + 1;
                    } else {
                        if ($movingItem->seq + 1 <= $targetSeq) {
                            Media::whereBetween('seq', [$movingItem->seq + 1, $targetSeq])
                                ->decrement('seq');
                        }
                        $movingItem->seq = $targetSeq;
                    }
                } else {
                    // Moving UP
                    $targetSeq = $targetItem->seq;

                    if ($movingItem->seq == $targetSeq) {
                        $movingItem->seq = $targetSeq - 1;
                    } else {
                        if ($targetSeq <= $movingItem->seq - 1) {
                            Media::whereBetween('seq', [$targetSeq, $movingItem->seq - 1])
                                ->increment('seq');
                        }
                        $movingItem->seq = $targetSeq;
                    }
                }

                $movingItem->save();
            });

            unset($validated['seq']);
        }

        $slider->update([
            'name' => $validated['name'],
        ]);

        return back()->with('success', 'Slider berhasil diupdate');
    }

    public function destroy(Media $slider)
    {
        // Delete image file if exists
        if ($slider->url) {
            $filename = basename(parse_url($slider->url, PHP_URL_PATH));
            $filePath = public_path('assets/upload/slider/' . $filename);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $slider->delete();

        return back()->with('success', 'Slider berhasil dihapus');
    }
}
