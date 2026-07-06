<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Resolves the lazy content tree that backs the chat-category scope editor.
 *
 * Each node is shaped as:
 *   array{domain: string, level: string, node_id: int, label: string, has_children: bool}
 *
 * The (domain, level) => target-table catalog below is authoritative; pairs
 * outside it are rejected. node_id always points at the id column of the mapped
 * table (book.category additionally scoped to category.type = 'book').
 */
class ChatContentTree
{
    /**
     * (domain, level) => [table, extraColumn|null, extraValue|null].
     *
     * @var array<string, array<string, array{0: string, 1: string|null, 2: string|null}>>
     */
    private const CATALOG = [
        'book' => [
            'category' => ['category', 'type', 'book'],
            'book' => ['book', null, null],
            'chapter' => ['book_chapters', null, null],
            'content' => ['book_contents', null, null],
        ],
        'video' => [
            'category' => ['video_category', null, null],
            'video' => ['video', null, null],
            'subtitle' => ['video_subtitle', null, null],
        ],
        'topics' => [
            'topic_category' => ['topics_category', null, null],
            'subtitle' => ['audio_subtitle', null, null],
        ],
        'topics2' => [
            'root' => ['topics2', null, null],
            'chapter' => ['topics2_chapters', null, null],
            'content' => ['topics2_contents', null, null],
        ],
        'topics3' => [
            'root' => ['topics3', null, null],
            'category' => ['topics3_content_category', null, null],
            'chapter' => ['topics3_chapters', null, null],
            'content' => ['topics3_contents', null, null],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function domains(): array
    {
        return array_keys(self::CATALOG);
    }

    public static function isValidPair(string $domain, string $level): bool
    {
        return isset(self::CATALOG[$domain][$level]);
    }

    /**
     * Verify node_id actually exists in the mapped target table.
     */
    public function nodeExists(string $domain, string $level, int $nodeId): bool
    {
        if (! self::isValidPair($domain, $level)) {
            return false;
        }

        [$table, $col, $val] = self::CATALOG[$domain][$level];

        return DB::table($table)
            ->where('id', $nodeId)
            ->when($col !== null, fn ($q) => $q->where($col, $val))
            ->exists();
    }

    /**
     * Root nodes for a domain tab.
     *
     * @return list<array{domain: string, level: string, node_id: int, label: string, has_children: bool}>
     */
    public function roots(string $domain): array
    {
        return match ($domain) {
            'book' => $this->bookRoots(),
            'video' => $this->videoRoots(),
            'topics' => $this->topicsRoots(),
            'topics2' => $this->topics2Roots(),
            'topics3' => $this->topics3Roots(),
            default => [],
        };
    }

    /**
     * Children of a node, given its (domain, level) and node_id.
     *
     * @return list<array{domain: string, level: string, node_id: int, label: string, has_children: bool}>
     */
    public function children(string $domain, string $level, int $nodeId): array
    {
        return match ("{$domain}.{$level}") {
            'book.category' => $this->bookOfCategory($nodeId),
            'book.book' => $this->chaptersOfBook($nodeId),
            'book.chapter' => $this->bookChapterChildren($nodeId),
            'video.category' => $this->videoCategoryChildren($nodeId),
            'video.video' => $this->videoChildren($nodeId),
            'topics.topic_category' => $this->topicCategoryChildren($nodeId),
            'topics2.root' => $this->topics2Chapters($nodeId, null),
            'topics2.chapter' => $this->topics2ChapterChildren($nodeId),
            'topics3.root' => $this->topics3Chapters($nodeId, null),
            'topics3.category' => $this->topics3ContentsByCategory($nodeId),
            'topics3.chapter' => $this->topics3ChapterChildren($nodeId),
            default => [],
        };
    }

    // ---- BOOK ----

    private function bookRoots(): array
    {
        $rows = DB::table('category')->where('type', 'book')->orderBy('seq')->orderBy('id')->get(['id', 'title']);
        $has = $this->hasChildrenSet('book', 'book_category_id', $rows->pluck('id')->all());

        return $rows->map(fn ($r) => $this->node('book', 'category', $r->id, $r->title, isset($has[$r->id])))->all();
    }

    private function bookOfCategory(int $categoryId): array
    {
        $rows = DB::table('book')->where('book_category_id', $categoryId)->orderBy('seq')->orderBy('id')->get(['id', 'title']);
        $has = $this->hasChildrenSet('book_chapters', 'book_id', $rows->pluck('id')->all());

        return $rows->map(fn ($r) => $this->node('book', 'book', $r->id, $r->title, isset($has[$r->id])))->all();
    }

    private function chaptersOfBook(int $bookId): array
    {
        $rows = DB::table('book_chapters')->where('book_id', $bookId)->whereNull('parent_id')->orderBy('seq')->orderBy('id')->get(['id', 'title']);

        return $this->mapBookChapters($rows);
    }

    private function bookChapterChildren(int $chapterId): array
    {
        $subChapters = DB::table('book_chapters')->where('parent_id', $chapterId)->orderBy('seq')->orderBy('id')->get(['id', 'title']);
        $contents = DB::table('book_contents')->where('book_chapters_id', $chapterId)->orderBy('page')->orderBy('id')->get(['id', 'page', 'content']);

        return array_merge(
            $this->mapBookChapters($subChapters),
            $contents->map(fn ($r) => $this->node('book', 'content', $r->id, $this->pageLabel($r->page, $r->content), false))->all(),
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function mapBookChapters($rows): array
    {
        $ids = $rows->pluck('id')->all();
        $hasSub = $this->hasChildrenSet('book_chapters', 'parent_id', $ids);
        $hasContent = $this->hasChildrenSet('book_contents', 'book_chapters_id', $ids);

        return $rows->map(fn ($r) => $this->node('book', 'chapter', $r->id, $r->title, isset($hasSub[$r->id]) || isset($hasContent[$r->id])))->all();
    }

    // ---- VIDEO ----

    private function videoRoots(): array
    {
        $rows = DB::table('video_category')->whereNull('parent_id')->orderBy('seq')->orderBy('id')->get(['id', 'title']);

        return $this->mapVideoCategories($rows);
    }

    private function videoCategoryChildren(int $categoryId): array
    {
        $subCats = DB::table('video_category')->where('parent_id', $categoryId)->orderBy('seq')->orderBy('id')->get(['id', 'title']);
        $videos = DB::table('video')->where('video_category_id', $categoryId)->whereNull('parent_id')->orderBy('seq')->orderBy('id')->get(['id', 'title']);

        return array_merge($this->mapVideoCategories($subCats), $this->mapVideos($videos));
    }

    private function videoChildren(int $videoId): array
    {
        $children = DB::table('video')->where('parent_id', $videoId)->orderBy('seq')->orderBy('id')->get(['id', 'title']);
        $subtitles = DB::table('video_subtitle')->where('video_id', $videoId)->orderBy('timestamp')->orderBy('id')->get(['id', 'description']);

        return array_merge(
            $this->mapVideos($children),
            $subtitles->map(fn ($r) => $this->node('video', 'subtitle', $r->id, $this->snippet($r->description), false))->all(),
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function mapVideoCategories($rows): array
    {
        $ids = $rows->pluck('id')->all();
        $hasSub = $this->hasChildrenSet('video_category', 'parent_id', $ids);
        $hasVideo = $this->hasChildrenSet('video', 'video_category_id', $ids);

        return $rows->map(fn ($r) => $this->node('video', 'category', $r->id, $r->title, isset($hasSub[$r->id]) || isset($hasVideo[$r->id])))->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function mapVideos($rows): array
    {
        $ids = $rows->pluck('id')->all();
        $hasChild = $this->hasChildrenSet('video', 'parent_id', $ids);
        $hasSub = $this->hasChildrenSet('video_subtitle', 'video_id', $ids);

        return $rows->map(fn ($r) => $this->node('video', 'video', $r->id, $r->title, isset($hasChild[$r->id]) || isset($hasSub[$r->id])))->all();
    }

    // ---- TOPICS (topic1) ----

    private function topicsRoots(): array
    {
        $rows = DB::table('topics_category')->whereNull('parent_id')->orderBy('seq')->orderBy('id')->get(['id', 'title']);

        return $this->mapTopicCategories($rows);
    }

    private function topicCategoryChildren(int $categoryId): array
    {
        $subCats = DB::table('topics_category')->where('parent_id', $categoryId)->orderBy('seq')->orderBy('id')->get(['id', 'title']);

        $subtitles = DB::table('topics_content')
            ->join('audio_subtitle', 'audio_subtitle.id', '=', 'topics_content.id_header')
            ->where('topics_content.topics_category_id', $categoryId)
            ->where('topics_content.type', 'audio')
            ->orderBy('topics_content.seq')
            ->orderBy('topics_content.id')
            ->get(['audio_subtitle.id as id', 'audio_subtitle.title as title', 'audio_subtitle.description as description']);

        return array_merge(
            $this->mapTopicCategories($subCats),
            $subtitles->map(fn ($r) => $this->node('topics', 'subtitle', $r->id, $r->title ?: $this->snippet($r->description), false))->all(),
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function mapTopicCategories($rows): array
    {
        $ids = $rows->pluck('id')->all();
        $hasSub = $this->hasChildrenSet('topics_category', 'parent_id', $ids);
        $hasContent = $this->hasChildrenSet('topics_content', 'topics_category_id', $ids, 'type', 'audio');

        return $rows->map(fn ($r) => $this->node('topics', 'topic_category', $r->id, $r->title, isset($hasSub[$r->id]) || isset($hasContent[$r->id])))->all();
    }

    // ---- TOPICS2 ----

    private function topics2Roots(): array
    {
        $rows = DB::table('topics2')->orderBy('seq')->orderBy('id')->get(['id', 'title']);
        $has = $this->hasChildrenSet('topics2_chapters', 'topics2_id', $rows->pluck('id')->all());

        return $rows->map(fn ($r) => $this->node('topics2', 'root', $r->id, $r->title, isset($has[$r->id])))->all();
    }

    private function topics2Chapters(int $topics2Id, ?int $unused): array
    {
        $rows = DB::table('topics2_chapters')->where('topics2_id', $topics2Id)->whereNull('parent_id')->orderBy('seq')->orderBy('id')->get(['id', 'title']);

        return $this->mapTopics2Chapters($rows);
    }

    private function topics2ChapterChildren(int $chapterId): array
    {
        $subChapters = DB::table('topics2_chapters')->where('parent_id', $chapterId)->orderBy('seq')->orderBy('id')->get(['id', 'title']);
        $contents = DB::table('topics2_contents')->where('topics2_chapters_id', $chapterId)->orderBy('page')->orderBy('id')->get(['id', 'page', 'content']);

        return array_merge(
            $this->mapTopics2Chapters($subChapters),
            $contents->map(fn ($r) => $this->node('topics2', 'content', $r->id, $this->pageLabel($r->page, $r->content), false))->all(),
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function mapTopics2Chapters($rows): array
    {
        $ids = $rows->pluck('id')->all();
        $hasSub = $this->hasChildrenSet('topics2_chapters', 'parent_id', $ids);
        $hasContent = $this->hasChildrenSet('topics2_contents', 'topics2_chapters_id', $ids);

        return $rows->map(fn ($r) => $this->node('topics2', 'chapter', $r->id, $r->title, isset($hasSub[$r->id]) || isset($hasContent[$r->id])))->all();
    }

    // ---- TOPICS3 ----

    private function topics3Roots(): array
    {
        $roots = DB::table('topics3')->orderBy('seq')->orderBy('id')->get(['id', 'title']);
        $hasChapters = $this->hasChildrenSet('topics3_chapters', 'topics3_id', $roots->pluck('id')->all());

        $categories = DB::table('topics3_content_category')->orderBy('seq')->orderBy('id')->get(['id', 'name']);
        $hasContent = $this->hasChildrenSet('topics3_contents', 'category_id', $categories->pluck('id')->all());

        return array_merge(
            $roots->map(fn ($r) => $this->node('topics3', 'root', $r->id, $r->title, isset($hasChapters[$r->id])))->all(),
            $categories->map(fn ($r) => $this->node('topics3', 'category', $r->id, '[Kategori] '.$r->name, isset($hasContent[$r->id])))->all(),
        );
    }

    private function topics3Chapters(int $topics3Id, ?int $unused): array
    {
        $rows = DB::table('topics3_chapters')->where('topics3_id', $topics3Id)->whereNull('parent_id')->orderBy('seq')->orderBy('id')->get(['id', 'title']);

        return $this->mapTopics3Chapters($rows);
    }

    private function topics3ChapterChildren(int $chapterId): array
    {
        $subChapters = DB::table('topics3_chapters')->where('parent_id', $chapterId)->orderBy('seq')->orderBy('id')->get(['id', 'title']);
        $contents = DB::table('topics3_contents')->where('topics3_chapters_id', $chapterId)->orderBy('page')->orderBy('id')->get(['id', 'page', 'content']);

        return array_merge(
            $this->mapTopics3Chapters($subChapters),
            $contents->map(fn ($r) => $this->node('topics3', 'content', $r->id, $this->pageLabel($r->page, $r->content), false))->all(),
        );
    }

    private function topics3ContentsByCategory(int $categoryId): array
    {
        $rows = DB::table('topics3_contents')->where('category_id', $categoryId)->orderBy('page')->orderBy('id')->get(['id', 'page', 'content']);

        return $rows->map(fn ($r) => $this->node('topics3', 'content', $r->id, $this->pageLabel($r->page, $r->content), false))->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function mapTopics3Chapters($rows): array
    {
        $ids = $rows->pluck('id')->all();
        $hasSub = $this->hasChildrenSet('topics3_chapters', 'parent_id', $ids);
        $hasContent = $this->hasChildrenSet('topics3_contents', 'topics3_chapters_id', $ids);

        return $rows->map(fn ($r) => $this->node('topics3', 'chapter', $r->id, $r->title, isset($hasSub[$r->id]) || isset($hasContent[$r->id])))->all();
    }

    // ---- ancestor chain (reverse walk, for the "selected items" panel + auto-expand) ----

    /**
     * Ancestor node descriptors from root down to the node itself (inclusive).
     * Each entry is tree-matching ({domain, level, node_id, label}) so the
     * frontend can expand straight to it. Empty if the node no longer exists.
     *
     * @return list<array{domain: string, level: string, node_id: int, label: string}>
     */
    public function ancestors(string $domain, string $level, int $nodeId): array
    {
        return match ("{$domain}.{$level}") {
            'book.category' => $this->refChain('category', 'parent_id', $nodeId, 'book', 'category'),
            'book.book' => $this->bookRefs($nodeId),
            'book.chapter' => $this->bookChapterRefs($nodeId),
            'book.content' => $this->bookContentRefs($nodeId),
            'video.category' => $this->refChain('video_category', 'parent_id', $nodeId, 'video', 'category'),
            'video.video' => $this->videoRefs($nodeId),
            'video.subtitle' => $this->videoSubtitleRefs($nodeId),
            'topics.topic_category' => $this->refChain('topics_category', 'parent_id', $nodeId, 'topics', 'topic_category'),
            'topics.subtitle' => $this->topicSubtitleRefs($nodeId),
            'topics2.root' => $this->singleRef('topics2', $nodeId, 'topics2', 'root'),
            'topics2.chapter' => $this->chapterRefs('topics2', 'topics2_id', $nodeId),
            'topics2.content' => $this->contentRefs('topics2', 'topics2_id', 'topics2_chapters_id', $nodeId),
            'topics3.root' => $this->singleRef('topics3', $nodeId, 'topics3', 'root'),
            'topics3.category' => $this->topics3CategoryRef($nodeId),
            'topics3.chapter' => $this->chapterRefs('topics3', 'topics3_id', $nodeId),
            'topics3.content' => $this->contentRefs('topics3', 'topics3_id', 'topics3_chapters_id', $nodeId),
            default => [],
        };
    }

    /**
     * Ordered labels root→node (derived from ancestors()).
     *
     * @return list<string>
     */
    public function pathLabels(string $domain, string $level, int $nodeId): array
    {
        return array_map(fn (array $r): string => $r['label'], $this->ancestors($domain, $level, $nodeId));
    }

    /**
     * @return array{domain: string, level: string, node_id: int, label: string}
     */
    private function ref(string $domain, string $level, int $nodeId, ?string $label): array
    {
        return [
            'domain' => $domain,
            'level' => $level,
            'node_id' => $nodeId,
            'label' => $label !== null && trim($label) !== '' ? $label : '(tanpa judul)',
        ];
    }

    /**
     * Walk a self-referential title table from $id up to root, returned root→node.
     *
     * @return list<array{domain: string, level: string, node_id: int, label: string}>
     */
    private function refChain(string $table, string $parentCol, ?int $id, string $domain, string $level): array
    {
        $chain = [];
        $current = $id;

        while ($current !== null) {
            $row = DB::table($table)->where('id', $current)->first(['id', 'title', $parentCol]);
            if ($row === null) {
                break;
            }
            array_unshift($chain, $this->ref($domain, $level, (int) $row->id, $row->title));
            $current = $row->{$parentCol} !== null ? (int) $row->{$parentCol} : null;
        }

        return $chain;
    }

    /**
     * @return list<array{domain: string, level: string, node_id: int, label: string}>
     */
    private function singleRef(string $table, ?int $id, string $domain, string $level): array
    {
        if ($id === null) {
            return [];
        }

        $row = DB::table($table)->where('id', $id)->first(['title']);

        return $row === null ? [] : [$this->ref($domain, $level, $id, $row->title)];
    }

    /**
     * @return list<array{domain: string, level: string, node_id: int, label: string}>
     */
    private function bookRefs(int $bookId): array
    {
        $book = DB::table('book')->where('id', $bookId)->first(['title', 'book_category_id']);
        if ($book === null) {
            return [];
        }

        $category = $this->singleRef('category', $book->book_category_id !== null ? (int) $book->book_category_id : null, 'book', 'category');

        return array_merge($category, [$this->ref('book', 'book', $bookId, $book->title)]);
    }

    /**
     * @return list<array{domain: string, level: string, node_id: int, label: string}>
     */
    private function bookChapterRefs(int $chapterId): array
    {
        $chapters = $this->refChain('book_chapters', 'parent_id', $chapterId, 'book', 'chapter');
        if ($chapters === []) {
            return [];
        }

        return array_merge($this->topChapterBookRefs($chapterId), $chapters);
    }

    /**
     * @return list<array{domain: string, level: string, node_id: int, label: string}>
     */
    private function bookContentRefs(int $contentId): array
    {
        $content = DB::table('book_contents')->where('id', $contentId)->first(['book_chapters_id', 'page', 'content']);
        if ($content === null) {
            return [];
        }

        $chapter = $content->book_chapters_id !== null ? $this->bookChapterRefs((int) $content->book_chapters_id) : [];

        return array_merge($chapter, [$this->ref('book', 'content', $contentId, $this->pageLabel($content->page, $content->content))]);
    }

    /**
     * Climb to the top chapter, then resolve its owning book + category.
     *
     * @return list<array{domain: string, level: string, node_id: int, label: string}>
     */
    private function topChapterBookRefs(int $chapterId): array
    {
        $bookId = $this->climbToOwner('book_chapters', 'book_id', $chapterId);

        return $bookId === null ? [] : $this->bookRefs($bookId);
    }

    /**
     * @return list<array{domain: string, level: string, node_id: int, label: string}>
     */
    private function videoRefs(int $videoId): array
    {
        $videoChain = $this->refChain('video', 'parent_id', $videoId, 'video', 'video');
        if ($videoChain === []) {
            return [];
        }

        // Child videos inherit their category from the top-most video in the
        // parent chain (their own video_category_id is usually blank), so read
        // it from there rather than from the leaf.
        $topVideoId = $videoChain[0]['node_id'];
        $top = DB::table('video')->where('id', $topVideoId)->first(['video_category_id']);
        $categoryId = $top !== null && $top->video_category_id ? (int) $top->video_category_id : null;

        $catChain = $this->refChain('video_category', 'parent_id', $categoryId, 'video', 'category');

        return array_merge($catChain, $videoChain);
    }

    /**
     * @return list<array{domain: string, level: string, node_id: int, label: string}>
     */
    private function videoSubtitleRefs(int $subtitleId): array
    {
        $sub = DB::table('video_subtitle')->where('id', $subtitleId)->first(['video_id', 'description']);
        if ($sub === null) {
            return [];
        }

        $videos = $sub->video_id ? $this->videoRefs((int) $sub->video_id) : [];

        return array_merge($videos, [$this->ref('video', 'subtitle', $subtitleId, $this->snippet($sub->description))]);
    }

    /**
     * @return list<array{domain: string, level: string, node_id: int, label: string}>
     */
    private function topicSubtitleRefs(int $subtitleId): array
    {
        $sub = DB::table('audio_subtitle')->where('id', $subtitleId)->first(['title', 'description']);
        if ($sub === null) {
            return [];
        }

        $label = $sub->title ?: $this->snippet($sub->description);

        $bridge = DB::table('topics_content')
            ->where('id_header', $subtitleId)
            ->where('type', 'audio')
            ->first(['topics_category_id']);

        $catChain = $bridge ? $this->refChain('topics_category', 'parent_id', (int) $bridge->topics_category_id, 'topics', 'topic_category') : [];

        return array_merge($catChain, [$this->ref('topics', 'subtitle', $subtitleId, $label)]);
    }

    /**
     * @return list<array{domain: string, level: string, node_id: int, label: string}>
     */
    private function chapterRefs(string $rootTable, string $rootFk, int $chapterId): array
    {
        $chapterTable = $rootTable.'_chapters';
        $chapters = $this->refChain($chapterTable, 'parent_id', $chapterId, $rootTable, 'chapter');
        if ($chapters === []) {
            return [];
        }

        $rootId = $this->climbToOwner($chapterTable, $rootFk, $chapterId);
        $root = $this->singleRef($rootTable, $rootId, $rootTable, 'root');

        return array_merge($root, $chapters);
    }

    /**
     * @return list<array{domain: string, level: string, node_id: int, label: string}>
     */
    private function contentRefs(string $rootTable, string $rootFk, string $chapterFk, int $contentId): array
    {
        $contentsTable = $rootTable.'_contents';
        $content = DB::table($contentsTable)->where('id', $contentId)->first([$chapterFk, 'page', 'content']);
        if ($content === null) {
            return [];
        }

        $chapter = $content->{$chapterFk} !== null ? $this->chapterRefs($rootTable, $rootFk, (int) $content->{$chapterFk}) : [];

        return array_merge($chapter, [$this->ref($rootTable, 'content', $contentId, $this->pageLabel($content->page, $content->content))]);
    }

    /**
     * @return list<array{domain: string, level: string, node_id: int, label: string}>
     */
    private function topics3CategoryRef(int $categoryId): array
    {
        $row = DB::table('topics3_content_category')->where('id', $categoryId)->first(['name']);

        return $row === null ? [] : [$this->ref('topics3', 'category', $categoryId, '[Kategori] '.($row->name ?: '(tanpa nama)'))];
    }

    /**
     * Climb a self-referential chapter table to the top row, returning its owner fk.
     */
    private function climbToOwner(string $chapterTable, string $ownerFk, int $chapterId): ?int
    {
        $topId = $chapterId;
        $ownerId = null;

        while (true) {
            $row = DB::table($chapterTable)->where('id', $topId)->first(['parent_id', $ownerFk]);
            if ($row === null) {
                break;
            }
            $ownerId = $row->{$ownerFk} !== null ? (int) $row->{$ownerFk} : null;
            if ($row->parent_id === null) {
                break;
            }
            $topId = (int) $row->parent_id;
        }

        return $ownerId;
    }

    // ---- helpers ----

    /**
     * Batched existence check: which of $parentIds have >=1 row in $table keyed by $fk.
     *
     * @param  list<int>  $parentIds
     * @return array<int, true>
     */
    private function hasChildrenSet(string $table, string $fk, array $parentIds, ?string $extraCol = null, ?string $extraVal = null): array
    {
        if ($parentIds === []) {
            return [];
        }

        return DB::table($table)
            ->whereIn($fk, $parentIds)
            ->when($extraCol !== null, fn ($q) => $q->where($extraCol, $extraVal))
            ->distinct()
            ->pluck($fk)
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * @return array{domain: string, level: string, node_id: int, label: string, has_children: bool}
     */
    private function node(string $domain, string $level, int $nodeId, ?string $label, bool $hasChildren): array
    {
        return [
            'domain' => $domain,
            'level' => $level,
            'node_id' => $nodeId,
            'label' => $label !== null && trim($label) !== '' ? $label : '(tanpa judul)',
            'has_children' => $hasChildren,
        ];
    }

    private function pageLabel(?int $page, ?string $content): string
    {
        if ($page !== null) {
            return 'Hal '.$page;
        }

        return $this->snippet($content);
    }

    private function snippet(?string $value): string
    {
        return Str::limit(trim(strip_tags((string) $value)), 50);
    }
}
