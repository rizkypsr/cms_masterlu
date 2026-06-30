<?php

namespace App\Http\Controllers;

use App\Models\ChatCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ChatCategoryController extends Controller
{
    /**
     * Allowed content families a leaf category may search.
     *
     * @var list<string>
     */
    private const TYPES = ['book', 'audio', 'video', 'topics', 'topics2', 'topics3'];

    public function index()
    {
        $categories = ChatCategory::roots()
            ->ordered()
            ->with(['children' => fn ($query) => $query->ordered()])
            ->get();

        return Inertia::render('ChatCategory/Index', [
            'categories' => $categories,
            'typeOptions' => self::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateData($request);

        $position = $request->integer('seq');

        $siblings = ChatCategory::where('parent_id', $validated['parent_id'])
            ->orderBy('seq')
            ->orderBy('id')
            ->get();

        foreach ($siblings as $index => $sibling) {
            $sibling->update(['seq' => $index + 1]);
        }

        $total = $siblings->count();

        if (! $position || $position > $total) {
            $newSeq = $total + 1;
        } else {
            ChatCategory::where('parent_id', $validated['parent_id'])
                ->where('seq', '>=', $position)
                ->increment('seq');

            $newSeq = $position;
        }

        ChatCategory::create([
            'name' => $validated['name'],
            'types' => $validated['types'],
            'is_active' => $validated['is_active'],
            'parent_id' => $validated['parent_id'],
            'seq' => $newSeq,
        ]);

        return back();
    }

    public function update(Request $request, ChatCategory $chatCategory)
    {
        $validated = $this->validateData($request);

        $position = $request->integer('seq');

        if ($position) {
            $this->reorder($chatCategory, $position);
        }

        $chatCategory->update([
            'name' => $validated['name'],
            'types' => $validated['types'],
            'is_active' => $validated['is_active'],
        ]);

        return back();
    }

    public function destroy(ChatCategory $chatCategory)
    {
        // FK ON DELETE SET NULL promotes any children to top-level leaves.
        $chatCategory->delete();

        return back();
    }

    /**
     * @return array{name: string, types: string, is_active: bool, parent_id: int|null}
     */
    private function validateData(Request $request): array
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'parent_id' => 'nullable|exists:chat_category,id',
            'seq' => 'nullable|integer',
            'is_active' => 'boolean',
            'types' => 'nullable|array',
            'types.*' => 'string|in:'.implode(',', self::TYPES),
        ]);

        return [
            'name' => $request->string('name')->trim()->value(),
            'types' => implode(',', $request->input('types', [])),
            'is_active' => $request->boolean('is_active'),
            'parent_id' => $request->input('parent_id'),
        ];
    }

    private function reorder(ChatCategory $chatCategory, int $targetPosition): void
    {
        DB::transaction(function () use ($chatCategory, $targetPosition) {
            $items = ChatCategory::where('parent_id', $chatCategory->parent_id)
                ->orderBy('seq')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $currentIndex = $items->search(fn ($item) => $item->id === $chatCategory->id);
            if ($currentIndex === false) {
                return;
            }

            $currentPosition = $currentIndex + 1;
            $total = $items->count();

            if ($targetPosition > $total) {
                $targetPosition = $total;
            }

            if ($targetPosition === $currentPosition) {
                return;
            }

            $moving = $items[$currentIndex];

            if ($targetPosition > $currentPosition) {
                $targetSeq = $items[$targetPosition - 1]->seq;

                ChatCategory::where('parent_id', $chatCategory->parent_id)
                    ->whereBetween('seq', [$moving->seq + 1, $targetSeq])
                    ->decrement('seq');
            } else {
                $targetSeq = $items[$targetPosition - 1]->seq;

                ChatCategory::where('parent_id', $chatCategory->parent_id)
                    ->whereBetween('seq', [$targetSeq, $moving->seq - 1])
                    ->increment('seq');
            }

            $moving->seq = $targetSeq;
            $moving->save();
        });
    }
}
