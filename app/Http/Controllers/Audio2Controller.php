<?php

namespace App\Http\Controllers;

use App\Models\Audio2;
use App\Models\Audio2Category;
use App\Models\Audio2Segment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class Audio2Controller extends Controller
{
    /**
     * Header line of one segment: "Shuohua20111014 01:09" (code + mm:ss or hh:mm:ss).
     */
    private const SEGMENT_HEADER = '/^\s*([A-Za-z]+\d{6,}[A-Za-z]?)\s+(\d{1,2}:\d{2}(?::\d{2})?)\s*$/';

    /**
     * Caller marker line: "Penelepon 1." or "Penelepon ke-1." ("ke", dash, dot and case optional).
     */
    private const CALLER_LINE = '/^\s*penelepon\s+(?:ke\s*-?\s*)?(\d+)\s*\.?\s*$/i';

    private const CATEGORY_MESSAGES = [
        'title.required' => 'Nama kategori wajib diisi.',
        'title.unique' => 'Nama kategori sudah dipakai.',
    ];

    public function index(Request $request): Response
    {
        $categories = Audio2Category::query()
            ->withCount('audios')
            ->orderBy('seq')->orderBy('id')
            ->get(['id', 'title', 'seq']);

        $category = $categories->firstWhere('id', (int) $request->query('category')) ?? $categories->first();

        $years = $category
            ? Audio2::query()->where('audio2_category_id', $category->id)
                ->whereNotNull('year')->distinct()->orderByDesc('year')->pluck('year')
            : collect();

        // Episodes without a year (e.g. Lagu) get their own "Tanpa tahun" tab, requested as ?year=none.
        $hasUndated = $category !== null
            && Audio2::query()->where('audio2_category_id', $category->id)->whereNull('year')->exists();

        $year = match (true) {
            $request->query('year') === 'none' && $hasUndated => null,
            $years->contains((int) $request->query('year')) => (int) $request->query('year'),
            default => $years->first(),
        };

        $episodes = $category
            ? Audio2::query()
                ->where('audio2_category_id', $category->id)
                ->when($year, fn ($q) => $q->where('year', $year), fn ($q) => $q->whereNull('year'))
                ->withCount('segments')
                ->orderBy('month')->orderByRaw('aired_on is null')->orderBy('aired_on')->orderBy('seq')->orderBy('id')
                ->get(['id', 'audio2_category_id', 'title', 'url', 'duration', 'year', 'month', 'aired_on', 'seq'])
            : collect();

        return Inertia::render('Audio2/Index', [
            'categories' => $categories,
            'selectedCategoryId' => $category?->id,
            'years' => $years->values(),
            'selectedYear' => $year,
            'hasUndated' => $hasUndated,
            'episodes' => $episodes,
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => 'required|string|max:255|unique:audio2_category,title'], self::CATEGORY_MESSAGES);

        $category = Audio2Category::query()->create([
            'title' => $data['title'],
            'seq' => (int) Audio2Category::query()->max('seq') + 1,
        ]);

        return redirect()->route('audio2.index', ['category' => $category->id]);
    }

    public function updateCategory(Request $request, Audio2Category $category): RedirectResponse
    {
        $data = $request->validate(['title' => 'required|string|max:255|unique:audio2_category,title,'.$category->id], self::CATEGORY_MESSAGES);

        $category->update($data);

        return back();
    }

    /**
     * Refuses while episodes remain, so one click cannot wipe a whole category of transcripts.
     */
    public function destroyCategory(Audio2Category $category): RedirectResponse
    {
        $count = $category->audios()->count();

        if ($count > 0) {
            throw ValidationException::withMessages([
                'category' => "Kategori {$category->title} masih berisi {$count} episode. Pindahkan atau hapus episodenya dulu.",
            ]);
        }

        $category->delete();

        return redirect()->route('audio2.index');
    }

    public function reorderCategories(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:audio2_category,id',
        ]);

        DB::transaction(function () use ($request): void {
            foreach (array_values($request->input('ids')) as $index => $id) {
                Audio2Category::query()->whereKey($id)->update(['seq' => $index + 1]);
            }
        });

        return back();
    }

    public function storeEpisode(Request $request): RedirectResponse
    {
        $data = $this->validateEpisode($request);
        $data['seq'] = (int) Audio2::query()
            ->where('audio2_category_id', $data['audio2_category_id'])
            ->where('year', $data['year'])->where('month', $data['month'])
            ->max('seq') + 1;

        Audio2::query()->create($data);

        return back();
    }

    public function updateEpisode(Request $request, Audio2 $audio2): RedirectResponse
    {
        $audio2->update($this->validateEpisode($request));

        return back();
    }

    public function destroyEpisode(Audio2 $audio2): RedirectResponse
    {
        DB::transaction(function () use ($audio2) {
            Audio2Segment::query()->where('audio2_id', $audio2->id)->delete();
            $audio2->delete();
        });

        return back();
    }

    public function transcript(Audio2 $audio2): Response
    {
        $audio2->load('category:id,title');

        return Inertia::render('Audio2/Transcript', [
            'episode' => $audio2,
            'segments' => $audio2->segments()->get(['id', 'audio2_id', 'caller', 'code', 'timestamp', 'title', 'content', 'seq']),
        ]);
    }

    public function previewTranscript(Request $request): JsonResponse
    {
        $request->validate(['text' => 'required|string', 'start_caller' => 'nullable|integer|min:0']);

        $segments = array_map(fn (array $s) => [
            'caller' => $s['caller'],
            'code' => $s['code'],
            'time' => $s['time'],
            'title' => $s['title'],
            'excerpt' => mb_substr(preg_replace('/\s+/', ' ', $s['body']), 0, 120),
        ], self::parseTranscript($request->input('text'), (int) $request->input('start_caller', 0)));

        return response()->json(['segments' => $segments]);
    }

    public function storeTranscript(Request $request, Audio2 $audio2): RedirectResponse
    {
        $request->validate(['text' => 'required|string']);

        $lastCaller = (int) Audio2Segment::query()
            ->where('audio2_id', $audio2->id)
            ->orderByDesc('seq')
            ->orderByDesc('id')
            ->value('caller');

        $parsed = self::parseTranscript($request->input('text'), $lastCaller);

        if ($parsed === []) {
            throw ValidationException::withMessages([
                'text' => 'Tidak ada segmen terbaca. Setiap segmen harus diawali baris kode + waktu, contoh: Shuohua20111014 01:09',
            ]);
        }

        DB::transaction(function () use ($audio2, $parsed) {
            $seq = (int) Audio2Segment::query()->where('audio2_id', $audio2->id)->max('seq');

            foreach ($parsed as $segment) {
                Audio2Segment::query()->create([
                    'audio2_id' => $audio2->id,
                    'caller' => $segment['caller'],
                    'code' => $segment['code'],
                    'timestamp' => $segment['timestamp'],
                    'title' => $segment['title'],
                    'content' => $segment['body'] === '' ? '' : Topic3Controller::plainTextToHtml($segment['body']),
                    'seq' => ++$seq,
                ]);
            }
        });

        return back();
    }

    public function storeSegment(Request $request, Audio2 $audio2): RedirectResponse
    {
        $data = $this->validateSegment($request);
        $data['audio2_id'] = $audio2->id;
        $data['seq'] = (int) Audio2Segment::query()->where('audio2_id', $audio2->id)->max('seq') + 1;

        $segment = Audio2Segment::query()->create($data);
        $this->placeAtEndOfGroup($segment);

        return back();
    }

    public function updateSegment(Request $request, Audio2Segment $segment): RedirectResponse
    {
        $data = $this->validateSegment($request);
        $callerChanged = (int) $data['caller'] !== $segment->caller;

        $segment->update($data);

        if ($callerChanged) {
            $this->placeAtEndOfGroup($segment);
        }

        return back();
    }

    /**
     * Move a segment to the end of its caller group so groups stay contiguous
     * (e.g. adding to "Penelepon 1" while "Penelepon 2" already exists).
     */
    private function placeAtEndOfGroup(Audio2Segment $segment): void
    {
        DB::transaction(function () use ($segment): void {
            $others = Audio2Segment::query()
                ->where('audio2_id', $segment->audio2_id)
                ->whereKeyNot($segment->id)
                ->orderBy('seq')
                ->orderBy('id')
                ->get(['id', 'caller']);

            $insertAt = 0;
            foreach ($others as $index => $other) {
                if ($other->caller <= $segment->caller) {
                    $insertAt = $index + 1;
                }
            }

            $ids = $others->pluck('id')->all();
            array_splice($ids, $insertAt, 0, [$segment->id]);

            foreach ($ids as $index => $id) {
                Audio2Segment::query()->whereKey($id)->update(['seq' => $index + 1]);
            }
        });
    }

    public function destroySegment(Audio2Segment $segment): RedirectResponse
    {
        $segment->delete();

        return back();
    }

    public function bulkDeleteSegments(Request $request): RedirectResponse
    {
        $request->validate([
            'segment_ids' => 'required|array',
            'segment_ids.*' => 'integer|exists:audio2_segment,id',
        ]);

        Audio2Segment::query()->whereIn('id', $request->input('segment_ids'))->delete();

        return back();
    }

    public function destroyAllSegments(Audio2 $audio2): RedirectResponse
    {
        Audio2Segment::query()->where('audio2_id', $audio2->id)->delete();

        return back();
    }

    /**
     * Save a new order. Items come in display order; a segment dragged to another caller group carries its new caller.
     */
    public function reorderSegments(Request $request, Audio2 $audio2): RedirectResponse
    {
        $request->validate([
            'segments' => 'required|array',
            'segments.*.id' => 'required|integer',
            'segments.*.caller' => 'required|integer|min:0',
        ]);

        $owned = Audio2Segment::query()->where('audio2_id', $audio2->id)->pluck('id')->flip();

        DB::transaction(function () use ($request, $owned) {
            foreach (array_values($request->input('segments')) as $index => $item) {
                if (! $owned->has($item['id'])) {
                    continue;
                }

                Audio2Segment::query()->whereKey($item['id'])->update([
                    'seq' => $index + 1,
                    'caller' => $item['caller'],
                ]);
            }
        });

        return back();
    }

    /**
     * Split a full transcript into segments.
     *
     * A segment starts at a "Code mm:ss" line; the next non-blank line is its title, kept as typed ("[Kata pembuka]")
     * and everything after it, up to the next segment or caller line, is its body with blank lines kept.
     * "Penelepon N" lines set the caller of the segments below; segments before the first one get $startCaller
     * (0 = Pembuka, or the episode's last caller when appending so a continued chunk stays in its group).
     *
     * @return list<array{caller: int, code: string, time: string, timestamp: int, title: string, body: string}>
     */
    public static function parseTranscript(string $text, int $startCaller = 0): array
    {
        $segments = [];
        $current = null;
        $caller = $startCaller;

        $close = function () use (&$current, &$segments): void {
            if ($current === null) {
                return;
            }

            $lines = $current['lines'];
            while ($lines !== [] && trim($lines[0]) === '') {
                array_shift($lines);
            }
            while ($lines !== [] && trim(end($lines)) === '') {
                array_pop($lines);
            }

            unset($current['lines']);
            $current['body'] = implode("\n", array_map('rtrim', $lines));
            $current['title'] ??= '';
            $segments[] = $current;
            $current = null;
        };

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            if (preg_match(self::CALLER_LINE, $line, $m)) {
                $close();
                $caller = (int) $m[1];

                continue;
            }

            if (preg_match(self::SEGMENT_HEADER, $line, $m)) {
                $close();
                $current = [
                    'caller' => $caller,
                    'code' => $m[1],
                    'time' => $m[2],
                    'timestamp' => self::timeToSeconds($m[2]),
                    'title' => null,
                    'lines' => [],
                ];

                continue;
            }

            if ($current === null) {
                continue;
            }

            if ($current['title'] === null) {
                if (trim($line) !== '') {
                    $current['title'] = trim($line);
                }

                continue;
            }

            $current['lines'][] = $line;
        }

        $close();

        return $segments;
    }

    /**
     * "01:09" → 69, "1:02:03" → 3723.
     */
    public static function timeToSeconds(string $time): int
    {
        $seconds = 0;
        foreach (explode(':', trim($time)) as $part) {
            $seconds = $seconds * 60 + (int) $part;
        }

        return $seconds;
    }

    /**
     * Read the air date from an episode name: yyyymmdd ("Shuohua20180427") or yymmdd ("Zongshu081030"),
     * ignoring a trailing A/B part letter. With extra digits ("Zongshu2020120519") the reading that falls in
     * $yearHint wins, so a typo does not move an episode to another year.
     */
    public static function dateFromTitle(string $title, ?int $yearHint = null): ?Carbon
    {
        if (! preg_match('/^[A-Za-z]+(\d{6,})/', trim($title), $m)) {
            return null;
        }

        $digits = $m[1];
        $slices = strlen($digits) >= 8 ? [substr($digits, 0, 8), substr($digits, -8)] : [];
        $slices[] = substr($digits, 0, 6);
        $slices[] = substr($digits, -6);

        $valid = [];
        foreach (array_unique($slices) as $slice) {
            [$year, $month, $day] = strlen($slice) === 8
                ? [(int) substr($slice, 0, 4), (int) substr($slice, 4, 2), (int) substr($slice, 6, 2)]
                : [2000 + (int) substr($slice, 0, 2), (int) substr($slice, 2, 2), (int) substr($slice, 4, 2)];

            if ($year >= 2000 && $year <= 2099 && checkdate($month, $day, $year)) {
                $valid[] = Carbon::create($year, $month, $day)->startOfDay();
            }
        }

        if ($yearHint !== null) {
            foreach ($valid as $date) {
                if ($date->year === $yearHint) {
                    return $date;
                }
            }
        }

        return $valid[0] ?? null;
    }

    /**
     * Indonesian month name (typos like "Febuari" or "Desvember" included) → 1–12.
     */
    public static function monthFromName(string $name): ?int
    {
        $prefixes = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'mei' => 5, 'jun' => 6,
            'jul' => 7, 'agu' => 8, 'sep' => 9, 'okt' => 10, 'nov' => 11, 'des' => 12];

        return $prefixes[mb_strtolower(mb_substr(trim($name), 0, 3))] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEpisode(Request $request): array
    {
        $data = $request->validate([
            'audio2_category_id' => 'required|integer|exists:audio2_category,id',
            'title' => 'required|string|max:255',
            'url' => 'nullable|string|max:500',
            'duration' => 'nullable|string|max:20',
            'year' => 'nullable|integer|min:1900|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'aired_on' => 'nullable|date',
        ]);

        if (empty($data['aired_on'])) {
            $data['aired_on'] = self::dateFromTitle($data['title'])?->toDateString();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSegment(Request $request): array
    {
        $data = $request->validate([
            'caller' => 'required|integer|min:0|max:999',
            'code' => 'nullable|string|max:50',
            'time' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'title' => 'nullable|string|max:500',
            'content' => 'nullable|string',
        ]);

        $data['timestamp'] = self::timeToSeconds($data['time']);
        unset($data['time']);

        return $data;
    }
}
