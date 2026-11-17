<?php

use App\Http\Controllers\Audio2Controller;
use App\Models\Audio2;
use App\Models\Audio2Category;
use App\Models\Audio2Segment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$transcript = <<<'TXT'
Shuohua20111014 00:00
[Kata pembuka]

Master:
Baiklah, para pendengar ...

Penelepon ke-1.

Shuohua20111014 01:09
[Hal yang perlu diperhatikan saat bersembahyang]

Penelepon: Halo

Master: Ya


Shuohua20111014 08:53
[Buah persembahan baru boleh dimakan setelah diganti]

Penelepon: ...

Penelepon 2.

Shuohua20111014 1:02:05
Judul tanpa kurung
TXT;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('splits a full transcript into segments grouped by caller', function () use ($transcript) {
    $segments = Audio2Controller::parseTranscript($transcript);

    expect($segments)->toHaveCount(4)
        ->and(array_column($segments, 'caller'))->toBe([0, 1, 1, 2])
        ->and(array_column($segments, 'timestamp'))->toBe([0, 69, 533, 3725])
        ->and($segments[0])->toMatchArray(['code' => 'Shuohua20111014', 'title' => '[Kata pembuka]', 'body' => "Master:\nBaiklah, para pendengar ..."])
        ->and($segments[1]['body'])->toBe("Penelepon: Halo\n\nMaster: Ya")
        ->and($segments[3])->toMatchArray(['title' => 'Judul tanpa kurung', 'body' => '']);
});

it('ignores text before the first segment header', function () {
    expect(Audio2Controller::parseTranscript("catatan\n\nWenda200103 00:00\n[A]\nisi"))->toHaveCount(1)
        ->and(Audio2Controller::parseTranscript('tanpa header'))->toBe([]);
});

it('reads the air date from episode names', function (string $title, ?string $date) {
    expect(Audio2Controller::dateFromTitle($title)?->toDateString())->toBe($date);
})->with([
    ['Zongshu081030', '2008-10-30'],
    ['Shuohua20180427', '2018-04-27'],
    ['Wenda20120226A', '2012-02-26'],
    ['wenda100418B', '2010-04-18'],
    ['Zongshu2020120519', '2020-12-05'],
    ['Shuohua080725', '2008-07-25'],
    ['100 orang nyanyi Da Bei Zhou', null],
]);

it('prefers the reading that matches the Audio 1 year for typo names', function () {
    expect(Audio2Controller::dateFromTitle('Zongshu2020120519', 2012)?->toDateString())->toBe('2012-05-19');
});

it('maps month names including typos', function () {
    expect(Audio2Controller::monthFromName('Febuari'))->toBe(2)
        ->and(Audio2Controller::monthFromName('Desvember'))->toBe(12)
        ->and(Audio2Controller::monthFromName('Lagu'))->toBeNull();
});

