<?php

use App\Http\Controllers\AudioCategoryController;
use App\Http\Controllers\BookCategoryController;
use App\Http\Controllers\Topic2Controller;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\VideoCategoryController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Video - Daftar Isi (CH)
    Route::get('video/daftar-isi/{category?}', [VideoCategoryController::class, 'daftarIsi'])->name('video.daftar-isi');
    Route::post('video/daftar-isi/category', [VideoCategoryController::class, 'storeDaftarIsi'])->name('video.daftar-isi.store');
    Route::post('video/daftar-isi/category/{category}/video-category', [VideoCategoryController::class, 'storeVideoCategory'])->name('video.daftar-isi.video-category.store');

    // Video - Topik (ID)
    Route::get('video/topik/{category?}', [VideoCategoryController::class, 'topik'])->name('video.topik');
    Route::post('video/topik/category', [VideoCategoryController::class, 'storeTopik'])->name('video.topik.store');
    Route::post('video/topik/category/{category}/video-category', [VideoCategoryController::class, 'storeVideoCategory'])->name('video.topik.video-category.store');

    // Video - Shared
    Route::put('video/category/{category}', [VideoCategoryController::class, 'update'])->name('video.category.update');
    Route::delete('video/category/{category}', [VideoCategoryController::class, 'destroy'])->name('video.category.destroy');

    // Video Category CRUD
    Route::put('video/video-category/{videoCategory}', [VideoCategoryController::class, 'updateVideoCategory'])->name('video.video-category.update');
    Route::delete('video/video-category/{videoCategory}', [VideoCategoryController::class, 'destroyVideoCategory'])->name('video.video-category.destroy');

    // Video Category Detail Page
    Route::get('video/video-category/{videoCategory}', [VideoCategoryController::class, 'showVideoCategory'])->name('video.video-category.show');
    Route::post('video/video-category/{videoCategory}/video', [VideoCategoryController::class, 'storeOrUpdateVideoDetail'])->name('video.video-category.video.store');
    Route::post('video/video-category/{videoCategory}/sub-video', [VideoCategoryController::class, 'storeSubVideo'])->name('video.sub-video.store');
    Route::put('video/sub-video/{subVideo}', [VideoCategoryController::class, 'updateSubVideo'])->name('video.sub-video.update');
    Route::delete('video/sub-video/{subVideo}', [VideoCategoryController::class, 'destroySubVideo'])->name('video.sub-video.destroy');
    Route::put('video/sub-video-child/{video}', [VideoCategoryController::class, 'updateSubVideoChild'])->name('video.sub-video-child.update');
    Route::delete('video/sub-video-child/{video}', [VideoCategoryController::class, 'destroySubVideoChild'])->name('video.sub-video-child.destroy');

    // Video Subtitle CRUD
    Route::get('video/subtitle/{video}', [VideoCategoryController::class, 'showSubtitle'])->name('video.subtitle.show');
    Route::post('video/subtitle/{video}', [VideoCategoryController::class, 'storeSubtitle'])->name('video.subtitle.store');
    Route::post('video/subtitle/{video}/upload-srt', [VideoCategoryController::class, 'uploadSrtFile'])->name('video.subtitle.upload-srt');
    Route::get('video/subtitle/{video}/export-srt', [VideoCategoryController::class, 'exportSrtFile'])->name('video.subtitle.export-srt');
    Route::put('video/subtitle/item/{subtitle}', [VideoCategoryController::class, 'updateSubtitle'])->name('video.subtitle.update');
    Route::delete('video/subtitle/item/{subtitle}', [VideoCategoryController::class, 'destroySubtitle'])->name('video.subtitle.destroy');
    Route::delete('video/subtitle/{video}/all', [VideoCategoryController::class, 'destroyAllSubtitles'])->name('video.subtitle.destroy-all');

    // Video Item CRUD
    Route::post('video/category/{category}/video', [VideoCategoryController::class, 'storeVideo'])->name('video.item.store');
    Route::put('video/item/{video}', [VideoCategoryController::class, 'updateVideo'])->name('video.item.update');
    Route::delete('video/item/{video}', [VideoCategoryController::class, 'destroyVideo'])->name('video.item.destroy');

    // Audio - Daftar Isi (CH)
    Route::get('audio/daftar-isi/{category?}', [AudioCategoryController::class, 'daftarIsi'])->name('audio.daftar-isi');
    Route::post('audio/daftar-isi/category', [AudioCategoryController::class, 'storeDaftarIsi'])->name('audio.daftar-isi.store');
    Route::post('audio/daftar-isi/category/{category}/sub-group', [AudioCategoryController::class, 'storeSubGroup'])->name('audio.daftar-isi.sub-group.store');

    // Audio - Topik (ID)
    Route::get('audio/topik/{category?}', [AudioCategoryController::class, 'topik'])->name('audio.topik');
    Route::post('audio/topik/category', [AudioCategoryController::class, 'storeTopik'])->name('audio.topik.store');
    Route::post('audio/topik/category/{category}/sub-group', [AudioCategoryController::class, 'storeSubGroup'])->name('audio.topik.sub-group.store');

    // Audio - Shared
    Route::put('audio/category/{category}', [AudioCategoryController::class, 'update'])->name('audio.category.update');
    Route::delete('audio/category/{category}', [AudioCategoryController::class, 'destroy'])->name('audio.category.destroy');

    // Audio Sub-Group CRUD
    Route::put('audio/sub-group/{subGroup}', [AudioCategoryController::class, 'updateSubGroup'])->name('audio.sub-group.update');
    Route::delete('audio/sub-group/{subGroup}', [AudioCategoryController::class, 'destroySubGroup'])->name('audio.sub-group.destroy');
    Route::put('audio/audio-child/{audio}', [AudioCategoryController::class, 'updateAudioChild'])->name('audio.audio-child.update');
    Route::delete('audio/audio-child/{audio}', [AudioCategoryController::class, 'destroyAudioChild'])->name('audio.audio-child.destroy');

    // Audio Subtitle CRUD
    Route::get('audio/subtitle/{audio}', [AudioCategoryController::class, 'showSubtitle'])->name('audio.subtitle.show');
    Route::post('audio/subtitle/{audio}', [AudioCategoryController::class, 'storeSubtitle'])->name('audio.subtitle.store');
    Route::post('audio/subtitle/{audio}/upload-srt', [AudioCategoryController::class, 'uploadSrtFile'])->name('audio.subtitle.upload-srt');
    Route::get('audio/subtitle/{audio}/export-srt', [AudioCategoryController::class, 'exportSrtFile'])->name('audio.subtitle.export-srt');
    Route::put('audio/subtitle/item/{subtitle}', [AudioCategoryController::class, 'updateSubtitle'])->name('audio.subtitle.update');
    Route::delete('audio/subtitle/item/{subtitle}', [AudioCategoryController::class, 'destroySubtitle'])->name('audio.subtitle.destroy');
    Route::delete('audio/subtitle/{audio}/all', [AudioCategoryController::class, 'destroyAllSubtitles'])->name('audio.subtitle.destroy-all');

    // Topic Routes (without lang parameter)
    Route::get('topic', [TopicController::class, 'index'])->name('topic.index');
    Route::post('topic', [TopicController::class, 'store'])->name('topic.store');
    Route::put('topic/{topic}', [TopicController::class, 'update'])->name('topic.update');
    Route::delete('topic/{topic}', [TopicController::class, 'destroy'])->name('topic.destroy');

    // Topic Category Routes
    Route::post('topic/{topic}/category', [TopicController::class, 'storeCategory'])->name('topic.category.store');
    Route::put('topic/category/{category}', [TopicController::class, 'updateCategory'])->name('topic.category.update');
    Route::delete('topic/category/{category}', [TopicController::class, 'destroyCategory'])->name('topic.category.destroy');

    // Topic Content Routes
    Route::get('topic/category/{category}/detail', [TopicController::class, 'detail'])->name('topic.category.detail');
    Route::post('topic/category/{category}/content', [TopicController::class, 'storeContent'])->name('topic.content.store');
    Route::put('topic/content/{content}', [TopicController::class, 'updateContent'])->name('topic.content.update');
    Route::delete('topic/content/{content}', [TopicController::class, 'destroyContent'])->name('topic.content.destroy');

    // Topic2 Routes (like Book structure)
    Route::get('topic2/{topic2?}', [Topic2Controller::class, 'index'])->name('topic2.index');
    Route::post('topic2/category', [Topic2Controller::class, 'store'])->name('topic2.category.store');
    Route::put('topic2/category/{category}', [Topic2Controller::class, 'update'])->name('topic2.category.update');
    Route::delete('topic2/category/{category}', [Topic2Controller::class, 'destroy'])->name('topic2.category.destroy');

    // Topic2 Item CRUD
    Route::post('topic2/category/{category}/topic2', [Topic2Controller::class, 'storeTopic2'])->name('topic2.store');
    Route::put('topic2/{topic2}', [Topic2Controller::class, 'updateTopic2'])->name('topic2.update');
    Route::delete('topic2/{topic2}', [Topic2Controller::class, 'destroyTopic2'])->name('topic2.destroy');

    // Topic2 Chapter CRUD
    Route::post('topic2/{topic2}/chapter', [Topic2Controller::class, 'storeChapter'])->name('topic2.chapter.store');
    Route::put('topic2/chapter/{chapter}', [Topic2Controller::class, 'updateChapter'])->name('topic2.chapter.update');
    Route::delete('topic2/chapter/{chapter}', [Topic2Controller::class, 'destroyChapter'])->name('topic2.chapter.destroy');

    // Topic2 Content Page
    Route::get('topic2/chapter/{chapter}/content', [Topic2Controller::class, 'showContent'])->name('topic2.content.show');
    Route::post('topic2/chapter/{chapter}/content', [Topic2Controller::class, 'storeContent'])->name('topic2.content.store');
    Route::put('topic2/content/{content}', [Topic2Controller::class, 'updateContent'])->name('topic2.content.update');
    Route::delete('topic2/content/{content}', [Topic2Controller::class, 'destroyContent'])->name('topic2.content.destroy');

    // Topic3 Routes (like Topic2 but with content categories)
    Route::get('topic3/{topic3?}', [App\Http\Controllers\Topic3Controller::class, 'index'])->name('topic3.index');
    Route::post('topic3/category', [App\Http\Controllers\Topic3Controller::class, 'store'])->name('topic3.category.store');
    Route::put('topic3/category/{category}', [App\Http\Controllers\Topic3Controller::class, 'update'])->name('topic3.category.update');
    Route::delete('topic3/category/{category}', [App\Http\Controllers\Topic3Controller::class, 'destroy'])->name('topic3.category.destroy');

    // Topic3 Item CRUD
    Route::post('topic3/category/{category}/topic3', [App\Http\Controllers\Topic3Controller::class, 'storeTopic3'])->name('topic3.store');
    Route::put('topic3/{topic3}', [App\Http\Controllers\Topic3Controller::class, 'updateTopic3'])->name('topic3.update');
    Route::delete('topic3/{topic3}', [App\Http\Controllers\Topic3Controller::class, 'destroyTopic3'])->name('topic3.destroy');

    // Topic3 Chapter CRUD
    Route::post('topic3/{topic3}/chapter', [App\Http\Controllers\Topic3Controller::class, 'storeChapter'])->name('topic3.chapter.store');
    Route::put('topic3/chapter/{chapter}', [App\Http\Controllers\Topic3Controller::class, 'updateChapter'])->name('topic3.chapter.update');
    Route::delete('topic3/chapter/{chapter}', [App\Http\Controllers\Topic3Controller::class, 'destroyChapter'])->name('topic3.chapter.destroy');

    // Topic3 Content Page
    Route::get('topic3/chapter/{chapter}/content', [App\Http\Controllers\Topic3Controller::class, 'showContent'])->name('topic3.content.show');
    Route::post('topic3/chapter/{chapter}/content', [App\Http\Controllers\Topic3Controller::class, 'storeContent'])->name('topic3.content.store');
    Route::put('topic3/content/{content}', [App\Http\Controllers\Topic3Controller::class, 'updateContent'])->name('topic3.content.update');
    Route::delete('topic3/content/{content}', [App\Http\Controllers\Topic3Controller::class, 'destroyContent'])->name('topic3.content.destroy');
    Route::post('topic3/content/bulk-assign-category', [App\Http\Controllers\Topic3Controller::class, 'bulkAssignCategory'])->name('topic3.content.bulk-assign-category');

    // Topic3 Content Category CRUD
    Route::post('topic3/content-category', [App\Http\Controllers\Topic3Controller::class, 'storeContentCategory'])->name('topic3.content-category.store');
    Route::put('topic3/content-category/{category}', [App\Http\Controllers\Topic3Controller::class, 'updateContentCategory'])->name('topic3.content-category.update');
    Route::delete('topic3/content-category/{category}', [App\Http\Controllers\Topic3Controller::class, 'destroyContentCategory'])->name('topic3.content-category.destroy');

    // Book Routes
    Route::get('book/{book?}', [BookCategoryController::class, 'index'])->name('book.index');
    Route::post('book/category', [BookCategoryController::class, 'store'])->name('book.category.store');
    Route::put('book/category/{category}', [BookCategoryController::class, 'update'])->name('book.category.update');
    Route::delete('book/category/{category}', [BookCategoryController::class, 'destroy'])->name('book.category.destroy');

    // Book CRUD
    Route::post('book/category/{category}/book', [BookCategoryController::class, 'storeBook'])->name('book.store');
    Route::put('book/{book}', [BookCategoryController::class, 'updateBook'])->name('book.update');
    Route::delete('book/{book}', [BookCategoryController::class, 'destroyBook'])->name('book.destroy');

    // Book Detail Page
    Route::get('book/{book}/detail', [BookCategoryController::class, 'showBook'])->name('book.show');

    // Book Chapter CRUD
    Route::post('book/{book}/chapter', [BookCategoryController::class, 'storeChapter'])->name('book.chapter.store');
    Route::put('book/chapter/{chapter}', [BookCategoryController::class, 'updateChapter'])->name('book.chapter.update');
    Route::delete('book/chapter/{chapter}', [BookCategoryController::class, 'destroyChapter'])->name('book.chapter.destroy');

    // Book Content Page
    Route::get('book/chapter/{chapter}/content', [BookCategoryController::class, 'showContent'])->name('book.content.show');
    Route::post('book/chapter/{chapter}/content', [BookCategoryController::class, 'storeContent'])->name('book.content.store');
    Route::put('book/content/{content}', [BookCategoryController::class, 'updateContent'])->name('book.content.update');
    Route::delete('book/content/{content}', [BookCategoryController::class, 'destroyContent'])->name('book.content.destroy');

    // Community Playlist Routes
    Route::get('community-playlist', [App\Http\Controllers\CommunityPlaylistController::class, 'index'])->name('community-playlist.index');
    Route::get('community-playlist/{playlist}', [App\Http\Controllers\CommunityPlaylistController::class, 'show'])->name('community-playlist.show');
    Route::put('community-playlist/{playlist}', [App\Http\Controllers\CommunityPlaylistController::class, 'update'])->name('community-playlist.update');
    Route::post('community-playlist/{playlist}/toggle-pin', [App\Http\Controllers\CommunityPlaylistController::class, 'togglePin'])->name('community-playlist.toggle-pin');

    // Menu Mobile Routes
    Route::get('menu-mobile', [App\Http\Controllers\MenuMobileController::class, 'index'])->name('menu-mobile.index');
    Route::post('menu-mobile/{menu}/toggle-status', [App\Http\Controllers\MenuMobileController::class, 'toggleStatus'])->name('menu-mobile.toggle-status');

    // Keyword Routes
    Route::get('keyword', [App\Http\Controllers\KeywordController::class, 'index'])->name('keyword.index');
    Route::post('keyword', [App\Http\Controllers\KeywordController::class, 'store'])->name('keyword.store');
    Route::put('keyword/{keyword}', [App\Http\Controllers\KeywordController::class, 'update'])->name('keyword.update');
    Route::delete('keyword/{keyword}', [App\Http\Controllers\KeywordController::class, 'destroy'])->name('keyword.destroy');

    // Keyword Category Routes
    Route::post('keyword/category', [App\Http\Controllers\KeywordController::class, 'storeCategory'])->name('keyword.category.store');
    Route::put('keyword/category/{category}', [App\Http\Controllers\KeywordController::class, 'updateCategory'])->name('keyword.category.update');
    Route::delete('keyword/category/{category}', [App\Http\Controllers\KeywordController::class, 'destroyCategory'])->name('keyword.category.destroy');
});

require __DIR__.'/settings.php';
