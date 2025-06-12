<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\UniversityScraperController;
use App\Models\University;
use App\Models\Course;

class ScrapeAllUniversities extends Command
{
    protected $signature = 'scrape:all';
    protected $description = 'Scrape all universities';

    public function handle()
    {
        $scraper = new UniversityScraperController();
        
        $this->info('Starting to scrape all universities...');
        
        // Create universities first
        $universities = [
            [
                'name' => 'Alfa College',
                'slug' => 'alfacollege',
                'city' => 'Groningen',
                'website' => 'https://www.alfa-college.nl'
            ],
            [
                'name' => 'Drenthe College',
                'slug' => 'drenthe-college',
                'city' => 'Emmen',
                'website' => 'https://www.drenthecollege.nl'
            ],
            [
                'name' => 'Hanze University of Applied Sciences',
                'slug' => 'hanze',
                'city' => 'Groningen',
                'website' => 'https://www.hanze.nl'
            ],
            [
                'name' => 'Noorderpoort',
                'slug' => 'noorderpoort',
                'city' => 'Groningen',
                'website' => 'https://www.noorderpoort.nl'
            ],
            [
                'name' => 'Windesheim University of Applied Sciences',
                'slug' => 'windesheim',
                'city' => 'Zwolle',
                'website' => 'https://www.windesheim.com'
            ]
        ];

        foreach ($universities as $uni) {
            University::firstOrCreate(
                ['slug' => $uni['slug']],
                $uni
            );
        }
        
        // Scrape each university
        $this->info('Scraping Alfa College...');
        $beforeCount = Course::where('university_id', University::where('slug', 'alfacollege')->first()->id)->count();
        $scraper->scrapeAlfaCollege();
        $afterCount = Course::where('university_id', University::where('slug', 'alfacollege')->first()->id)->count();
        $this->info("Alfa College courses: {$beforeCount} -> {$afterCount}");
        
        $this->info('Scraping Drenthe College...');
        $beforeCount = Course::where('university_id', University::where('slug', 'drenthe-college')->first()->id)->count();
        $scraper->scrapeDrentheCollege();
        $afterCount = Course::where('university_id', University::where('slug', 'drenthe-college')->first()->id)->count();
        $this->info("Drenthe College courses: {$beforeCount} -> {$afterCount}");
        
        $this->info('Scraping Hanze...');
        $beforeCount = Course::where('university_id', University::where('slug', 'hanze')->first()->id)->count();
        $scraper->scrapeHanze();
        $afterCount = Course::where('university_id', University::where('slug', 'hanze')->first()->id)->count();
        $this->info("Hanze courses: {$beforeCount} -> {$afterCount}");
        
        $this->info('Scraping Noorderpoort...');
        $beforeCount = Course::where('university_id', University::where('slug', 'noorderpoort')->first()->id)->count();
        $scraper->scrapeNoorderpoort();
        $afterCount = Course::where('university_id', University::where('slug', 'noorderpoort')->first()->id)->count();
        $this->info("Noorderpoort courses: {$beforeCount} -> {$afterCount}");

        $this->info('Scraping Windesheim...');
        $beforeCount = Course::where('university_id', University::where('slug', 'windesheim')->first()->id)->count();
        $scraper->scrapeWindesheim();
        $afterCount = Course::where('university_id', University::where('slug', 'windesheim')->first()->id)->count();
        $this->info("Windesheim courses: {$beforeCount} -> {$afterCount}");
        
        
        // Show final counts
        $this->info("\nFinal course counts per university:");
        $universities = University::withCount('courses')->get();
        foreach ($universities as $university) {
            $this->info("{$university->name}: {$university->courses_count} courses");
        }
    }
} 