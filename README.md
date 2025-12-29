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

- **🆕 Advanced Error Handling & Resilience** (v2.0):
  - **Triple-Layer API Fallback**:
    - **Layer 1**: HuggingFace Primary → HuggingFace Fallback (all models)
    - **Layer 2**: Gemini Primary → Gemini Fallback
    - **Layer 3**: OpenRouter (deepseek → mistral → hermes)
    - Exponential backoff for 429 rate limits (2^attempt seconds)
  - **Smart Topic Management**:
    - **Robust De-Duplication**: Retries topic selection up to 10 times if duplicates are found.
    - **Fail-Safe Fallback**: Switches to static fallback topics if all RSS sources (updated for 2025) fail.
  - **Unified Reporting System**:
    - Comprehensive email reports for **Success**, **Failure**, and **Duplicate** outcomes.
    - Includes execution logs, stack traces, and direct links.
  - **Autonomous Error Recovery**:
    - Job retry with backoff delays: 60s, 300s, 600s
    - Auto-detects quota errors (402/429) and delays re-queue

- **🆕 API Resilience & Multi-Key Support** (v3.0):
  - **Array-Based API Keys**:
    - Support for multiple API keys per provider: `GEMINI_API_KEY_ARR=["key1","key2","key3"]`
    - Automatic key rotation on rate limits (429) with exponential backoff (2s, 4s, 8s)
    - Immediate skip to next key on quota exceeded (402/403)
    - Quota tracking for email notifications
  - **Mediastack News API Integration**:
    - Primary source for trending topics and research
    - Real-time news from 50+ sources
    - Automatic fallback to RSS on quota/errors
    - Snippet truncation to 1000 chars for optimal processing
  - **Enhanced Duplicate Detection**:
    - LIKE check + `similar_text()` similarity (>80% threshold)
    - 5-retry mechanism (optimized from 10)
    - Detailed logging of similarity scores
    - Email notification when all retries exhausted
  - **Strict Scheduler Enforcement**:
    - Exactly 5 blogs per day (no more, no less)
    - Minimum 3.5hr (210 min) gaps between posts
    - Random jitter (0-120 min) for organic distribution
    - Email alerts for scheduler issues
  - **Comprehensive Email Notifications**:
    - Success: Blog title, link, thumbnail, generation time
    - Failure: Error cause, stack trace, detailed logs
    - Duplicate: Attempted topics, similarity scores
    - Quota Exceeded: API provider, remaining keys, next retry time
- **🆕 Scraping Hub API Integration** (v4.0):
  - **Professional Data Normalization**:
    - Unified parsing for `/news`, `/search`, and `/blog` with explicit data mapping.
    - Captures and logs API `message` when results are empty for better diagnostics.
    - Normalized output fields: `url`, `title`, `snippet`, `source`.
  - **Diagnostic Command Suite**:
    - `php artisan blog:scrapinghub-test`: Tests all 9 major endpoints (Stats, Resources, Scrape, etc.).
    - Automated query handling (confirmed working terms: "tech", "technology").
    - Detailed per-endpoint feedback with title/content-length previews.
  - **Enhanced Health Monitoring**:
    - `php artisan blog:api-health`: Now displays live healthy node counts and total daily requests.
    - 1-hour caching for health and 24-hour caching for search/scraping results.
  - **Deep Integration & Fallbacks**:
    - **RSS**: `ScrapingService` now tries Scraping Hub RSS as the primary fallback before Guzzle.
    - **Topic Discovery**: Prioritizes Scraping Hub for high-quality trending links.
    - **Scrape & Snippet**: Used as the primary engine in `LinkDiscoveryService`.
  - **Automatic Resilience**:
    - Temporary API disabling and email alerts for auth/quota (401-403) errors.
    - Exponential backoff (up to 3 attempts) for server-side rate limits.
- **🆕 Ultimate Resiliency & Fallbacks** (v5.0):
  - **Ultimate Content Fallback**:
    - **Scraped Content Pipeline**: If all AI providers (Gemini, OpenRouter, HF) fail, the system now automatically extracts, cleans, and formats content from scraped research data.
    - **DOM-Based Cleaning**: Uses `DOMDocument` to strip noise (ads, scripts, styles) and extract meaningful structure (H1, Paragraphs) from raw HTML/Research.
    - **Proactive Alerts**: Triggers critical email notifications when the system drops into AI-exhaustion fallback mode.
  - **Thumbnail Redundancy**:
    - **Default Thumbnail**: Integrated a professional, abstract static fallback (`images/default-thumbnail.webp`) for when all AI and SVG generation attempts fail.
    - **WebP Optimized**: Automatically serves a high-quality, lightweight WebP version of the default thumbnail.
  - **Enhanced Scraping Service**:
    - **Web Search Integration**: Automatically performs a broad Google Search (via Serper) for "overview of [topic]" if initial research (RSS/Wiki/News) is sparse.
    - **Top Result Scraping**: Proactively scrapes the top search result to ensure high-quality raw data for generation or fallback.
  - **Proactive Daily Blog Limits**:
    - **Hard Limit**: Enforces exactly 5 blogs per 24-hour period via centralized Cache tracking.
    - **Drift Prevention**: Automatically calculates the remaining balance to schedule, preventing overlap or over-publishing.
    - **Self-Healing Locks**: Proactively force-releases stuck scheduler/queue locks after 1 hour (enhanced from 10 minutes) with admin alerts.

