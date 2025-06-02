# University Course Scraper

This project scrapes course information from various Dutch universities and stores it in a PostgreSQL database. It provides a web interface to view the scraped data and statistics.

## Prerequisites

Before you begin, ensure you have the following installed:
- PHP 8.1 or higher
- Composer
- PostgreSQL 15 or higher
- Node.js and NPM (for frontend assets)

## Installation

1. Clone the repository:
```bash
git clone [repository-url]
cd uniScraper2
```

2. Install PHP dependencies:
```bash
composer install
```

3. Create a copy of the environment file:
```bash
cp .env.example .env
```

4. Configure your database connection in `.env` to connect to the existing database:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=scraper_universitie
DB_USERNAME=scraper2
DB_PASSWORD=scraper2
```

5. Generate application key:
```bash
php artisan key:generate
```

6. Verify database connection:
```bash
# Test the database connection
psql -U scraper2 -d scraper_universitie -c "SELECT version();"

# List existing tables
psql -U scraper2 -d scraper_universitie -c "\dt"
```

## Populating the Database

The database can be populated in two ways:

### 1. Using the Web Interface

1. Start the Laravel server:
```bash
php artisan serve
```
Note: If port 8000 is in use, the server will automatically try ports 8001, 8002, etc.

2. Access the scraper endpoints in your browser:
- Alfa College: /scrape/drenthe-college
- Hanze: /scrape/hanze
- NHL Stenden: /scrape/nhlstenden
- RUG: /scrape/rug
- Windesheim: /scrape/windesheim
- Open University: /scrape/ou
- Noorderpoort: /scrape/noorderpoort

Each endpoint will:
- Create the university record if it doesn't exist
- Scrape all courses from that university
- Save the courses to the database
- Show a success message with the number of courses scraped

### 2. Using the Command Line

To scrape all universities at once:
```bash
php artisan scrape:all
```

This command will:
- Create university records for all supported universities
- Scrape courses from each university
- Show progress for each university
- Display final course counts

### Verifying the Data

After scraping, you can verify the data in several ways:

1. Check the web interface:
- Visit http://localhost:8000/stats to see course counts per university

2. Query the database directly:
```sql
-- Count courses per university
SELECT u.name, COUNT(c.id) as course_count 
FROM universities u 
LEFT JOIN courses c ON u.id = c.university_id 
GROUP BY u.name;

-- View courses for a specific university
SELECT c.title, c.type, c.location 
FROM courses c 
JOIN universities u ON c.university_id = u.id 
WHERE u.slug = 'hanze';
```

3. Use TablePlus or your preferred database client to browse the data

## Running the Application

1. Start the Laravel development server:
```bash
php artisan serve
```
The server will automatically try ports 8000, 8001, 8002, etc. if the previous port is in use.
You should see output like:
```
Server running on [http://127.0.0.1:8002].
Press Ctrl+C to stop the server
```

2. Access the application:
- Open your browser and go to the URL shown in the terminal (e.g., http://127.0.0.1:8002)
- You should see the application's home page

3. To stop the server:
- Press Ctrl+C in the terminal where the server is running

## Database Structure

### Tables

1. **universities**
   - `id` (bigint, primary key)
   - `name` (varchar) - University name
   - `slug` (varchar, unique) - URL-friendly identifier
   - `website` (varchar) - University website URL
   - `city` (varchar) - City where the university is located
   - `created_at` (timestamp)
   - `updated_at` (timestamp)

2. **courses**
   - `id` (bigint, primary key)
   - `university_id` (bigint, foreign key) - References universities.id
   - `title` (varchar) - Course name
   - `type` (varchar) - Course type (e.g., Bachelor, Master, MBO)
   - `location` (varchar) - Location where the course is offered
   - `url` (varchar, nullable) - Course webpage URL
   - `duration` (varchar, nullable) - Course duration
   - `tuition_fee` (decimal(10,2), nullable) - Course tuition fee
   - `start_date` (varchar, nullable) - Course start date
   - `description` (text, nullable) - Course description
   - `created_at` (timestamp)
   - `updated_at` (timestamp)

### Relationships
- One university has many courses (one-to-many)
- Each course belongs to one university (many-to-one)

## Viewing Data

### Web Interface
- View statistics: http://localhost:8000/stats
- View individual university courses: http://localhost:8000/scrape/[university-slug]

### Database Queries
Common queries you might need:

1. Get all courses for a specific university:
```sql
SELECT * FROM courses WHERE university_id = (SELECT id FROM universities WHERE slug = 'hanze');
```

2. Count courses per university:
```sql
SELECT u.name, COUNT(c.id) as course_count 
FROM universities u 
LEFT JOIN courses c ON u.id = c.university_id 
GROUP BY u.name;
```

3. Find courses by type:
```sql
SELECT c.title, u.name as university 
FROM courses c 
JOIN universities u ON c.university_id = u.id 
WHERE c.type = 'Bachelor';
```

## Development

### Starting the Server
```bash
php artisan serve
```
The server will run on http://localhost:8000 by default. If port 8000 is in use, it will try port 8001, then 8002, etc.

### Database Management
- Use TablePlus or any PostgreSQL client to manage the database
- Database name: scraper_universitie
- Default port: 5432
- Username: scraper2
- Password: scraper2

### Adding New Universities
1. Create a new scraper method in `app/Http/Controllers/UniversityScraperController.php`
2. Add a new route in `routes/web.php`
3. Update the `ScrapeAllUniversities` command if needed

## Troubleshooting

1. **Database Connection Issues**
   - Check PostgreSQL service is running:
     ```bash
     # On macOS
     brew services list
     # If not running, start it:
     brew services start postgresql
     ```
   - Verify database credentials in `.env`
   - Check database connection:
     ```bash
     psql -U scraper2 -d scraper_universitie -c "SELECT version();"
     ```
   - If connection fails, verify:
     - PostgreSQL is running
     - User 'scraper2' has access to the database
     - Database 'scraper_universitie' exists

2. **Scraper Issues**
   - Check university website is accessible
   - Verify network connection
   - Check scraper logs in `storage/logs/laravel.log`

3. **Server Issues**
   - If port 8000 is in use, try: `php artisan serve --port=8001`
   - Check PHP version: `php -v`
   - Verify all dependencies: `composer install`
   - Check if another Laravel server is running:
     ```bash
     # On macOS
     lsof -i :8000
     # Kill the process if needed
     kill -9 <PID>
     ```

## Contributing
1. Create a new branch for your changes
2. Make your changes
3. Test thoroughly
4. Submit a pull request