it('copies Audio 1 episodes once, keeping Audio 1 untouched', function () {
    Schema::create('category', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->integer('seq')->default(0);
    });
    Schema::create('audio_sub_group', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('audio_category_id');
        $table->string('name');
        $table->integer('seq')->default(0);
    });
    Schema::create('audio', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('audio_sub_group_id');
        $table->string('title');
        $table->string('url')->nullable();
        $table->string('duration')->nullable();
        $table->integer('seq')->default(0);
    });

    DB::table('category')->insert([
        ['id' => 238, 'title' => 'Tanya Jawab', 'parent_id' => null, 'seq' => 3],
        ['id' => 355, 'title' => 'Lagu', 'parent_id' => null, 'seq' => 6],
        ['id' => 500, 'title' => 'Tanya Jawab 2020', 'parent_id' => 238, 'seq' => 1],
        ['id' => 356, 'title' => '100 orang nyanyi Da Bei Zhou', 'parent_id' => 355, 'seq' => 1],
    ]);
    DB::table('audio_sub_group')->insert([
        ['id' => 1, 'audio_category_id' => 500, 'name' => 'Januari', 'seq' => 1],
        ['id' => 664, 'audio_category_id' => 356, 'name' => 'Lagu', 'seq' => 1],
    ]);
    DB::table('audio')->insert([
        ['id' => 10, 'audio_sub_group_id' => 1, 'title' => 'Wenda200103', 'url' => 'https://x/w.mp3', 'duration' => '00:41:20', 'seq' => 1],
        ['id' => 3616, 'audio_sub_group_id' => 664, 'title' => "100 orang nyanyi Da Bei Zhou   \r\n", 'url' => 'https://x/l.mp3', 'duration' => '00:30:10', 'seq' => 1],
    ]);

    $this->artisan('audio2:import-from-audio1', ['--dry-run' => true])->assertSuccessful();
    expect(Audio2::count())->toBe(0);

    $this->artisan('audio2:import-from-audio1')->assertSuccessful();
    $this->artisan('audio2:import-from-audio1')->assertSuccessful();

    expect(Audio2Category::orderBy('seq')->pluck('title')->all())->toBe(['Tanya Jawab', 'Lagu'])
        ->and(Audio2::count())->toBe(2)
        ->and(DB::table('audio')->count())->toBe(2);

    $wenda = Audio2::where('source_audio_id', 10)->first();
    expect($wenda->only(['title', 'url', 'duration', 'year', 'month']))
        ->toBe(['title' => 'Wenda200103', 'url' => 'https://x/w.mp3', 'duration' => '00:41:20', 'year' => 2020, 'month' => 1])
        ->and($wenda->aired_on->toDateString())->toBe('2020-01-03');

    $lagu = Audio2::where('source_audio_id', 3616)->first();
    expect($lagu->title)->toBe('100 orang nyanyi Da Bei Zhou')
        ->and($lagu->aired_on)->toBeNull();
});

function audio2Episode(): Audio2
{
    $category = Audio2Category::create(['title' => 'Shuohua', 'seq' => 1]);

    return Audio2::create(['audio2_category_id' => $category->id, 'title' => 'Shuohua20111014', 'year' => 2011, 'month' => 10]);
}

it('stores a full transcript and appends on a second input', function () use ($transcript) {
    $episode = audio2Episode();

    $this->post("/audio2/episode/{$episode->id}/transcript", ['text' => $transcript])->assertRedirect()->assertSessionHasNoErrors();
    $this->post("/audio2/episode/{$episode->id}/transcript", ['text' => "Shuohua20111014 20:00\n[Penutup]\nisi"])->assertRedirect();

    $segments = $episode->segments()->get();
    expect($segments)->toHaveCount(5)
        ->and($segments->pluck('seq')->all())->toBe([1, 2, 3, 4, 5])
        ->and($segments[1]->content)->toBe('<p>Penelepon: Halo<br><br>Master: Ya</p>')
        ->and($segments[4]->title)->toBe('[Penutup]');
});

it('continues the last caller when an appended chunk has no caller line', function () use ($transcript) {
    $episode = audio2Episode();

    $this->post("/audio2/episode/{$episode->id}/transcript", ['text' => $transcript]);
    $this->post("/audio2/episode/{$episode->id}/transcript", ['text' => "Shuohua20111014 1:10:00\n[Lanjutan]\nisi"]);

    expect($episode->segments()->get()->last()->only(['title', 'caller']))->toBe(['title' => '[Lanjutan]', 'caller' => 2]);

    $this->postJson('/audio2/transcript/preview', ['text' => "Shuohua20111014 1:10:00\n[Lanjutan]", 'start_caller' => 2])
        ->assertJsonPath('segments.0.caller', 2);
});

it('rejects a transcript without segment headers', function () {
    $episode = audio2Episode();

    $this->post("/audio2/episode/{$episode->id}/transcript", ['text' => 'teks biasa'])->assertSessionHasErrors('text');
    expect(Audio2Segment::count())->toBe(0);
});

it('previews the split without saving', function () use ($transcript) {
    $this->postJson('/audio2/transcript/preview', ['text' => $transcript])
        ->assertOk()
        ->assertJsonCount(4, 'segments')
        ->assertJsonPath('segments.1.caller', 1)
        ->assertJsonPath('segments.1.time', '01:09');

    expect(Audio2Segment::count())->toBe(0);
});