- **🆕 Custom Prompt Feature** (v2.0):
  - **Admin UI**: Add specific instructions via custom prompt field (max 2000 chars)
  - **Smart Scraping**: Auto-detects URLs in prompt, scrapes content, and injects it as context.
  - **Use Cases**: Comparisons, Summarizing specific articles, Targeted stylistic changes.

- **🆕 AI Artifact Cleanup** (v2.0):
  - **Humanization Engine**: Post-processing step to remove robotic patterns.
  - **Pattern Removal**: Strips "In conclusion", "To sum up", and repetitive bolding (e.g., "**Topic** is...").
  - **Bold Limiter**: Enforces intelligent bold tag limits (5/1000 words) for natural readability.
  - **Retroactive Fixes**: `php artisan blog:reformat` command to clean existing blogs.

## 🛠 Tech Stack

- **Framework**: Laravel 11.x (compatible with 12.x structure)
- **Frontend**: Livewire, Blade, Tailwind CSS, Alpine.js
- **Database**: MySQL (Primary), SQLite (Backups)
- **AI Services**:
  - **Google Gemini API** (Primary Content & Analysis)
  - **Hugging Face Inference API** (Redundancy & Image Generation)
  - **OpenRouter API** (Tertiary Fallback - 3 free models)
  - **Mediastack News API** (Trending Topics & Research)
- **Scraping**: Guzzle, Symfony DomCrawler
- **SEO**: spatie/laravel-sitemap
- **Research**: Serper API (Google Search fallback)

## 🕒 Scheduler & Reliability (v5.0)

This system uses a "poor man's cron" (Terminable Middleware) to run tasks without a server-side crontab. While robust, it has specific requirements:

- **Browser Traffic**: Tasks only run when someone visits the site. If traffic is low, use a real cron job:
  ```bash
  * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
  ```
- **Timeouts**: The middleware is configured with a 900s (15 min) timeout for jobs.
- **Monitoring**: Automated email alerts for:
  - **Stalled Scheduler**: If no traffic for >25 hours.
  - **Worker Failure**: If the background process crashes.
  - **Stuck Locks**: Automatic detection and recovery.


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

# AI API Keys - Array Format (v3.0+)
# New: Support for multiple keys with automatic rotation
GEMINI_API_KEY_ARR=["your_gemini_key_1","your_gemini_key_2","your_gemini_key_3"]
HUGGINGFACE_API_KEY_ARR=["your_hf_key_1","your_hf_key_2"]

# Legacy format still supported (backward compatible)
# GEMINI_API_KEY=your_gemini_key_here
# GEMINI_API_KEY_FALLBACK=your_backup_gemini_key
# HUGGINGFACE_API_KEY=your_hf_key_here
# HUGGINGFACE_API_KEY_FALLBACK=your_backup_hf_key

# OpenRouter API (Free tier available)
OPEN_ROUTER_KEY=your_openrouter_key  # Optional

# Mediastack News API (v3.0+)
# Get your free key at: https://mediastack.com/
MEDIA_STACK_KEY=your_mediastack_key  # Optional - Falls back to RSS

# Scraping Hub API (v4.0+) - Primary Scraping Source
# Get your MASTER_KEY from: https://scraping-hub-backend-only.vercel.app
SCRAPING_HUB_BASE_URL=https://scraping-hub-backend-only.vercel.app
MASTER_KEY=your_master_key_here  # Optional - Falls back to Mediastack/RSS

# Research & SEO
SERPER_API_KEY=your_serper_key  # Optional - For web search fallback

# Mail Configuration (Required for error alerts)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
REPORTS_EMAIL=admin@example.com  # Receives quota/error/duplicate notifications
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

### AI Content Cleanup (Artifact Removal)
To retroactively clean up robotic phrases ("In conclusion") and excessive bolding from existing blogs:
```bash
# Clean all blogs
php artisan blog:reformat

# Clean specific blog
php artisan blog:reformat 123
```

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
- **Gemini API Model Errors**: 
  - If you see `404 model not found` errors, the Gemini API model may have changed.
  - Current implementation uses `gemini-2.0-flash-exp` (experimental) via `v1beta` endpoint.
  - Check [Google AI Studio](https://aistudio.google.com/) for latest available models.
  - Update model name in `AIService.php` if needed (search for `gemini-2.0-flash-exp`).
- **Link Injection Failures**: 
  - System tries Gemini first (2 retries), then falls back to HuggingFace.
  - Check `storage/logs/laravel.log` for specific error messages.
  - Verify both `GEMINI_API_KEY` and `HUGGINGFACE_API_KEY` are set in `.env`.
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
