<?php

namespace App\Console\Commands;

use App\Http\Controllers\Audio2Controller;
use App\Models\Audio2;
use App\Models\Audio2Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportAudio2FromAudio1 extends Command
{
    /**
     * Audio 1 root categories copied to Audio 2: Totem, Tanya Jawab, Shuohua, Lagu.
     *
     * @var list<int>
     */
    public const SOURCE_CATEGORY_IDS = [165, 238, 237, 355];

    protected $signature = 'audio2:import-from-audio1 {--dry-run : Show what would be copied without writing}';

    protected $description = 'Copy Audio 1 episodes (name, link, duration, category, year, month, date) into Audio 2. Safe to run again.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $roots = DB::table('category')->whereIn('id', self::SOURCE_CATEGORY_IDS)->orderBy('seq')->get();

        $created = 0;
        $skipped = 0;
        $undated = [];

        foreach ($roots as $root) {
            $category = $dryRun
                ? Audio2Category::query()->where('source_category_id', $root->id)->first()
                : Audio2Category::query()->firstOrCreate(
                    ['source_category_id' => $root->id],
                    ['title' => trim($root->title), 'seq' => $root->seq],
                );

            $existing = $category
                ? Audio2::query()->where('audio2_category_id', $category->id)->whereNotNull('source_audio_id')->pluck('source_audio_id')->flip()
                : collect();

            $rows = DB::table('audio as a')
                ->join('audio_sub_group as s', 's.id', '=', 'a.audio_sub_group_id')
                ->join('category as y', 'y.id', '=', 's.audio_category_id')
                ->where('y.parent_id', $root->id)
                ->orderBy('y.seq')->orderBy('s.seq')->orderBy('a.seq')->orderBy('a.id')
                ->get(['a.id', 'a.title', 'a.url', 'a.duration', 'a.seq', 'y.title as year_title', 's.name as month_name']);

            $insert = [];
            foreach ($rows as $row) {
                if ($existing->has($row->id)) {
                    $skipped++;

                    continue;
                }

                $title = trim($row->title);
                $sourceYear = preg_match('/(\d{4})/', (string) $row->year_title, $m) ? (int) $m[1] : null;
                $airedOn = Audio2Controller::dateFromTitle($title, $sourceYear);
                $year = $sourceYear ?? $airedOn?->year;
                $month = Audio2Controller::monthFromName((string) $row->month_name) ?? $airedOn?->month;

                if ($airedOn === null) {
                    $undated[] = $title;
                }

                $insert[] = [
                    'audio2_category_id' => $category?->id,
                    'title' => $title,
                    'url' => $row->url,
                    'duration' => $row->duration,
                    'year' => $year,
                    'month' => $month,
                    'aired_on' => $airedOn?->toDateString(),
                    'seq' => (int) $row->seq,
                    'source_audio_id' => $row->id,
                ];
            }

            $created += count($insert);
            $this->line(sprintf('%s: %d baru', trim($root->title), count($insert)));

            if (! $dryRun) {
                foreach (array_chunk($insert, 500) as $chunk) {
                    Audio2::query()->insert($chunk);
                }
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Episode baru: {$created}, sudah ada: {$skipped}");

        if ($undated !== []) {
            $this->warn('Tanggal tidak terbaca dari nama ('.count($undated).'): '.implode(', ', $undated));
        }

        return self::SUCCESS;
    }
}
