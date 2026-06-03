<?php

use App\Models\User;
use App\Models\Video;
use App\Models\VideoSubtitle;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->video = Video::create([
        'title' => 'Test Video',
        'seq' => 1,
    ]);
});

it('stores SRT timestamps as seconds not milliseconds', function () {
    $srtContent = "1\r\n00:00:00,000 --> 00:01:00,000\r\nFirst subtitle line\r\n\r\n2\r\n00:01:00,000 --> 00:01:02,000\r\nSecond subtitle line\r\n";

    $file = UploadedFile::fake()->createWithContent('test.srt', $srtContent);

    $this->post(route('video.subtitle.upload-srt', $this->video), [
        'srt_file' => $file,
    ])->assertRedirect();

    $subtitles = VideoSubtitle::where('video_id', $this->video->id)
        ->orderBy('timestamp')
        ->get();

    expect($subtitles)->toHaveCount(2);

    // First subtitle: 00:00:00 = 0 seconds
    expect($subtitles[0]->timestamp)->toBe(0);

    // Second subtitle: 00:01:00 = 60 seconds (NOT 60000 milliseconds)
    expect($subtitles[1]->timestamp)->toBe(60);
});

it('stores manual subtitle timestamps as seconds', function () {
    $this->post(route('video.subtitle.store', $this->video), [
        'timestamp' => '00:01:00',
        'description' => 'Manual subtitle',
    ])->assertRedirect();

    $subtitle = VideoSubtitle::where('video_id', $this->video->id)->first();

    // 00:01:00 = 60 seconds
    expect($subtitle->timestamp)->toBe(60);
});

it('exports SRT with correct timestamps from seconds storage', function () {
    VideoSubtitle::create([
        'video_id' => $this->video->id,
        'timestamp' => 0,
        'description' => 'First line',
    ]);

    VideoSubtitle::create([
        'video_id' => $this->video->id,
        'timestamp' => 60,
        'description' => 'Second line',
    ]);

    $response = $this->get(route('video.subtitle.export-srt', $this->video));

    $response->assertSuccessful();

    $content = $response->getContent();

    // First subtitle should start at 00:00:00,000
    expect($content)->toContain('00:00:00,000');
    // Second subtitle should start at 00:01:00,000 (NOT 16:40:00,000)
    expect($content)->toContain('00:01:00,000');
    expect($content)->not->toContain('16:40:00,000');
});
