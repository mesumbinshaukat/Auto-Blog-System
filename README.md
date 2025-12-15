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
    - **SEO & AISEO Enhanced**: 
    - **E-E-A-T Optimized**: AI Prompts engineered for Expertise, Experience, Authoritativeness, and Trustworthiness.
    - **Smart External Linking**: Auto-validates and inserts 2-4 authoritative dofollow links (e.g., Wikipedia, IEEE).
    - **Keyword Research**: Integrates 1 Primary and 2-3 Long-tail keywords (1-2% density) with Q&A optimization for AI Overviews.
    - **Intelligent Internal Linking**: Contextually links 3 related blogs to improve crawl depth.
    - **Optimized Meta**: AI-generated Meta Titles and Descriptions.
    - **Sitemap Automation**: `BlogObserver` auto-regenerates `sitemap.xml` on create/update/delete.
  - **Smart Title Sanitizer**: Automatically detects and fixes malformed HTML entities (e.g., `&rsquo;` → `’`) in titles and identifying duplicate topics.

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
  - Automatic Meta Title & Description generation.
  - OpenGraph tags for social sharing.
  - **Dynamic XML Sitemap**: `spatie/laravel-sitemap` integration for robust handling.
  - `robots.txt` configuration.
  - Keyword density optimization and Internal Linking.

- **Professional Analytics Dashboard**:
  - **Detailed Tracking**: Records IP, User Agent, Referer, and Country (IPv4/IPv6 support).
  - **Visual Insights**: 30-Day View Trend Line Chart.
  - **Demographics**: Top Locations table with country flags.
  - **Anti-Spam**: Smart session buffering to prevent duplicate view counts.

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
- **SEO**: spatie/laravel-sitemap

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

### Smart Middleware Scheduling ("Poor Man's Cron")
The system uses an intelligent middleware-based scheduler, eliminating the need for external cron jobs on shared hosting.

- **How it works**:
  - Every time a user visits the site, the `CheckScheduler` middleware runs.
  - It checks if the daily blog generation has run in the last 24 hours.
  - If not, it dispatches the daily batch of 5 blogs.
  - It also processes one queued job per request (non-blocking) to keep the queue moving.
  
To manually trigger the schedule (for testing):
Visit `/trigger-scheduler` (adds jobs to queue instantly).

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

### Title Sanitization
The system includes a service to clean malformed HTML entities from blog titles (e.g. `User&rsquo;s` -> `User’s`).
```bash
# Scan and fix all malformed titles
php artisan blog:fix-titles
```
*Note: This runs daily via the scheduler.*

### Enhanced SEO Fixes & Retrofitting
To retroactively fix SEO meta tags, validate external links, and inject missing internal links for existing blogs:
```bash
php artisan blog:fix-seo
```
**Output provides detailed per-blog stats:**
```text
✔ Blog Title
  External Validated: 2 | Internal Added: 1
- Another Blog (No changes/Skipped)
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
  - `AIService.php`: Handles Gemini/HF interactions for text.
  - `ScrapingService.php`: Trends and content scraping.
  - `TitleSanitizerService.php`: Cleaning and fixing entity-encoded titles.
- `app/Http/Controllers/`:
  - `SitemapController.php`: Handles dynamic sitemap generation.
  - `AdminController.php`: Analytics aggregator and backend management.
- `app/Models/`: 
  - `BlogView.php`: Analytics tracking model.
  - `Blog.php`: Core content model with custom accessors.
- `tests/Feature/`: Comprehensive feature tests including API integration mocks.

---
**Note**: This project is configured for a single-directory structure combining backend and frontend as requested.
