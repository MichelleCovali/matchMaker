<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\University;
use App\Models\Course;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

class UniversityScraperController extends Controller
{
    /**
     * Scrape Hanze University programs.
     */
    public function scrapeHanze()
    {
        try {
            $university = University::updateOrCreate(
                ['slug' => 'hanze'],
                [
                    'name' => 'Hanze University of Applied Sciences',
                    'website' => 'https://www.hanze.nl',
                    'city' => 'Groningen',
                ]
            );

            $coursesProcessed = 0;
            $errors = [];

            // Fetch the first page to determine totalPages
            $allPrograms = [];
            $page = 1;
            $params = [
                'language' => 'en',
                'overview' => 1,
                'mountalias' => 'hanze-en',
                'page' => $page
            ];
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest'
            ])->get('https://www.hanze.nl/services/education-facetednavigation', $params);
            
            if (!$response->successful()) {
                $errors[] = "Failed to fetch Hanze programs: " . $response->status();
            }
            $data = $response->json();
            $totalPages = $data['totalPages'] ?? 1;
            if (isset($data['results']) && is_array($data['results'])) {
                $allPrograms = array_merge($allPrograms, $data['results']);
            }
            // Fetch remaining pages if any
            for ($page = 2; $page <= $totalPages; $page++) {
                $params['page'] = $page;
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest'
                ])->get('https://www.hanze.nl/services/education-facetednavigation', $params);
                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['results']) && is_array($data['results'])) {
                        $allPrograms = array_merge($allPrograms, $data['results']);
                    }
                } else {
                    $errors[] = "Failed to fetch Hanze programs page $page: " . $response->status();
                }
            }
            // Now process all programs
            if (count($allPrograms) > 0) {
                foreach ($allPrograms as $program) {
                    try {
                        $title = $program['title'] ?? null;
                        $type = null;
                        $url = isset($program['url']) ? 'https://www.hanze.nl' . $program['url'] : null;
                        // Extract program type from labels
                        if (isset($program['labels']) && is_array($program['labels'])) {
                            foreach ($program['labels'] as $label) {
                                if (in_array($label, ['Bachelor', 'Master', 'PhD'])) {
                                    $type = $label;
                                    break;
                                }
                            }
                        }
                        if ($title && $type) {
                            Course::updateOrCreate(
                                [
                                    'university_id' => $university->id,
                                    'title' => $title
                                ],
                                [
                                    'type' => $type,
                                    'location' => 'Groningen',
                                    'url' => $url
                                ]
                            );
                            $coursesProcessed++;
                        } else {
                            $errors[] = "Missing title or type for program: " . ($title ?? 'unknown');
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Error processing program: " . $e->getMessage();
                    }
                }
            } else {
                $errors[] = "No programs found in the API response";
            }
            
            // Get all programs for this university
            $programs = Course::where('university_id', $university->id)->get();
            
            // Return the view with the data
            return view('universities.programs', [
                'university' => $university,
                'programs' => $programs,
                'stats' => [
                    'courses_processed' => $coursesProcessed,
                    'pages_scraped' => $totalPages,
                    'errors' => $errors
                ]
            ]);
            
        } catch (\Exception $e) {
            // Return the view with error information
            return view('universities.programs', [
                'university' => $university ?? null,
                'programs' => collect([]),
                'stats' => [
                    'courses_processed' => 0,
                    'pages_scraped' => 0,
                    'errors' => [$e->getMessage()]
                ]
            ]);
        }
    }

    /**
     * Scrape NHL Stenden University programs.
     */
    public function scrapeNHLStenden()
    {
        try {
            $university = University::updateOrCreate(
                ['slug' => 'nhl-stenden'],
                [
                    'name' => 'NHL Stenden University of Applied Sciences',
                    'website' => 'https://www.nhlstenden.com',
                    'city' => 'Leeuwarden',
                ]
            );

            $coursesProcessed = 0;
            $errors = [];
            
            // Process both pages explicitly
            for ($currentPage = 0; $currentPage <= 1; $currentPage++) {
                // Fetch the courses page with pagination
                $response = Http::get("https://www.nhlstenden.com/en/courses?page={$currentPage}");
                
                if (!$response->successful()) {
                    $errors[] = "Failed to fetch page {$currentPage}: " . $response->status();
                    continue;
                }

                $html = $response->body();
                
                // Create a DOM parser
                $dom = new \DOMDocument();
                libxml_use_internal_errors(true); // Suppress HTML5 errors
                @$dom->loadHTML($html);
                libxml_clear_errors();
                
                $xpath = new \DOMXPath($dom);
                
                // Find all course nodes - updated to find the main course container
                $courseNodes = $xpath->query("//div[contains(@class, 'node__content')][.//h3[contains(@class, 'node__title')]]");
                
                if ($courseNodes->length === 0) {
                    $errors[] = "No courses found on page {$currentPage}";
                    continue;
                }
                
                foreach ($courseNodes as $node) {
                    try {
                        // Extract course title
                        $titleNode = $xpath->query(".//h3[contains(@class, 'node__title')]//span[contains(@class, 'field--name-title')]", $node)->item(0);
                        $title = $titleNode ? trim($titleNode->textContent) : null;
                        
                        // Extract course type - updated selector to find the education level in the first content row
                        $typeNode = $xpath->query(".//div[contains(@class, 'node__content-row')][1]//div[contains(@class, 'field--name-field-education-level')]", $node)->item(0);
                        $type = $typeNode ? trim($typeNode->textContent) : null;
                        
                        // Extract location(s)
                        $locationNodes = $xpath->query(".//div[contains(@class, 'field--name-field-locations')]//div[contains(@class, 'field__items')]/div", $node);
                        $locations = [];
                        foreach ($locationNodes as $locationNode) {
                            $locations[] = trim($locationNode->textContent);
                        }
                        $location = implode(', ', $locations);
                        
                        // Debug logging
                        if (!$title || !$type) {
                            $errors[] = sprintf(
                                "Debug - Page %d: Title: '%s', Type: '%s', HTML: %s",
                                $currentPage,
                                $title ?? 'null',
                                $type ?? 'null',
                                $node->ownerDocument->saveHTML($node)
                            );
                        }
                        
                        if ($title && $type) {
                            Course::updateOrCreate(
                                [
                                    'university_id' => $university->id,
                                    'title' => $title
                                ],
                                [
                                    'type' => $type,
                                    'location' => $location,
                                    'url' => null
                                ]
                            );
                            $coursesProcessed++;
                        } else {
                            $errors[] = "Missing title or type for a course on page {$currentPage}";
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Error processing course on page {$currentPage}: " . $e->getMessage();
                    }
                }
            }
            
            // Get all programs for this university
            $programs = Course::where('university_id', $university->id)->get();
            
            // Return the view with the data
            return view('universities.programs', [
                'university' => $university,
                'programs' => $programs,
                'stats' => [
                    'courses_processed' => $coursesProcessed,
                    'pages_scraped' => 2, // We know there are exactly 2 pages
                    'errors' => $errors
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while scraping NHL Stenden',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Scrape Windesheim University programs.
     */
    public function scrapeWindesheim()
    {
        try {
        $university = University::firstOrCreate(
            ['slug' => 'windesheim'],
            [
                'name' => 'Windesheim University of Applied Sciences',
                'city' => 'Zwolle',
                'website' => 'https://www.windesheim.nl'
            ]
        );

            $programs = [];
            $page = 1;
            $hasMorePages = true;
            $errors = [];
            $coursesProcessed = 0;

            while ($hasMorePages) {
                try {
                    // First, get the main programs page to extract the program links
                    $response = Http::withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                        'Accept-Language' => 'en-US,en;q=0.5',
                    ])->get('https://www.windesheim.nl/opleidingen');

                    if (!$response->successful()) {
                        throw new \Exception("Failed to fetch programs page: " . $response->status());
                    }

                    $html = $response->body();
                    $errors[] = "Got HTML response, length: " . strlen($html);
                    
                    // Debug: Save the HTML to a file for inspection
                    file_put_contents(storage_path('windesheim.html'), $html);
                    $errors[] = "Saved HTML to windesheim.html for inspection";

                    $dom = new \DOMDocument();
                    libxml_use_internal_errors(true); // Suppress HTML5 errors
                    @$dom->loadHTML($html);
                    libxml_clear_errors();
                    
                    $xpath = new \DOMXPath($dom);

                    // Try different selectors and log what we find
                    $selectors = [
                        "//li[contains(@class, 'card--study')]",
                        "//div[contains(@class, 'card--study')]",
                        "//div[contains(@class, 'program-card')]",
                        "//div[contains(@class, 'program-item')]",
                        "//div[contains(@class, 'card')]//h2[contains(@class, 'title')]"
                    ];

                    foreach ($selectors as $selector) {
                        $nodes = $xpath->query($selector);
                        $errors[] = "Selector '$selector' found " . $nodes->length . " nodes";
                        if ($nodes->length > 0) {
                            $errors[] = "First node HTML: " . $dom->saveHTML($nodes->item(0));
                        }
                    }

                    // Find all program cards using the correct selector
                    $programCards = $xpath->query("//li[contains(@class, 'card--study')]");
                    
                    if ($programCards->length === 0) {
                        $errors[] = "No program cards found with primary selector";
                        // Try alternative selector
                        $programCards = $xpath->query("//div[contains(@class, 'card--study')]");
                    }

                    $errors[] = "Found " . $programCards->length . " program cards";

                    foreach ($programCards as $card) {
                        try {
                            // Get the program title
                            $titleNode = $xpath->query(".//h2[contains(@class, 'title')]", $card)->item(0);
                            if (!$titleNode) {
                                $errors[] = "No title found in card: " . $dom->saveHTML($card);
                                continue;
                            }
                            $title = trim($titleNode->textContent);
                            $errors[] = "Found title: " . $title;

                            // Get the program type
                            $type = null;
                            $typeNodes = $xpath->query(".//ul[contains(@class, 'list--icons')]/li", $card);
                            foreach ($typeNodes as $typeNode) {
                                $text = trim($typeNode->textContent);
                                $errors[] = "Checking type text: " . $text;
                                if (strpos($text, 'Bachelor') !== false) {
                                    $type = 'Bachelor';
                                    break;
                                } elseif (strpos($text, 'Master') !== false) {
                                    $type = 'Master';
                                    break;
                                } elseif (strpos($text, 'Post-hbo') !== false) {
                                    $type = 'Post-hbo';
                                    break;
                                }
                            }

                            // Get the program URL
                            $urlNode = $xpath->query(".//a[contains(@href, '/opleidingen/')]", $card)->item(0);
                            $url = $urlNode ? 'https://www.windesheim.nl' . $urlNode->getAttribute('href') : null;

                            if ($title && $type) {
                                Course::updateOrCreate(
                                    [
                                        'university_id' => $university->id,
                                        'title' => $title
                                    ],
                                    [
                                        'type' => $type,
                                        'location' => 'Zwolle',
                                        'url' => $url
                                    ]
                                );
                                $coursesProcessed++;
                                $errors[] = "Successfully added program: " . $title;
                            } else {
                                $errors[] = "Missing title or type for program: " . ($title ?? 'unknown');
                            }
                        } catch (\Exception $e) {
                            $errors[] = "Error processing program card: " . $e->getMessage();
                            continue;
                        }
                    }

                    $hasMorePages = false; // We're getting all programs in one go
                } catch (\Exception $e) {
                    $errors[] = "Error scraping Windesheim page {$page}: " . $e->getMessage();
                    break;
                }
            }

            // Get all programs for this university
            $programs = Course::where('university_id', $university->id)->get();
            
            // Return the view with the data
            return view('universities.programs', [
                'university' => $university,
                'programs' => $programs,
                'stats' => [
                    'courses_processed' => $coursesProcessed,
                    'pages_scraped' => $page,
                    'errors' => $errors
                ]
            ]);
            
        } catch (\Exception $e) {
            // Return the view with error information
            return view('universities.programs', [
                'university' => $university ?? null,
                'programs' => collect([]),
                'stats' => [
                    'courses_processed' => 0,
                    'pages_scraped' => 0,
                    'errors' => [$e->getMessage()]
                ]
            ]);
        }
    }

    /**
     * Scrape University of Groningen programs.
     */
    public function scrapeRUG()
    {
        try {
            $university = University::updateOrCreate(
                ['slug' => 'rug'],
                [
                    'name' => 'University of Groningen',
                    'website' => 'https://www.rug.nl',
                    'city' => 'Groningen',
                ]
            );

            $coursesProcessed = 0;
            $errors = [];

            // Scrape Bachelor's programs
            $bachelorResponse = Http::get('https://www.rug.nl/bachelors/alphabet');
            if (!$bachelorResponse->successful()) {
                $errors[] = "Failed to fetch bachelor's programs: " . $bachelorResponse->status();
            } else {
                $html = $bachelorResponse->body();
                $dom = new \DOMDocument();
                libxml_use_internal_errors(true);
                @$dom->loadHTML($html);
                libxml_clear_errors();
                
                $xpath = new \DOMXPath($dom);
                
                // Find all bachelor programs - updated selector to match the actual HTML structure
                $programNodes = $xpath->query("//ul[contains(@class, 'rug-list--bullets')]/li[contains(@class, 'rug-list--bullets__item')]");
                
                // Keep track of processed titles to avoid duplicates
                $processedTitles = [];
                
                foreach ($programNodes as $node) {
                    try {
                        // Skip if it's a header or empty
                        if ($xpath->query(".//h2", $node)->length > 0) {
                            continue;
                        }

                        // First try to get the program from the link
                        $titleNode = $xpath->query(".//div/a", $node)->item(0);
                        $title = $titleNode ? trim($titleNode->textContent) : null;
                        $url = $titleNode ? $titleNode->getAttribute('href') : null;
                        
                        // If no link found, try to get the text directly from the div
                        if (!$title) {
                            $titleNode = $xpath->query(".//div", $node)->item(0);
                            $title = $titleNode ? trim($titleNode->textContent) : null;
                        }
                        
                        // Skip if it's the "Create your own brochure" link or empty
                        if ($title === 'Create your own brochure!' || 
                            $title === 'Stel je eigen brochure samen!' || 
                            empty($title)) {
                            continue;
                        }

                        // Check if this is a specialization (has "Profile" text)
                        if (strpos($title, 'Profile') === 0) {
                            $title = trim(substr($title, 7)); // Remove "Profile" prefix
                        }
                        
                        // Skip if we've already processed this title
                        if (in_array($title, $processedTitles)) {
                            continue;
                        }
                        
                        // Add to processed titles
                        $processedTitles[] = $title;
                        
                        if ($title) {
                            Course::updateOrCreate(
                                [
                                    'university_id' => $university->id,
                                    'title' => $title
                                ],
                                [
                                    'type' => 'Bachelor',
                                    'location' => 'Groningen',
                                    'url' => $url
                                ]
                            );
                            $coursesProcessed++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Error processing bachelor program: " . $e->getMessage();
                    }
                }
            }

            // Scrape Master's programs
            $masterResponse = Http::get('https://www.rug.nl/masters/alphabetical');
            if (!$masterResponse->successful()) {
                $errors[] = "Failed to fetch master's programs: " . $masterResponse->status();
            } else {
                $html = $masterResponse->body();
                $dom = new \DOMDocument();
                libxml_use_internal_errors(true);
                @$dom->loadHTML($html);
                libxml_clear_errors();
                
                $xpath = new \DOMXPath($dom);
                
                // Find all master programs - updated selector to match the actual HTML structure
                $programNodes = $xpath->query("//ul[contains(@class, 'rug-list--bullets')]/li[contains(@class, 'rug-list--bullets__item')]");
                
                // Keep track of processed titles to avoid duplicates
                $processedTitles = [];
                
                foreach ($programNodes as $node) {
                    try {
                        // Skip if it's a header or empty
                        if ($xpath->query(".//h2", $node)->length > 0) {
                            continue;
                        }

                        // First try to get the program from the link
                        $titleNode = $xpath->query(".//div/a", $node)->item(0);
                        if (!$titleNode) {
                            $titleNode = $xpath->query(".//p/a", $node)->item(0);
                        }
                        $title = $titleNode ? trim($titleNode->textContent) : null;
                        $url = $titleNode ? $titleNode->getAttribute('href') : null;
                        
                        // If no link found, try to get the text directly from the div
                        if (!$title) {
                            $titleNode = $xpath->query(".//div", $node)->item(0);
                            $title = $titleNode ? trim($titleNode->textContent) : null;
                        }
                        
                        // Skip if it's the "Create your own brochure" link or empty
                        if ($title === 'Create your own brochure!' || 
                            $title === 'Stel je eigen brochure samen!' || 
                            empty($title)) {
                            continue;
                        }

                        // Check if this is a specialization (has "Profile" text)
                        if (strpos($title, 'Profile') === 0) {
                            $title = trim(substr($title, 7)); // Remove "Profile" prefix
                        }
                        
                        // Skip if we've already processed this title
                        if (in_array($title, $processedTitles)) {
                            continue;
                        }
                        
                        // Add to processed titles
                        $processedTitles[] = $title;
                        
                        if ($title) {
                            Course::updateOrCreate(
                                [
                                    'university_id' => $university->id,
                                    'title' => $title
                                ],
                                [
                                    'type' => 'Master',
                                    'location' => 'Groningen',
                                    'url' => $url
                                ]
                            );
                            $coursesProcessed++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Error processing master program: " . $e->getMessage();
                    }
                }
            }
            
            // Get all programs for this university
            $programs = Course::where('university_id', $university->id)->get();
            
            // Return the view with the data
            return view('universities.programs', [
                'university' => $university,
                'programs' => $programs,
                'stats' => [
                    'courses_processed' => $coursesProcessed,
                    'pages_scraped' => 2, // Bachelor and Master pages
                    'errors' => [] // Don't show errors in the view
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while scraping University of Groningen',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Scrape Alfa College programs.
     */
    public function scrapeAlfaCollege()
    {
        try {
            // Increase execution time limit to 5 minutes
            set_time_limit(300);

            // Create or find the university record
            $university = University::firstOrCreate(
                ['name' => 'Alfa College'],
                [
                    'city' => 'Groningen',
                    'slug' => 'alfacollege',
                    'website' => 'https://www.alfa-college.nl'
                ]
            );

            $coursesProcessed = 0;
            $errors = [];
            $pagesScraped = 0;

            // Clear existing courses
            $university->courses()->delete();

            // Set headers to mimic browser request
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'application/json',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Referer' => 'https://www.alfa-college.nl/mbo-opleidingen'
            ];

            // First request to get total pages
            $response = Http::withHeaders($headers)
                ->get('https://www.alfa-college.nl/api/AlfaCollege/FacetedSearch/v1/get/4d00cd88-120e-4a49-b5b8-0f1e10d3205e', [
                    'q' => '',
                    'currentPage' => 1
                ]);

            if (!$response->successful()) {
                throw new \Exception("Failed to fetch initial page: " . $response->status());
            }

            $data = $response->json();
            $totalPages = $data['totalPages'] ?? 1;
            $batchSize = 5; // Process 5 pages at a time
            
            // Process pages in batches
            for ($batchStart = 1; $batchStart <= $totalPages; $batchStart += $batchSize) {
                $batchEnd = min($batchStart + $batchSize - 1, $totalPages);
                
                for ($page = $batchStart; $page <= $batchEnd; $page++) {
                    try {
                        $response = Http::withHeaders($headers)
                            ->get('https://www.alfa-college.nl/api/AlfaCollege/FacetedSearch/v1/get/4d00cd88-120e-4a49-b5b8-0f1e10d3205e', [
                                'q' => '',
                                'currentPage' => $page
                            ]);

                        if (!$response->successful()) {
                            $errors[] = "Failed to fetch page {$page}: " . $response->status();
                            continue;
                        }

                        $data = $response->json();
                        
                        if (isset($data['items']) && is_array($data['items'])) {
                            foreach ($data['items'] as $item) {
                                try {
                                    // Create course record
                                    $university->courses()->create([
                                        'title' => $item['title'] ?? null,
                                        'type' => $item['type'] ?? null,
                                        'education_level' => $item['educationLevel'] ?? null,
                                        'location' => $item['location'] ?? null,
                                        'description' => $item['lead'] ?? null,
                                        'url' => $item['url'] ? 'https://www.alfa-college.nl' . $item['url'] : null,
                                        'discipline' => $item['discipline'] ?? null,
                                        'duration' => $item['duration'] ?? null,
                                        'town' => $item['town'] ?? null
                                    ]);
                                    $coursesProcessed++;
                                } catch (\Exception $e) {
                                    $errors[] = "Error creating course on page {$page}: " . $e->getMessage();
                                    \Log::error('Error creating course: ' . $e->getMessage());
                                    continue;
                                }
                            }
                        } else {
                            $errors[] = "No items found on page {$page}";
                        }

                        $pagesScraped++;
                        
                        // Add a small delay between requests to be nice to the server
                        if ($page < $batchEnd) {
                            usleep(250000); // 0.25 second delay
                        }

                    } catch (\Exception $e) {
                        $errors[] = "Error processing page {$page}: " . $e->getMessage();
                    }
                }

                // Add a longer delay between batches
                if ($batchEnd < $totalPages) {
                    usleep(1000000); // 1 second delay between batches
                }
            }

            // Get all programs for this university
            $programs = Course::where('university_id', $university->id)->get();
            
            // Return the view with the data
            return view('universities.programs', [
                'university' => $university,
                'programs' => $programs,
                'stats' => [
                    'courses_processed' => $coursesProcessed,
                    'pages_scraped' => $pagesScraped,
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error scraping Alfa College: ' . $e->getMessage());
            return view('universities.programs', [
                'university' => $university ?? null,
                'programs' => collect([]),
                'stats' => [
                    'courses_processed' => 0,
                    'pages_scraped' => 0,
                    'errors' => [$e->getMessage()]
                ]
            ]);
        }
    }

    /**
     * Scrape Open University programs.
     */
    public function scrapeOU()
    {
        try {
            // Increase execution time limit to 5 minutes
            set_time_limit(300);

            $university = University::firstOrCreate([
                'name' => 'Open University',
                'city' => 'Heerlen',
                'slug' => 'ou',
                'website' => 'https://www.ou.nl'
            ]);

            if (!$university) {
                throw new \Exception("Failed to create or find Open University record");
            }

            $programs = [];
            $errors = [];
            $coursesProcessed = 0;
            $pagesScraped = 0;
            $totalPages = 42; // 830 items / 20 items per page = 42 pages
            $batchSize = 10; // Process 10 pages at a time

            // Clear existing programs for this university
            Course::where('university_id', $university->id)->delete();

            // Process pages in batches
            for ($batchStart = 1; $batchStart <= $totalPages; $batchStart += $batchSize) {
                $batchEnd = min($batchStart + $batchSize - 1, $totalPages);
                
                for ($page = $batchStart; $page <= $batchEnd; $page++) {
                    try {
                        // Get the programs page with pagination
                        $response = Http::withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                            'Accept-Language' => 'en-US,en;q=0.5',
                        ])->get('https://www.ou.nl/opleiding-overzicht', [
                            'page' => $page,
                            'items_per_page' => 20
                        ]);

                        if (!$response->successful()) {
                            $errors[] = "Failed to fetch page {$page}: " . $response->status();
                            continue;
                        }

                        $html = $response->body();
                        
                        // Save HTML for debugging on first page
                        if ($page === 1) {
                            file_put_contents(storage_path('ou.html'), $html);
                        }

                        // Create a new crawler instance
                        $crawler = new Crawler($html);

                        // Find all program items
                        $crawler->filter('.list__item__container')->each(function ($node) use ($university, &$programs, &$coursesProcessed, &$errors) {
                            try {
                                // Get the program title
                                $title = $node->filter('.list__item__title')->text();
                                
                                // Get the program type and credits
                                $typeAndCredits = $node->filter('.list__item__description')->text();
                                
                                // Parse type and credits
                                $type = null;
                                $credits = null;
                                if (preg_match('/(Bachelor|Master|Premaster|Cursus|Contractonderwijs|Korte studie|Training|Minor)\s*\|\s*(\d+)\s*EC/', $typeAndCredits, $matches)) {
                                    $type = $matches[1];
                                    $credits = $matches[2];
                                }
                                
                                // Get the description
                                $description = null;
                                try {
                                    $description = trim($node->filter('.list__item__content')->text());
                                } catch (\Exception $e) {
                                    $errors[] = "Error getting description: " . $e->getMessage();
                                }
                                
                                // Get the URL
                                $url = null;
                                try {
                                    $urlNode = $node->filter('a')->first();
                                    if ($urlNode->count() > 0) {
                                        $url = $urlNode->attr('href');
                                        if ($url && !str_starts_with($url, 'http')) {
                                            $url = 'https://www.ou.nl' . $url;
                                        }
                                    }
                                } catch (\Exception $e) {
                                    $errors[] = "Error getting URL: " . $e->getMessage();
                                }
                                
                                if ($title && $type) {
                                    Course::create([
                                        'university_id' => $university->id,
                                        'title' => $title,
                                        'type' => $type,
                                        'location' => 'Heerlen',
                                        'url' => $url,
                                        'education_level' => $credits ? $credits . ' EC' : null,
                                        'description' => $description
                                    ]);
                                    $coursesProcessed++;
                                }
                            } catch (\Exception $e) {
                                $errors[] = "Error processing program card: " . $e->getMessage();
                            }
                        });

                        $pagesScraped++;
                        
                        // Add a small delay between requests to be nice to the server
                        if ($page < $batchEnd) {
                            usleep(500000); // 0.5 second delay
                        }

                    } catch (\Exception $e) {
                        $errors[] = "Error scraping page {$page}: " . $e->getMessage();
                    }
                }
            }

            // Get all programs for this university
            $programs = Course::where('university_id', $university->id)->get();

            return view('universities.programs', [
                'university' => $university,
                'programs' => $programs,
                'stats' => [
                    'courses_processed' => $coursesProcessed,
                    'pages_scraped' => $pagesScraped,
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error scraping Open University: ' . $e->getMessage());
            return view('universities.programs', [
                'university' => null,
                'programs' => collect([]),
                'stats' => [
                    'courses_processed' => 0,
                    'pages_scraped' => 0,
                    'errors' => [$e->getMessage()]
                ]
            ]);
        }
    }

    /**
     * Scrape Drenthe College programs.
     */
    public function scrapeDrentheCollege()
    {
        try {
            // Increase execution time limit to 5 minutes
            set_time_limit(300);

            $university = University::firstOrCreate([
                'name' => 'Drenthe College',
                'city' => 'Emmen',
                'slug' => 'drenthe-college',
                'website' => 'https://www.drenthecollege.nl'
            ]);

            if (!$university) {
                throw new \Exception("Failed to create or find Drenthe College record");
            }

            $programs = [];
            $errors = [];
            $coursesProcessed = 0;
            $pagesScraped = 0;

            // Clear existing programs for this university
            Course::where('university_id', $university->id)->delete();

            // Get the main programs page
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ])->get('https://www.drenthecollege.nl/opleidingen/');

            if (!$response->successful()) {
                throw new \Exception("Failed to fetch programs page: " . $response->status());
            }

            $html = $response->body();
            
            // Save HTML for debugging
            file_put_contents(storage_path('drenthecollege.html'), $html);

            // Create a new crawler instance
            $crawler = new Crawler($html);

            // Find all program items
            $crawler->filter('.navigationItem')->each(function ($node) use ($university, &$programs, &$coursesProcessed, &$errors) {
                try {
                    // Get the program title
                    $titleNode = $node->filter('.btn-text');
                    if ($titleNode->count() === 0) {
                        return;
                    }
                    
                    $title = trim($titleNode->text());
                    
                    // Skip navigation items that aren't programs
                    if (in_array($title, ['Alle opleidingen', 'Bekijk alle opleidingen', 'Wat is BOL?', 'Wat is BBL?'])) {
                        return;
                    }
                    
                    // Get the URL
                    $url = null;
                    try {
                        $urlNode = $node->filter('a')->first();
                        if ($urlNode->count() > 0) {
                            $url = $urlNode->attr('href');
                            if ($url && !str_starts_with($url, 'http')) {
                                $url = 'https://www.drenthecollege.nl' . $url;
                            }
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Error getting URL: " . $e->getMessage();
                    }
                    
                    // Determine program type based on URL or title
                    $type = 'MBO';
                    if (strpos($url, '/bol/') !== false || strpos($title, 'BOL') !== false) {
                        $type = 'BOL';
                    } elseif (strpos($url, '/bbl/') !== false || strpos($title, 'BBL') !== false) {
                        $type = 'BBL';
                    }
                    
                    if ($title) {
                        Course::create([
                            'university_id' => $university->id,
                            'title' => $title,
                            'type' => $type,
                            'location' => 'Emmen',
                            'url' => $url,
                            'education_level' => null,
                            'description' => null
                        ]);
                        $coursesProcessed++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error processing program: " . $e->getMessage();
                }
            });

            $pagesScraped++;

            // Get all programs for this university
            $programs = Course::where('university_id', $university->id)->get();

            return view('universities.programs', [
                'university' => $university,
                'programs' => $programs,
                'stats' => [
                    'courses_processed' => $coursesProcessed,
                    'pages_scraped' => $pagesScraped,
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error scraping Drenthe College: ' . $e->getMessage());
            return view('universities.programs', [
                'university' => null,
                'programs' => collect([]),
                'stats' => [
                    'courses_processed' => 0,
                    'pages_scraped' => 0,
                    'errors' => [$e->getMessage()]
                ]
            ]);
        }
    }

    /**
     * Scrape Noorderpoort programs.
     */
    public function scrapeNoorderpoort()
    {
        try {
            // Increase execution time limit to 5 minutes
            set_time_limit(300);

            $university = University::firstOrCreate([
                'name' => 'Noorderpoort',
                'city' => 'Groningen',
                'slug' => 'noorderpoort',
                'website' => 'https://www.noorderpoort.nl'
            ]);

            if (!$university) {
                throw new \Exception("Failed to create or find Noorderpoort record");
            }

            $programs = [];
            $errors = [];
            $coursesProcessed = 0;
            $pagesScraped = 0;

            // Clear existing programs for this university
            Course::where('university_id', $university->id)->delete();

            // Get total number of pages from first request
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Referer' => 'https://noorderpoort.nl/voor-studenten/opleidingen',
                'Sec-Fetch-Site' => 'same-origin',
                'Sec-Fetch-Mode' => 'cors',
                'Sec-Fetch-Dest' => 'empty'
            ])->get('https://noorderpoort.nl/programmes/nl-NL/1199', [
                'page' => 1
            ]);

            if (!$response->successful()) {
                throw new \Exception("Failed to fetch programs: " . $response->status());
            }

            $data = $response->json();
            $totalPages = $data['totalPages'] ?? 1;
            
            // Process all pages
            for ($page = 1; $page <= $totalPages; $page++) {
                try {
                    $response = Http::withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36',
                        'Accept' => 'application/json, text/plain, */*',
                        'Accept-Language' => 'en-US,en;q=0.9',
                        'Referer' => 'https://noorderpoort.nl/voor-studenten/opleidingen',
                        'Sec-Fetch-Site' => 'same-origin',
                        'Sec-Fetch-Mode' => 'cors',
                        'Sec-Fetch-Dest' => 'empty'
                    ])->get('https://noorderpoort.nl/programmes/nl-NL/1199', [
                        'page' => $page
                    ]);

                    if (!$response->successful()) {
                        $errors[] = "Failed to fetch page {$page}: " . $response->status();
                        continue;
                    }

                    $data = $response->json();
                    
                    // Save response for debugging on first page
                    if ($page === 1) {
                        file_put_contents(storage_path('noorderpoort.json'), json_encode($data, JSON_PRETTY_PRINT));
                    }

                    if (isset($data['items']) && is_array($data['items'])) {
                        foreach ($data['items'] as $program) {
                            try {
                                $title = $program['title'] ?? null;
                                $type = null;
                                
                                // Determine program type from tag or title
                                $tag = $program['tag'] ?? '';
                                if (strpos($tag, 'BOL') !== false || strpos($title ?? '', 'BOL') !== false) {
                                    $type = 'BOL';
                                } elseif (strpos($tag, 'BBL') !== false || strpos($title ?? '', 'BBL') !== false) {
                                    $type = 'BBL';
                                } else {
                                    $type = 'MBO';
                                }
                                
                                $location = $program['location'] ?? 'Groningen';
                                $level = $program['level'] ?? null;
                                
                                // Get the URL from the urls array
                                $url = null;
                                if (isset($program['urls']) && is_array($program['urls'])) {
                                    foreach ($program['urls'] as $urlData) {
                                        if (isset($urlData['url'])) {
                                            $url = $urlData['url'];
                                            break;
                                        }
                                    }
                                }
                                
                                if ($url && !str_starts_with($url, 'http')) {
                                    $url = 'https://www.noorderpoort.nl' . $url;
                                }

                                if ($title && $type) {
                                    Course::create([
                                        'university_id' => $university->id,
                                        'title' => $title,
                                        'type' => $type,
                                        'location' => $location,
                                        'url' => $url,
                                        'education_level' => $level,
                                        'description' => null
                                    ]);
                                    $coursesProcessed++;
                                }
                            } catch (\Exception $e) {
                                $errors[] = "Error processing program on page {$page}: " . $e->getMessage();
                            }
                        }
                    } else {
                        $errors[] = "No programs found on page {$page}";
                    }

                    $pagesScraped++;
                    
                    // Add a small delay between requests to be nice to the server
                    if ($page < $totalPages) {
                        usleep(250000); // 0.25 second delay
                    }

                } catch (\Exception $e) {
                    $errors[] = "Error processing page {$page}: " . $e->getMessage();
                }
            }

            // Get all programs for this university
            $programs = Course::where('university_id', $university->id)->get();

            return view('universities.programs', [
                'university' => $university,
                'programs' => $programs,
                'stats' => [
                    'courses_processed' => $coursesProcessed,
                    'pages_scraped' => $pagesScraped,
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error scraping Noorderpoort: ' . $e->getMessage());
            return view('universities.programs', [
                'university' => null,
                'programs' => collect([]),
                'stats' => [
                    'courses_processed' => 0,
                    'pages_scraped' => 0,
                    'errors' => [$e->getMessage()]
                ]
            ]);
        }
    }
}
