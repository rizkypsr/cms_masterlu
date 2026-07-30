<?php

namespace App\Http\Controllers;

use App\Models\ChatCategory;
use App\Models\ChatCategoryItem;
use App\Support\ChatContentTree;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ChatCategoryController extends Controller
{
    public function index()
    {
        // Whole table in one query, then nested in PHP: depth is unbounded, so
        // there is no fixed `with('children.children...')` chain that would fit.
        $all = ChatCategory::query()->ordered()->withCount('items')->get();

        return Inertia::render('ChatCategory/Index', [
            'categories' => $this->buildTree($all),
        ]);
    }

    /**
     * Nest a flat, already-ordered collection under $parentId.
     *
     * @param  \Illuminate\Support\Collection<int, ChatCategory>  $all
     * @return list<array<string, mixed>>
     */
    private function buildTree($all, ?int $parentId = null): array
    {
        return $all
            ->where('parent_id', $parentId)
            ->map(function (ChatCategory $c) use ($all): array {
                $children = $this->buildTree($all, $c->id);

                // Content on a node that has active children is unreachable —
                // only the deepest node in a branch is served. Resolve those
                // rows up front so the cleanup dialog needs no extra request;
                // the condition is a data anomaly, so this is normally empty.
                $isGroup = collect($children)->contains(fn (array $child): bool => $child['is_active']);
                $stale = $isGroup && $c->items_count > 0
                    ? $this->scopeItemList($c)
                    : [];

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'seq' => $c->seq,
                    'is_active' => $c->is_active,
                    'parent_id' => $c->parent_id,
                    'description' => $c->description,
                    'items_count' => $c->items_count,
                    'stale_items' => $stale,
                    'children' => $children,
                ];
            })
            ->values()
            ->all();
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
            'description' => $validated['description'],
            'seq' => $newSeq,
        ]);

        return back();
    }

    public function update(Request $request, ChatCategory $chatCategory)
    {
        $validated = $this->validateData($request);

        $newParentId = $validated['parent_id'] === null ? null : (int) $validated['parent_id'];

        if ($this->wouldCycle($chatCategory, $newParentId)) {
            return back()->with('error', 'Kategori tidak bisa dipindah ke dalam dirinya sendiri atau sub-kategorinya.');
        }

        // Parent is written first so reorder() ranks the row against the
        // siblings it is actually moving into, not the ones it left.
        $chatCategory->update([
            'name' => $validated['name'],
            'types' => $validated['types'],
            'is_active' => $validated['is_active'],
            'description' => $validated['description'],
            'parent_id' => $newParentId,
        ]);

        $position = $request->integer('seq');

        if ($position) {
            $this->reorder($chatCategory->refresh(), $position);
        }

        return back();
    }

    /**
     * Whether re-parenting $category under $newParentId would detach a branch
     * from the root by making it its own ancestor.
     */
    private function wouldCycle(ChatCategory $category, ?int $newParentId): bool
    {
        if ($newParentId === null) {
            return false;
        }

        if ($newParentId === $category->id) {
            return true;
        }

        // Walk up from the proposed parent: reaching $category means it sits
        // inside its own subtree. Cheaper than collecting every descendant.
        $ancestorId = $newParentId;
        $guard = 0;

        while ($ancestorId !== null && $guard++ < 100) {
            if ($ancestorId === $category->id) {
                return true;
            }

            $ancestorId = ChatCategory::where('id', $ancestorId)->value('parent_id');
            $ancestorId = $ancestorId === null ? null : (int) $ancestorId;
        }

        return false;
    }

    public function destroy(ChatCategory $chatCategory)
    {
        // FK ON DELETE SET NULL promotes any children to top-level leaves.
        $chatCategory->delete();

        return back();
    }

    /**
     * Drop content left attached to a category that has since gained children.
     *
     * Such rows are unreachable: only the deepest node in a branch is served,
     * so the scope editor refuses to open for a group and there is otherwise no
     * way to clear them.
     */
    public function destroyStaleItems(ChatCategory $chatCategory)
    {
        // A leaf's items are live content — never delete those from here.
        if ($chatCategory->isLeaf()) {
            return back()->with('error', 'Kategori ini tidak punya sub-kategori, jadi kontennya masih terpakai. Kelola lewat halaman Kelola Konten.');
        }

        $deleted = $chatCategory->items()->delete();

        return back()->with('success', "{$deleted} konten nyangkut dihapus dari \"{$chatCategory->name}\".");
    }

    /**
     * Scope editor page for one leaf category.
     */
    public function scope(ChatCategory $chatCategory)
    {
        abort_unless($chatCategory->isLeaf(), 403, 'Kategori grup (header) tidak bisa punya konten. Pilih kategori daun.');

        return Inertia::render('ChatCategory/Scope', [
            'category' => [
                'id' => $chatCategory->id,
                'name' => $chatCategory->name,
                'is_active' => $chatCategory->is_active,
            ],
            'domains' => self::domainOptions(),
            'items' => $this->scopeItemList($chatCategory),
        ]);
    }

    /**
     * Saved scope items for pre-checking the tree (AJAX).
     */
    public function scopeItems(ChatCategory $chatCategory): JsonResponse
    {
        return response()->json(['items' => $this->scopeItemList($chatCategory)]);
    }

    /**
     * Lazy-load children of a content node (AJAX). No node = domain roots.
     */
    public function treeChildren(Request $request, string $domain): JsonResponse
    {
        abort_unless(in_array($domain, ChatContentTree::domains(), true), 404);

        $tree = new ChatContentTree;

        $level = $request->string('level')->value();
        $nodeId = $request->integer('node_id');

        if ($level === '' || $nodeId === 0) {
            return response()->json(['nodes' => $tree->roots($domain)]);
        }

        abort_unless(ChatContentTree::isValidPair($domain, $level), 422, 'Pasangan domain/level tidak valid.');

        return response()->json(['nodes' => $tree->children($domain, $level, $nodeId)]);
    }

    /**
     * Free-text search across every level of a domain tree (AJAX).
     */
    public function treeSearch(Request $request, string $domain): JsonResponse
    {
        abort_unless(in_array($domain, ChatContentTree::domains(), true), 404);

        $term = trim($request->string('q')->value());
        if (mb_strlen($term) < 2) {
            return response()->json(['items' => []]);
        }

        return response()->json(['items' => (new ChatContentTree)->search($domain, $term)]);
    }

    /**
     * Toggle a single scope item on/off (idempotent, honours UNIQUE).
     */
    public function toggleScope(Request $request, ChatCategory $chatCategory): JsonResponse
    {
        abort_unless($chatCategory->isLeaf(), 403, 'Kategori grup tidak bisa punya konten.');

        $validated = $request->validate([
            'domain' => 'required|string',
            'level' => 'required|string',
            'node_id' => 'required|integer',
            'checked' => 'required|boolean',
        ]);

        $domain = $validated['domain'];
        $level = $validated['level'];
        $nodeId = (int) $validated['node_id'];

        abort_unless(ChatContentTree::isValidPair($domain, $level), 422, 'Pasangan domain/level tidak valid.');

        $tree = new ChatContentTree;

        if ($validated['checked']) {
            abort_unless($tree->nodeExists($domain, $level, $nodeId), 422, 'Node tidak ditemukan.');

            ChatCategoryItem::firstOrCreate([
                'category_id' => $chatCategory->id,
                'domain' => $domain,
                'level' => $level,
                'node_id' => $nodeId,
            ]);
        } else {
            ChatCategoryItem::where([
                'category_id' => $chatCategory->id,
                'domain' => $domain,
                'level' => $level,
                'node_id' => $nodeId,
            ])->delete();
        }

        return response()->json(['ok' => true, 'checked' => $validated['checked']]);
    }

    /**
     * @return list<array{domain: string, level: string, node_id: int, label: string, path: list<string>, path_nodes: list<array{domain: string, level: string, node_id: int}>, missing: bool}>
     */
    private function scopeItemList(ChatCategory $chatCategory): array
    {
        $tree = new ChatContentTree;

        return $chatCategory->items()
            ->orderBy('domain')
            ->orderBy('level')
            ->get(['domain', 'level', 'node_id'])
            ->map(function (ChatCategoryItem $item) use ($tree): array {
                $ancestors = ChatContentTree::isValidPair($item->domain, $item->level)
                    ? $tree->ancestors($item->domain, $item->level, $item->node_id)
                    : [];

                return [
                    'domain' => $item->domain,
                    'level' => $item->level,
                    'node_id' => $item->node_id,
                    'label' => $ancestors === [] ? "#{$item->node_id}" : end($ancestors)['label'],
                    'path' => array_map(fn (array $a): string => $a['label'], $ancestors),
                    'path_nodes' => array_map(fn (array $a): array => [
                        'domain' => $a['domain'],
                        'level' => $a['level'],
                        'node_id' => $a['node_id'],
                    ], $ancestors),
                    'missing' => $ancestors === [],
                ];
            })
            ->all();
    }

    /**
     * Tab labels for the scope editor.
     *
     * @return list<array{value: string, label: string}>
     */
    private static function domainOptions(): array
    {
        return [
            ['value' => 'book', 'label' => 'Buku'],
            ['value' => 'video', 'label' => 'Video'],
            ['value' => 'audio', 'label' => 'Audio'],
            ['value' => 'topics', 'label' => 'Topik 1'],
            ['value' => 'topics2', 'label' => 'Topik 2'],
            ['value' => 'topics3', 'label' => 'Topik 3'],
        ];
    }

    /**
     * @return array{name: string, types: string, is_active: bool, parent_id: int|null, description: string|null}
     */
    private function validateData(Request $request): array
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'parent_id' => 'nullable|exists:chat_category,id',
            'seq' => 'nullable|integer',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $description = $request->string('description')->trim()->value();

        return [
            // `types` is a dead legacy column; scope now lives in chat_category_item.
            'name' => $request->string('name')->trim()->value(),
            'types' => '',
            'is_active' => $request->boolean('is_active'),
            'parent_id' => $request->input('parent_id'),
            'description' => $description === '' ? null : $description,
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