it('adds, edits, deletes, bulk deletes and deletes all segments', function () {
    $episode = audio2Episode();

    $this->post("/audio2/episode/{$episode->id}/segment", ['caller' => 0, 'code' => 'Shuohua20111014', 'time' => '00:00', 'title' => 'A', 'content' => '<p>a</p>'])->assertSessionHasNoErrors();
    $this->post("/audio2/episode/{$episode->id}/segment", ['caller' => 1, 'time' => '1:00:10', 'title' => 'B'])->assertSessionHasNoErrors();
    $this->post("/audio2/episode/{$episode->id}/segment", ['caller' => 1, 'time' => '02:00', 'title' => 'C'])->assertSessionHasNoErrors();
    $this->post("/audio2/episode/{$episode->id}/segment", ['caller' => 1, 'time' => 'abc'])->assertSessionHasErrors('time');

    [$a, $b, $c] = $episode->segments()->get()->all();
    expect($b->timestamp)->toBe(3610)->and($c->seq)->toBe(3);

    $this->put("/audio2/segment/{$a->id}", ['caller' => 0, 'time' => '00:05', 'title' => 'A2'])->assertSessionHasNoErrors();
    expect($a->fresh()->only(['title', 'timestamp']))->toBe(['title' => 'A2', 'timestamp' => 5]);

    $this->delete("/audio2/segment/{$c->id}")->assertRedirect();
    $this->post('/audio2/segment/bulk-delete', ['segment_ids' => [$a->id]])->assertSessionHasNoErrors();
    expect($episode->segments()->pluck('id')->all())->toBe([$b->id]);

    $this->delete("/audio2/episode/{$episode->id}/segment")->assertRedirect();
    expect(Audio2Segment::count())->toBe(0);
});

it('keeps caller groups together when adding or regrouping a segment', function () {
    $episode = audio2Episode();
    foreach ([[0, 'A'], [1, 'B'], [2, 'C']] as [$caller, $title]) {
        $this->post("/audio2/episode/{$episode->id}/segment", ['caller' => $caller, 'time' => '00:00', 'title' => $title]);
    }

    $this->post("/audio2/episode/{$episode->id}/segment", ['caller' => 1, 'time' => '00:00', 'title' => 'D'])->assertSessionHasNoErrors();
    $titles = fn () => $episode->segments()->get()->map(fn ($s) => $s->title.$s->caller)->all();
    expect($titles())->toBe(['A0', 'B1', 'D1', 'C2']);

    $c = Audio2Segment::where('title', 'C')->first();
    $this->put("/audio2/segment/{$c->id}", ['caller' => 0, 'time' => '00:00', 'title' => 'C'])->assertSessionHasNoErrors();
    expect($titles())->toBe(['A0', 'C0', 'B1', 'D1'])
        ->and($episode->segments()->pluck('seq')->all())->toBe([1, 2, 3, 4]);
});

it('reorders segments and moves them between callers', function () {
    $episode = audio2Episode();
    $other = Audio2::create(['audio2_category_id' => $episode->audio2_category_id, 'title' => 'Lain']);
    $foreign = Audio2Segment::create(['audio2_id' => $other->id, 'caller' => 0, 'seq' => 1]);

    $ids = collect(['A', 'B', 'C'])->map(fn ($t, $i) => Audio2Segment::create([
        'audio2_id' => $episode->id, 'caller' => $i === 0 ? 0 : 1, 'title' => $t, 'seq' => $i + 1,
    ])->id)->all();

    $this->post("/audio2/episode/{$episode->id}/segment/reorder", ['segments' => [
        ['id' => $ids[2], 'caller' => 0],
        ['id' => $ids[0], 'caller' => 0],
        ['id' => $ids[1], 'caller' => 1],
        ['id' => $foreign->id, 'caller' => 5],
    ]])->assertSessionHasNoErrors();

    expect($episode->segments()->get()->map(fn ($s) => [$s->title, $s->caller])->all())
        ->toBe([['C', 0], ['A', 0], ['B', 1]])
        ->and($foreign->fresh()->caller)->toBe(0);
});

