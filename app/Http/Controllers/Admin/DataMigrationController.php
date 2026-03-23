<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookChapter;
use App\Models\BookContent;
use App\Models\Category;
use App\Models\Topic2;
use App\Models\Topic2Chapter;
use App\Models\Topic2Content;
use App\Models\Topic3;
use App\Models\Topic3Chapter;
use App\Models\Topic3Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DataMigrationController extends Controller
{
    private array $featureStructures = [
        'book' => [
            'parent_model' => Category::class,
            'parent_filter' => ['type' => 'book'],
            'hierarchy' => [
                'level1' => ['model' => Book::class, 'table' => 'book', 'fk' => 'book_category_id'],
                'level2' => ['model' => BookChapter::class, 'table' => 'book_chapters', 'fk' => 'book_id'],
                'level3' => ['model' => BookContent::class, 'table' => 'book_contents', 'fk' => 'book_chapters_id'],
            ],
        ],
        'topic2' => [
            'parent_model' => Category::class,
            'parent_filter' => ['type' => 'topic2'],
            'hierarchy' => [
                'level1' => ['model' => Topic2::class, 'table' => 'topics2', 'fk' => 'book_category_id'],
                'level2' => ['model' => Topic2Chapter::class, 'table' => 'topics2_chapters', 'fk' => 'topics2_id'],
                'level3' => ['model' => Topic2Content::class, 'table' => 'topics2_contents', 'fk' => 'topics2_chapters_id'],
            ],
        ],
        'topic3' => [
            'parent_model' => Category::class,
            'parent_filter' => ['type' => 'topic3'],
            'hierarchy' => [
                'level1' => ['model' => Topic3::class, 'table' => 'topics3', 'fk' => 'book_category_id'],
                'level2' => ['model' => Topic3Chapter::class, 'table' => 'topics3_chapters', 'fk' => 'topics3_id'],
                'level3' => ['model' => Topic3Content::class, 'table' => 'topics3_contents', 'fk' => 'topics3_chapters_id'],
            ],
        ],
    ];

    private array $columnMappings = [
        'book->topic2' => [
            'book' => ['id', 'book_category_id', 'synopsis', 'title', 'seq', 'date'],
            'book_chapters' => ['id', 'parent_id', 'title', 'description', 'seq', 'have_child', 'video_category_id'],
            'book_contents' => ['id', 'content', 'page'],
        ],
        'book->topic3' => [
            'book' => ['id', 'book_category_id', 'synopsis', 'title', 'seq', 'date'],
            'book_chapters' => ['id', 'parent_id', 'title', 'description', 'seq', 'have_child', 'video_category_id'],
            'book_contents' => ['id', 'content', 'page'],
        ],
        'topic2->topic3' => [
            'topics2' => ['id', 'book_category_id', 'synopsis', 'title', 'seq', 'date'],
            'topics2_chapters' => ['id', 'parent_id', 'title', 'description', 'seq', 'have_child', 'video_category_id'],
            'topics2_contents' => ['id', 'content', 'page'],
        ],
    ];

    public function index()
    {
        return inertia('Admin/DataMigration/Index', [
            'features' => array_keys($this->featureStructures),
        ]);
    }

    public function getParents(Request $request)
    {
        $feature = $request->input('feature');

        if (!isset($this->featureStructures[$feature])) {
            return response()->json(['error' => 'Invalid feature'], 400);
        }

        $parentModel = $this->featureStructures[$feature]['parent_model'];
        $parentFilter = $this->featureStructures[$feature]['parent_filter'];

        $query = $parentModel::query();

        if ($parentFilter) {
            foreach ($parentFilter as $key => $value) {
                $query->where($key, $value);
            }
        }

        $parents = $query->select('id', 'title')->orderBy('seq')->get();

        return response()->json(['parents' => $parents]);
    }

    public function getRelationPreview(Request $request)
    {
        $source = $request->input('source');
        $destination = $request->input('destination');

        if (!isset($this->featureStructures[$source]) || !isset($this->featureStructures[$destination])) {
            return response()->json(['error' => 'Invalid features'], 400);
        }

        $mappingKey = "{$source}->{$destination}";
        
        if (!isset($this->columnMappings[$mappingKey])) {
            return response()->json(['error' => 'No mapping defined for this combination'], 400);
        }

        $sourceStructure = $this->featureStructures[$source]['hierarchy'];
        $destStructure = $this->featureStructures[$destination]['hierarchy'];

        $preview = [
            'source' => $this->buildStructurePreview($sourceStructure),
            'destination' => $this->buildStructurePreview($destStructure),
            'mappings' => $this->columnMappings[$mappingKey],
        ];

        return response()->json($preview);
    }

    private function buildStructurePreview(array $hierarchy): array
    {
        $structure = [];
        
        foreach ($hierarchy as $level => $config) {
            $structure[$level] = [
                'table' => $config['table'],
                'foreign_key' => $config['fk'],
            ];
        }

        return $structure;
    }

    public function previewData(Request $request)
    {
        $validated = $request->validate([
            'source_feature' => 'required|string',
            'destination_feature' => 'required|string',
            'source_parents' => 'required|array',
        ]);

        $source = $validated['source_feature'];
        $dest = $validated['destination_feature'];
        $sourceParents = $validated['source_parents'];

        if (!isset($this->featureStructures[$source]) || !isset($this->featureStructures[$dest])) {
            return response()->json(['error' => 'Invalid features'], 400);
        }

        $mappingKey = "{$source}->{$dest}";
        
        if (!isset($this->columnMappings[$mappingKey])) {
            return response()->json(['error' => 'No mapping defined'], 400);
        }

        $sourceStructure = $this->featureStructures[$source]['hierarchy'];
        $destStructure = $this->featureStructures[$dest]['hierarchy'];
        $mappings = $this->columnMappings[$mappingKey];

        // Get source data
        $level1Model = $sourceStructure['level1']['model'];
        $level1FK = $sourceStructure['level1']['fk'];
        
        $query = $level1Model::query();
        
        if (!in_array('all', $sourceParents)) {
            $query->whereIn($level1FK, $sourceParents);
        }

        $level1Records = $query->limit(2)->get();

        $preview = [];

        foreach ($level1Records as $record) {
            $level1Data = $this->extractData($record, array_values($mappings)[0]);
            
            // Get ALL root chapters (parent_id = null)
            $chaptersRelation = $this->getChaptersRelationName($source);
            $rootChapters = $record->{$chaptersRelation}()->whereNull('parent_id')->get();
            
            $chaptersPreview = [];
            $totalChaptersCount = 0;
            
            foreach ($rootChapters as $chapter) {
                $chapterTree = $this->buildChapterTree($chapter, $mappings, $source);
                $chaptersPreview[] = $chapterTree;
                $totalChaptersCount += $this->countChapters($chapterTree);
            }
            
            $level1Data['chapters'] = $chaptersPreview;
            $level1Data['chapters_count'] = $totalChaptersCount;
            
            $preview[] = [
                'source' => $record->toArray(),
                'destination' => $level1Data,
                'destination_table' => $destStructure['level1']['table'],
            ];
        }

        // Get total counts
        $totalQuery = $level1Model::query();
        if (!in_array('all', $sourceParents)) {
            $totalQuery->whereIn($level1FK, $sourceParents);
        }
        $totalCount = $totalQuery->count();

        return response()->json([
            'preview' => $preview,
            'total_records' => $totalCount,
            'showing' => min(2, count($preview)),
        ]);
    }

    private function buildChapterTree($chapter, $mappings, $source): array
    {
        $chapterData = $this->extractData($chapter, array_values($mappings)[1]);
        
        // Get ALL contents for this chapter
        $contentsRelation = $this->getContentsRelationName($source);
        $contents = $chapter->{$contentsRelation}()->get();
        
        $contentsPreview = [];
        foreach ($contents as $content) {
            $contentData = $this->extractData($content, array_values($mappings)[2]);
            $contentsPreview[] = $contentData;
        }
        
        $chapterData['contents'] = $contentsPreview;
        $chapterData['contents_count'] = count($contentsPreview);
        
        // Get child chapters recursively
        $childChapters = $chapter->children()->get();
        $childrenPreview = [];
        
        foreach ($childChapters as $childChapter) {
            $childrenPreview[] = $this->buildChapterTree($childChapter, $mappings, $source);
        }
        
        $chapterData['children'] = $childrenPreview;
        $chapterData['children_count'] = count($childrenPreview);
        
        return $chapterData;
    }

    private function countChapters($chapterTree): int
    {
        $count = 1; // Count this chapter
        
        if (isset($chapterTree['children']) && is_array($chapterTree['children'])) {
            foreach ($chapterTree['children'] as $child) {
                $count += $this->countChapters($child);
            }
        }
        
        return $count;
    }

    private function getChaptersRelationName(string $feature): string
    {
        return match ($feature) {
            'book' => 'chapters',
            'topic2' => 'topics2Chapters',
            'topic3' => 'topics3Chapters',
            default => 'chapters',
        };
    }

    private function getContentsRelationName(string $feature): string
    {
        return match ($feature) {
            'book' => 'contents',
            'topic2' => 'topics2Contents',
            'topic3' => 'topics3Contents',
            default => 'contents',
        };
    }

    public function migrate(Request $request)
    {
        $validated = $request->validate([
            'source_feature' => 'required|string',
            'destination_feature' => 'required|string',
            'source_parents' => 'required|array',
        ]);

        $source = $validated['source_feature'];
        $dest = $validated['destination_feature'];
        $sourceParents = $validated['source_parents'];

        if (!isset($this->featureStructures[$source]) || !isset($this->featureStructures[$dest])) {
            return response()->json(['error' => 'Invalid features'], 400);
        }

        $mappingKey = "{$source}->{$dest}";
        
        if (!isset($this->columnMappings[$mappingKey])) {
            return response()->json(['error' => 'No mapping defined'], 400);
        }

        try {
            DB::beginTransaction();

            $result = $this->executeMigration($source, $dest, $sourceParents);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Migration completed successfully',
                'migrated_count' => $result['total_count'],
                'details' => $result['details'],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function executeMigration(string $source, string $dest, array $sourceParents): array
    {
        $sourceStructure = $this->featureStructures[$source]['hierarchy'];
        $destStructure = $this->featureStructures[$dest]['hierarchy'];
        $mappingKey = "{$source}->{$dest}";
        $mappings = $this->columnMappings[$mappingKey];

        $idMappings = [
            'category' => [],
            'level1' => [],
            'level2' => [],
            'level3' => [],
        ];

        $counts = [
            'category' => 0,
            'level1' => 0,
            'level2' => 0,
            'level3' => 0,
        ];

        // Get source categories
        $sourceParentModel = $this->featureStructures[$source]['parent_model'];
        $sourceParentFilter = $this->featureStructures[$source]['parent_filter'];
        
        $categoryQuery = $sourceParentModel::query();
        
        if ($sourceParentFilter) {
            foreach ($sourceParentFilter as $key => $value) {
                $categoryQuery->where($key, $value);
            }
        }
        
        if (!in_array('all', $sourceParents)) {
            $categoryQuery->whereIn('id', $sourceParents);
        }
        
        $sourceCategories = $categoryQuery->get();

        // Migrate categories first
        $destParentFilter = $this->featureStructures[$dest]['parent_filter'];
        
        // Get the max seq from destination categories
        $maxSeq = Category::where('type', $destParentFilter['type'])->max('seq') ?? 0;
        
        foreach ($sourceCategories as $category) {
            $maxSeq++; // Increment for each new category
            
            $newCategoryData = [
                'code' => $category->code,
                'title' => $category->title,
                'parent_id' => $category->parent_id,
                'seq' => $maxSeq, // Use incremented seq
                'type' => $destParentFilter['type'], // Set destination type
                'languange' => $category->languange,
                'date' => $category->date,
                'image' => $category->image,
            ];
            
            $newCategory = Category::create($newCategoryData);
            $idMappings['category'][$category->id] = $newCategory->id;
            $counts['category']++;
        }

        // Level 1: Main records (book -> topics2/topics3)
        $level1Model = $sourceStructure['level1']['model'];
        $level1FK = $sourceStructure['level1']['fk'];
        
        $query = $level1Model::query();
        
        if (!in_array('all', $sourceParents)) {
            $query->whereIn($level1FK, $sourceParents);
        }

        $level1Records = $query->get();

        foreach ($level1Records as $record) {
            $level1Data = $this->extractData($record, $mappings[array_key_first($mappings)]);
            
            // Map the category_id to new category
            $level1Data[$destStructure['level1']['fk']] = $idMappings['category'][$record->{$level1FK}] ?? null;
            
            $newRecord = $destStructure['level1']['model']::create($level1Data);
            
            $idMappings['level1'][$record->id] = $newRecord->id;
            $counts['level1']++;

            // Level 2: Chapters
            $this->migrateLevel2($record, $newRecord, $sourceStructure, $destStructure, $mappings, $idMappings, $counts);
        }

        return [
            'total_count' => array_sum($counts),
            'details' => $counts,
        ];
    }

    private function migrateLevel2($parentRecord, $newParentRecord, $sourceStructure, $destStructure, $mappings, &$idMappings, &$counts)
    {
        $chaptersRelation = $this->getChaptersRelationName(
            array_search($sourceStructure, $this->featureStructures)
        );
        
        // Get only root chapters (parent_id = null)
        $rootChapters = $parentRecord->{$chaptersRelation}()->whereNull('parent_id')->get();

        foreach ($rootChapters as $chapter) {
            $this->migrateChapterRecursive($chapter, $newParentRecord->id, null, $sourceStructure, $destStructure, $mappings, $idMappings, $counts);
        }
    }

    private function migrateChapterRecursive($chapter, $parentRecordId, $parentChapterId, $sourceStructure, $destStructure, $mappings, &$idMappings, &$counts)
    {
        // Migrate this chapter
        $chapterData = $this->extractData($chapter, array_values($mappings)[1]);
        $chapterData[$destStructure['level2']['fk']] = $parentRecordId;
        $chapterData['parent_id'] = $parentChapterId;

        $newChapter = $destStructure['level2']['model']::create($chapterData);
        $idMappings['level2'][$chapter->id] = $newChapter->id;
        $counts['level2']++;

        // Migrate contents for this chapter
        $this->migrateLevel3($chapter, $newChapter, $sourceStructure, $destStructure, $mappings, $idMappings, $counts);

        // Migrate child chapters recursively
        $childChapters = $chapter->children()->get();
        foreach ($childChapters as $childChapter) {
            $this->migrateChapterRecursive($childChapter, $parentRecordId, $newChapter->id, $sourceStructure, $destStructure, $mappings, $idMappings, $counts);
        }
    }

    private function migrateLevel3($chapterRecord, $newChapterRecord, $sourceStructure, $destStructure, $mappings, &$idMappings, &$counts)
    {
        $level3Model = $sourceStructure['level3']['model'];
        
        $contentsRelation = $this->getContentsRelationName(
            array_search($sourceStructure, $this->featureStructures)
        );
        
        $level3Records = $chapterRecord->{$contentsRelation}()->get();

        foreach ($level3Records as $content) {
            $contentData = $this->extractData($content, array_values($mappings)[2]);
            $contentData[$destStructure['level3']['fk']] = $newChapterRecord->id;
            
            // For topic3, set category_id to null
            if (isset($contentData['category_id'])) {
                $contentData['category_id'] = null;
            }

            $newContent = $destStructure['level3']['model']::create($contentData);
            $idMappings['level3'][$content->id] = $newContent->id;
            $counts['level3']++;
        }
    }

    private function migrateRecord($sourceRecord, string $destModel, array $columns)
    {
        $data = $this->extractData($sourceRecord, $columns);
        return $destModel::create($data);
    }

    private function extractData($record, array $columns): array
    {
        $data = [];
        
        foreach ($columns as $column) {
            if ($column !== 'id' && isset($record->{$column})) {
                $data[$column] = $record->{$column};
            }
        }

        return $data;
    }
}
