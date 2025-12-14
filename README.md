# Auto Blog System 🤖✍️

A fully automated, AI-powered blogging platform built with Laravel 12.x, Livewire, and Tailwind CSS. The system autonomously scrapes trending topics, generates research-backed content using a redundant AI architecture (Gemini + Hugging Face), enhances it for SEO, and publishes daily posts.

## 🌟 Features

- **Automated Content Generation**:
  - Scrapes trending topics from Google Trends and Wikipedia.
  - **Dual-AI Architecture**:
    - **Primary**: Google Gemini 1.5/2.0 Flash for optimization, humanization, and HTML formatting.
    - **Secondary**: Hugging Face (GPT-Neo 1.3B) for initial drafts and fallback generation.
  - **Unique Thumbnails**:
    - Deep content analysis (2000 chars) + Entity Extraction (10+ patterns like "iPhone", "AI", "Trains").
    - **Smart Redundancy**: Gemini 2.0 Flash (SVG analysis) → Hugging Face FLUX.1 (WebP generation) → Category Fallback.
    - Uniqueness validation (80% similarity threshold) to prevent generic images.
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
  - **Self-Healing Diagnostics**: Scripts to test Cron Jobs and Queue Workers on production.
  - Daily SQLite backups with retention policy (last 7 days).
  - Soft deletes for safety.
  - Comprehensive error handling and email notifications.

## 🛠 Tech Stack

- **Framework**: Laravel 11.x (compatible with 12.x structure)
- **Frontend**: Livewire, Blade, Tailwind CSS, Alpine.js
- **Database**: MySQL (Primary), SQLite (Backups)
- **AI Services**:
  - **Google Gemini API** (Primary Content & Analysis)
  - **Hugging Face Inference API** (Redundancy & Image Generation)
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

### Thumbnail Regeneration (New)
The system ensures unique thumbnails. You can bulk regenerate them:
```bash
# Regenerate only if similarity > 80%
php artisan thumbnails:regenerate

# Force regenerate all
php artisan thumbnails:regenerate --force

# Test with limit
php artisan thumbnails:regenerate --limit=5
```

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

- **AI Quota Exceeded (429)**: The system automatically fails over from Gemini to Hugging Face FLUX.1. If both fail, it uses a generic SVG fallback.
- **Queue Not Processing**: Production environments may need diagnostic scripts. Check `cron_test_queue.php` in the root (if uploaded) to debug cron execution.
- **SSL Certificate Errors**: For local development `verify` is set to `false` in Guzzle clients to avoid certificate issues. Ensure this is enabled for production.
- **Rate Limits**: The system implements exponential backoff (retries) for API calls.

## 📂 Directory Structure

- `app/Services/`: 
  - `ThumbnailService.php`: Core logic for content analysis, entity extraction, and multi-tier image generation.
  - `AIService.php`: Handles Gemini/HF interactions for text.
  - `ScrapingService.php`: Trends and content scraping.
- `app/Jobs/`: Queueable jobs for generation (`ProcessBlogGeneration`) and backups (`BackupDatabase`).
- `app/Models/`: Eloquent models with custom accessors (e.g., `TableOfContents`).
- `tests/Feature/`: Comprehensive feature tests including API integration mocks.

---
**Note**: This project is configured for a single-directory structure combining backend and frontend as requested.
