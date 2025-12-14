# Auto Blog System 🤖✍️

A fully automated, AI-powered blogging platform built with Laravel 12.x, Livewire, and Tailwind CSS. The system autonomously scrapes trending topics, generates research-backed content using AI (Hugging Face + Gemini), enhances it for SEO, and publishes daily posts.

## 🌟 Features

- **Automated Content Generation**:
  - Scrapes trending topics from Google Trends and Wikipedia.
  - Uses **Hugging Face (GPT-Neo 1.3B)** for initial draft generation.
  - Uses **Google Gemini 1.5 Flash** for optimization, humanization, and HTML formatting.
  - Generates structured content with H1-H6 headings, paragraphs, and tables.
  - Auto-extracts tags and meta descriptions.

- **Smart Scheduling**:
  - Publishes 5 blogs daily on a randomized schedule.
  - Ensures minimum 3.5-hour gaps between posts.
  - Prevents duplicate topics within a 30-day window.

- **Modern UI/UX**:
  - Built with **Tailwind CSS** and **Livewire**.
  - Responsive, mobile-first design.
  - Dynamic Table of Contents (TOC).
  - Clean reading mode with related posts.

- **SEO Optimized**:
  - Automatic Meta Title & Description generation.
  - OpenGraph tags for social sharing.
  - Dynamic XML Sitemap (`/sitemap.xml`).
  - `robots.txt` configuration.
  - Keyword density optimization.

- **Robust Backend**:
  - Admin Dashboard for manual management & generation.
  - Daily SQLite backups with retention policy (last 7 days).
  - Soft deletes for safety.
  - Comprehensive error handling and email notifications.

## 🛠 Tech Stack

- **Framework**: Laravel 11.x (compatible with 12.x structure)
- **Frontend**: Livewire, Blade, Tailwind CSS, Alpine.js
- **Database**: MySQL (Primary), SQLite (Backups)
- **AI Services**: Hugging Face Inference API, Google Gemini API
- **Scraping**: Guzzle, Symfony DomCrawler

## 🚀 Installation & Setup

### 1. Prerequisites
- PHP 8.2+
- Composer
- Node.js & NPM
- MySQL

### 2. Clone & Install
```bash
git clone <repository-url>
cd auto-blog-system
composer install
npm install && npm run build
```

### 3. Environment Configuration
Copy the example environment file and configure it:
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your database and API credentials:
```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=auto_blog
DB_USERNAME=root
DB_PASSWORD=

# AI API Keys (Get free keys from Hugging Face & Google AI Studio)
HUGGINGFACE_API_KEY=your_hf_key_here
GEMINI_API_KEY=your_gemini_key_here

# Mail Configuration (Required for error alerts)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
```

### 4. Database Setup
```bash
php artisan migrate --seed
# This creates the Admin user and default categories
```

### 5. Running the Application
**Recommended Method** (Runs Server, Queues, and Frontend together):
```bash
composer run dev
```
OR individually:
```bash
php artisan serve
php artisan queue:listen
npm run dev
```
Visit `http://127.0.0.1:8000`

## 🔑 Admin Access

- **Login URL**: `/login` or `/admin`
- **Email**: `admin@example.com`
- **Password**: `password`

## 🤖 Usage

### Manual Blog Generation
1. Log in to the Admin Dashboard.
2. Select a category (e.g., Technology, AI) under "Manual Generation".
3. Click **"Generate Blog"**.
   - The system will scrape a trending topic, research it, and generate a post.
   - Process usually takes 30-60 seconds.

### Automated Scheduling
To run the scheduler locally:
```bash
php artisan schedule:work
```
This will trigger the `GenerateDailyBlogs` job which schedules 5 posts throughout the day.

### Testing Real Content (Seeder)
To mass-generate 3 high-quality blogs for testing:
```bash
php artisan db:seed --class=EnhancedContentSeeder
```

## 🧪 Testing

The project includes a comprehensive test suite (21+ tests).
```bash
php artisan test
```

## ⚠️ Edge Cases & Troubleshooting

- **AI Content Too Short**: If API keys are missing or invalid, the system falls back to a mock generation system to ensure the site doesn't break. Check logs (`storage/logs/laravel.log`) for API errors.
- **SSL Certificate Errors**: For local development `verify` is set to `false` in Guzzle clients to avoid certificate issues. Ensure this is enabled for production.
- **Rate Limits**: The system implements exponential backoff (retries) for API calls. If limits are hit, it logs the error and sends an email.

## 📂 Directory Structure

- `app/Services/`: Core logic for AI (`AIService`) and Scraping (`ScrapingService`).
- `app/Jobs/`: Queueable jobs for generation (`ProcessBlogGeneration`) and backups (`BackupDatabase`).
- `app/Models/`: Eloquent models with custom accessors (e.g., `TableOfContents`).
- `tests/Feature/`: Comprehensive feature tests including API integration mocks.

---
**Note**: This project is configured for a single-directory structure combining backend and frontend as requested.
