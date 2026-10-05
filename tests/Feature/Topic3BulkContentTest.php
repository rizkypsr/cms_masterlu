<?php

use App\Http\Controllers\Topic3Controller;
use App\Models\Topic3Chapter;
use App\Models\Topic3Content;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // topics3 tables come from the legacy MySQL dump, not migrations.
    Schema::create('topics3_chapters', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->unsignedBigInteger('topics3_id')->nullable();
        $table->string('title')->nullable();
        $table->text('description')->nullable();
        $table->integer('seq')->default(0);
        $table->boolean('have_child')->default(false);
        $table->unsignedBigInteger('video_category_id')->nullable();
    });
    Schema::create('topics3_content_category', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->text('description')->nullable();
        $table->integer('seq')->default(0);
        $table->timestamps();
    });
    Schema::create('topics3_contents', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('topics3_chapters_id');
        $table->unsignedBigInteger('category_id')->nullable();
        $table->longText('content');
        $table->integer('page');
    });

    $this->actingAs(User::factory()->create());
    $this->chapter = Topic3Chapter::create(['title' => 'Bab 1', 'seq' => 1]);
});

$sample = <<<'TXT'
1.
Tanya: Saat aku membaca sutra, aku tertidur.

Master: Samsara adalah sebab-akibat.

2.
Tanya: Jika anakku menggunakan nama "Shanyou"?

Master: Karakter "you" (游) tidak stabil.

3.
Tanya: Apa yang seharusnya mereka lakukan?
TXT;

it('splits numbered text into blocks keeping the number line', function () use ($sample) {
    $blocks = Topic3Controller::splitNumberedText($sample);

    expect($blocks)->toHaveCount(3)
        ->and($blocks[0])->toBe("1.\nTanya: Saat aku membaca sutra, aku tertidur.\n\nMaster: Samsara adalah sebab-akibat.")
        ->and($blocks[2])->toBe("3.\nTanya: Apa yang seharusnya mereka lakukan?");
});

it('keeps every blank line as typed', function () {
    expect(Topic3Controller::plainTextToHtml("2.\n\nTanya: a\n\n\nMaster: b"))
        ->toBe('<p>2.<br><br>Tanya: a<br><br><br>Master: b</p>');
});

it('does not split on numbered lines that carry text', function () {
    $blocks = Topic3Controller::splitNumberedText("1.\nMaster: dua hal:\n1. jangan menanam sebab\n2. jangan ada akibat");

    expect($blocks)->toHaveCount(1);
});

it('stores each numbered block as a separate content with sequential pages', function () use ($sample) {
    Topic3Content::create(['topics3_chapters_id' => $this->chapter->id, 'page' => 5, 'content' => '<p>lama</p>']);

    $this->post(route('topic3.content.bulk-store', $this->chapter), ['text' => $sample])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $contents = Topic3Content::where('topics3_chapters_id', $this->chapter->id)->orderBy('page')->get();

    expect($contents)->toHaveCount(4)
        ->and($contents->pluck('page')->all())->toBe([5, 6, 7, 8])
        ->and($contents[1]->content)->toBe('<p>1.<br>Tanya: Saat aku membaca sutra, aku tertidur.<br><br>Master: Samsara adalah sebab-akibat.</p>')
        ->and($contents[2]->content)->toContain('&quot;Shanyou&quot;');
});

it('applies a new category to every bulk item', function () use ($sample) {
    $this->post(route('topic3.content.bulk-store', $this->chapter), [
        'text' => $sample,
        'new_category_name' => 'Tanya Jawab',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Topic3Content::whereNull('category_id')->count())->toBe(0)
        ->and(Topic3Content::distinct()->pluck('category_id'))->toHaveCount(1);
});

it('rejects text without numbered lines', function () {
    $this->post(route('topic3.content.bulk-store', $this->chapter), ['text' => 'tanpa nomor'])
        ->assertSessionHasErrors('text');

    expect(Topic3Content::count())->toBe(0);
});
