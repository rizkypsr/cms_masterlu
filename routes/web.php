<?php

use App\Http\Controllers\AudioCategoryController;
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
    Route::get('video/daftar-isi', [VideoCategoryController::class, 'daftarIsi'])->name('video.daftar-isi');
    Route::post('video/daftar-isi/category', [VideoCategoryController::class, 'storeDaftarIsi'])->name('video.daftar-isi.store');
    Route::get('video/daftar-isi/category/{category}', [VideoCategoryController::class, 'showCategory'])->name('video.daftar-isi.show');
    Route::post('video/daftar-isi/category/{category}/video-category', [VideoCategoryController::class, 'storeVideoCategory'])->name('video.daftar-isi.video-category.store');
    
    // Video - Topik (ID)
    Route::get('video/topik', [VideoCategoryController::class, 'topik'])->name('video.topik');
    Route::post('video/topik/category', [VideoCategoryController::class, 'storeTopik'])->name('video.topik.store');
    Route::get('video/topik/category/{category}', [VideoCategoryController::class, 'showCategory'])->name('video.topik.show');
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
    Route::put('video/subtitle/item/{subtitle}', [VideoCategoryController::class, 'updateSubtitle'])->name('video.subtitle.update');
    Route::delete('video/subtitle/item/{subtitle}', [VideoCategoryController::class, 'destroySubtitle'])->name('video.subtitle.destroy');
    Route::delete('video/subtitle/{video}/all', [VideoCategoryController::class, 'destroyAllSubtitles'])->name('video.subtitle.destroy-all');
    
    // Video Item CRUD
    Route::post('video/category/{category}/video', [VideoCategoryController::class, 'storeVideo'])->name('video.item.store');
    Route::put('video/item/{video}', [VideoCategoryController::class, 'updateVideo'])->name('video.item.update');
    Route::delete('video/item/{video}', [VideoCategoryController::class, 'destroyVideo'])->name('video.item.destroy');

    // Audio - Daftar Isi (CH)
    Route::get('audio/daftar-isi', [AudioCategoryController::class, 'daftarIsi'])->name('audio.daftar-isi');
    Route::post('audio/daftar-isi/category', [AudioCategoryController::class, 'storeDaftarIsi'])->name('audio.daftar-isi.store');
    
    // Audio - Topik (ID)
    Route::get('audio/topik', [AudioCategoryController::class, 'topik'])->name('audio.topik');
    Route::post('audio/topik/category', [AudioCategoryController::class, 'storeTopik'])->name('audio.topik.store');
    
    // Audio - Shared
    Route::put('audio/category/{category}', [AudioCategoryController::class, 'update'])->name('audio.category.update');
    Route::delete('audio/category/{category}', [AudioCategoryController::class, 'destroy'])->name('audio.category.destroy');
});

require __DIR__.'/settings.php';