it('creates, updates and deletes episodes with their segments', function () {
    $category = Audio2Category::create(['title' => 'Tanya Jawab', 'seq' => 1]);

    $this->post('/audio2/episode', ['audio2_category_id' => $category->id, 'title' => 'Wenda200110', 'year' => 2020, 'month' => 1])
        ->assertSessionHasNoErrors();

    $episode = Audio2::first();
    expect($episode->aired_on->toDateString())->toBe('2020-01-10');

    $this->put("/audio2/episode/{$episode->id}", ['audio2_category_id' => $category->id, 'title' => 'Wenda200110', 'aired_on' => '2020-01-11'])
        ->assertSessionHasNoErrors();
    expect($episode->fresh()->aired_on->toDateString())->toBe('2020-01-11');

    Audio2Segment::create(['audio2_id' => $episode->id, 'caller' => 0, 'seq' => 1]);
    $this->delete("/audio2/episode/{$episode->id}")->assertRedirect();

    expect(Audio2::count())->toBe(0)->and(Audio2Segment::count())->toBe(0);
});

it('adds, renames, reorders and deletes categories', function () {
    $totem = Audio2Category::create(['title' => 'Totem', 'seq' => 1]);
    $lagu = Audio2Category::create(['title' => 'Lagu', 'seq' => 2]);

    $this->post('/audio2/category', ['title' => 'Totem'])->assertSessionHasErrors('title');
    $this->post('/audio2/category', ['title' => 'Ceramah'])->assertSessionHasNoErrors();
    $ceramah = Audio2Category::where('title', 'Ceramah')->first();
    expect($ceramah->seq)->toBe(3);

    $this->put("/audio2/category/{$lagu->id}", ['title' => 'Lagu Buddha'])->assertSessionHasNoErrors();
    $this->put("/audio2/category/{$lagu->id}", ['title' => 'Lagu Buddha'])->assertSessionHasNoErrors();
    expect($lagu->fresh()->title)->toBe('Lagu Buddha');

    $this->post('/audio2/category/reorder', ['ids' => [$ceramah->id, $totem->id, $lagu->id]])->assertSessionHasNoErrors();
    expect(Audio2Category::orderBy('seq')->pluck('title')->all())->toBe(['Ceramah', 'Totem', 'Lagu Buddha']);

    Audio2::create(['audio2_category_id' => $totem->id, 'title' => 'Zongshu20150101']);
    $this->delete("/audio2/category/{$totem->id}")->assertSessionHasErrors('category');
    expect($totem->fresh())->not->toBeNull();

    $this->delete("/audio2/category/{$ceramah->id}")->assertSessionHasNoErrors();
    expect(Audio2Category::count())->toBe(2);
});

it('lists episodes without a year under their own tab', function () {
    $lagu = Audio2Category::create(['title' => 'Lagu', 'seq' => 1]);
    Audio2::create(['audio2_category_id' => $lagu->id, 'title' => 'Tanpa tahun']);
    Audio2::create(['audio2_category_id' => $lagu->id, 'title' => 'Baru', 'year' => 2026, 'month' => 1]);

    $this->get("/audio2?category={$lagu->id}")->assertInertia(fn ($page) => $page
        ->where('selectedYear', 2026)->where('hasUndated', true)->has('episodes', 1)->where('episodes.0.title', 'Baru'));

    $this->get("/audio2?category={$lagu->id}&year=none")->assertInertia(fn ($page) => $page
        ->where('selectedYear', null)->has('episodes', 1)->where('episodes.0.title', 'Tanpa tahun'));
});

it('renders the index and transcript pages', function () {
    $episode = audio2Episode();
    Audio2Segment::create(['audio2_id' => $episode->id, 'caller' => 0, 'seq' => 1, 'title' => 'A']);

    $this->get("/audio2?category={$episode->audio2_category_id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Audio2/Index', false)
            ->where('selectedYear', 2011)
            ->where('episodes.0.segments_count', 1));

    $this->get("/audio2/episode/{$episode->id}/transcript")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Audio2/Transcript', false)->has('segments', 1));
});

it('parses a transcript that starts without an opening segment', function () {
    $segments = Audio2Controller::parseTranscript("Penelepon 1.\n\nShuohua20110624 00:19\n[A]\n\nPenelepon:\nx\n\nPenelepon 2.\n\nShuohua20110624 05:20\n[C]\n\nPenelepon:\nz");

    expect(array_column($segments, 'caller'))->toBe([1, 2])
        ->and(array_column($segments, 'title'))->toBe(['[A]', '[C]']);
});
