<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UniversityScraperController;
use App\Models\University;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/scrape/hanze', [UniversityScraperController::class, 'scrapeHanze']);
Route::get('/scrape/nhlstenden', [UniversityScraperController::class, 'scrapeNHLStenden'])->name('scrape.nhlstenden');
Route::get('/scrape/windesheim', [UniversityScraperController::class, 'scrapeWindesheim']);
Route::get('/scrape/rug', [UniversityScraperController::class, 'scrapeRUG']);
Route::get('/scrape/alfacollege', [UniversityScraperController::class, 'scrapeAlfaCollege']);
Route::get('/scrape/ou', [UniversityScraperController::class, 'scrapeOU']);
Route::get('/scrape/drenthe-college', [UniversityScraperController::class, 'scrapeDrentheCollege']);
Route::get('/scrape/noorderpoort', [UniversityScraperController::class, 'scrapeNoorderpoort']);

Route::get('/stats', function() {
    $universities = University::withCount('courses')->get();
    return view('stats', ['universities' => $universities]);
});

Route::get('/test', function() {
    return 'Test route is working!';
});
