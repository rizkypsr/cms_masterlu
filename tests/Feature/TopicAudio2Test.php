<?php

use App\Models\Audio2;
use App\Models\Audio2Category;
use App\Models\Audio2Segment;
use App\Models\TopicContent;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Schema::create('category', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('type')->nullable();
        $table->string('languange')->nullable();
    });
    Schema::create('topics', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('category_id');
        $table->string('title');
        $table->integer('seq')->default(0);
    });
    Schema::create('topics_category', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->unsignedBigInteger('topics_id');
        $table->string('title');
        $table->integer('seq')->default(0);
        $table->integer('have_child')->default(0);
    });
    Schema::create('topics_content', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('topics_category_id');
        $table->string('type');
        $table->unsignedBigInteger('id_header');
        $table->integer('seq')->default(0);
    });
    Schema::create('audio', function (Blueprint $table) {
        $table->id();
        $table->string('title');
    });
    Schema::create('audio_subtitle', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('audio_id');
        $table->string('title')->nullable();
        $table->text('description')->nullable();
        $table->string('timestamp')->nullable();
    });

    DB::table('category')->insert(['id' => 1, 'title' => 'Topik']);
    DB::table('topics')->insert(['id' => 1, 'category_id' => 1, 'title' => 'Topik A']);
    DB::table('topics_category')->insert(['id' => 7, 'topics_id' => 1, 'title' => 'Bab 1']);
    DB::table('audio')->insert(['id' => 3, 'title' => 'Wenda110415']);
    DB::table('audio_subtitle')->insert(['id' => 40, 'audio_id' => 3, 'title' => 'Lama', 'timestamp' => '80']);
    DB::table('topics_content')->insert(['topics_category_id' => 7, 'type' => 'audio', 'id_header' => 40, 'seq' => 1]);

    $shuohua = Audio2Category::create(['title' => 'Shuohua', 'seq' => 1]);
    $totem = Audio2Category::create(['title' => 'Totem', 'seq' => 2]);
    $this->episode = Audio2::create(['audio2_category_id' => $shuohua->id, 'title' => 'Shuohua20110624', 'year' => 2011, 'month' => 6]);
    $other = Audio2::create(['audio2_category_id' => $totem->id, 'title' => 'Zongshu110701', 'year' => 2011, 'month' => 7]);
    $this->segment = Audio2Segment::create(['audio2_id' => $this->episode->id, 'caller' => 1, 'code' => 'Shuohua20110624', 'timestamp' => 69, 'title' => '[Bermimpi anak]', 'seq' => 1]);
    Audio2Segment::create(['audio2_id' => $other->id, 'caller' => 0, 'code' => 'Zongshu110701', 'timestamp' => 0, 'title' => '[Kata pembuka]', 'seq' => 1]);
    $this->totem = $totem;

    $this->actingAs(User::factory()->create());
});

it('links new topic items to Audio 2 segments and keeps Audio 1 items listed', function () {
    $this->post('/topic/category/7/content', ['id_header' => $this->segment->id])->assertSessionHasNoErrors();

    expect(TopicContent::orderBy('seq')->get(['type', 'id_header', 'seq'])->toArray())->toBe([
        ['type' => 'audio', 'id_header' => 40, 'seq' => 1],
        ['type' => 'audio2', 'id_header' => $this->segment->id, 'seq' => 2],
    ]);

    $this->get('/topic/category/7/detail')->assertInertia(fn (Assert $page) => $page
        ->component('Topic/Detail')
        ->has('items', 2)
        ->where('items.0.type', 'audio')
        ->where('items.0.title', 'Lama')
        ->where('items.0.timestamp', '00:01:20')
        ->where('items.1.type', 'audio2')
        ->where('items.1.title', '[Bermimpi anak]')
        ->where('items.1.timestamp', '00:01:09')
        ->where('items.1.source', 'Shuohua20110624')
        ->where('items.1.content.audio2_id', $this->episode->id)
        ->has('audio2Categories', 2));
});

it('rejects ids that are not Audio 2 segments', function () {
    $this->post('/topic/category/7/content', ['id_header' => 99999])->assertSessionHasErrors('id_header');

    expect(TopicContent::count())->toBe(1);
});

it('offers only Audio 2 segments in the picker, filtered by search and category', function () {
    $picker = function (array $query): array {
        $items = [];
        $this->get('/topic/category/7/detail?'.http_build_query($query))->assertInertia(function (Assert $page) use (&$items): void {
            $page->missing('availableItems')->reloadOnly('availableItems', function (Assert $reload) use (&$items): void {
                $items = $reload->toArray()['props']['availableItems'];
            });
        });

        return $items;
    };

    $all = $picker([]);
    expect($all['total'])->toBe(2)
        ->and(collect($all['data'])->pluck('type')->unique()->all())->toBe(['audio2']);

    expect($picker(['item_search' => 'mimpi'])['data'])->toHaveCount(1)
        ->and($picker(['item_search' => 'Zongshu'])['data'][0]['title'])->toBe('[Kata pembuka]')
        ->and($picker(['item_category' => $this->totem->id])['data'])->toHaveCount(1)
        ->and(collect($picker(['item_search' => 'Shuohua20110624 01:09'])['data'])->pluck('id')->all())->toBe([$this->segment->id])
        ->and(collect($picker(['item_search' => 'Shuohua20110624 0:01:09'])['data'])->pluck('id')->all())->toBe([$this->segment->id])
        ->and($picker(['item_search' => 'Shuohua20110624 01:10'])['data'])->toBeEmpty();
});

it('reorders and deletes mixed Audio 1 and Audio 2 items', function () {
    $this->post('/topic/category/7/content', ['id_header' => $this->segment->id, 'seq' => 1]);
    $audio2 = TopicContent::where('type', 'audio2')->first();
    expect($audio2->seq)->toBe(1);

    $this->put("/topic/content/{$audio2->id}", ['seq' => 2])->assertSessionHasNoErrors();
    expect(TopicContent::orderBy('seq')->pluck('type')->all())->toBe(['audio', 'audio2']);

    $this->delete('/topic/content/'.TopicContent::where('type', 'audio')->value('id'));
    expect(TopicContent::get(['type', 'seq'])->toArray())->toBe([['type' => 'audio2', 'seq' => 1]]);
});
