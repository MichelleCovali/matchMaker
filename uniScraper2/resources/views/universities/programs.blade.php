<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $university ? $university->name . ' Programs' : 'Programs' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .header {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stats {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .programs {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .program-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .error {
            background: #fee;
            color: #c00;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $university ? $university->name . ' Programs' : 'Programs' }}</h1>
        @if($university)
            <p>Location: {{ $university->city }}</p>
        @endif
    </div>

    <div class="stats">
        <h2>Scraping Statistics</h2>
        <p>Total Programs: {{ $programs->count() }}</p>
        <p>Courses Processed: {{ $stats['courses_processed'] }}</p>
        <p>Pages Scraped: {{ $stats['pages_scraped'] }}</p>
        
        @if(!empty($stats['errors']))
            <div class="error">
                <h3>Errors during scraping:</h3>
                <ul>
                    @foreach($stats['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    @if($programs->count() > 0)
        <div class="programs">
            @foreach($programs as $program)
                <div class="program-card">
                    <h3>{{ $program->title }}</h3>
                    <p><strong>Type:</strong> {{ $program->type }}</p>
                    <p><strong>Location:</strong> {{ $program->location }}</p>
                    @if($program->education_level)
                        <p><strong>Education Level:</strong> {{ $program->education_level }}</p>
                    @endif
                    @if($program->description)
                        <p><strong>Description:</strong> {{ $program->description }}</p>
                    @endif
                    @if($program->url)
                        <p><a href="{{ $program->url }}" target="_blank">View Program</a></p>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="error">
            <p>No programs found.</p>
        </div>
    @endif
</body>
</html> 