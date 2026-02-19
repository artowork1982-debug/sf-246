# Xibo Information Display Integration

This feature adds Xibo information display playlist integration to SafetyFlash, allowing published flashes to be displayed on information screens with TTL (Time To Live) management, per-image display duration, API key authentication, and manual removal capabilities.

## Features

- **API Key Authentication**: Secure authentication for display endpoints using unique API keys
- **Per-Image Duration**: Set individual display duration for each flash (10s to 60s)
- **TTL Selection**: Set display expiry time when publishing (1 week, 2 weeks, 1 month, 2 months, 3 months, or no limit)
- **Playlist Status**: View flash display status on view page (active, expired, removed)
- **Manual Management**: Admin, safety team, and communications can remove/restore flashes from playlist
- **Public API**: Xibo can fetch active flashes via JSON/HTML/slideshow format
- **Rate Limiting**: 60 requests per minute per IP
- **CORS Support**: Cross-origin requests allowed for Xibo integration
- **Embedded Widget Templates**: Ready-to-use HTML/CSS/JavaScript templates (see [docs/XIBO_EMBEDDED_WIDGET.md](docs/XIBO_EMBEDDED_WIDGET.md))

## Installation

### 1. Database Migrations

Run all migrations to add new tables and columns:

```bash
# Display TTL columns
mysql -u your_user -p your_database < migrations/add_display_ttl.sql

# Display duration column
mysql -u your_user -p your_database < migrations/add_display_duration.sql

# API keys table
mysql -u your_user -p your_database < migrations/add_display_api_keys.sql
```

Or manually execute:

```sql
-- Display TTL (already exists)
ALTER TABLE sf_flashes
    ADD COLUMN display_expires_at DATETIME DEFAULT NULL 
        COMMENT 'Milloin flash poistuu automaattisesti infonäyttö-playlistasta. NULL = ei vanhenemista',
    ADD COLUMN display_removed_at DATETIME DEFAULT NULL
        COMMENT 'Manuaalinen poisto playlistasta. NULL = näytetään normaalisti',
    ADD COLUMN display_removed_by INT DEFAULT NULL
        COMMENT 'Kuka poisti playlistasta';

ALTER TABLE sf_flashes
    ADD INDEX idx_display_active (state, display_expires_at, display_removed_at);

-- Display duration (NEW)
ALTER TABLE sf_flashes
    ADD COLUMN display_duration_seconds INT DEFAULT 30
        COMMENT 'Kuinka monta sekuntia tämä flash näkyy infonäytöllä (oletus 30s)';

-- API keys table (NEW)
CREATE TABLE sf_display_api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    site VARCHAR(100) NOT NULL COMMENT 'Työmaan tunniste',
    label VARCHAR(255) DEFAULT NULL COMMENT 'Näytön nimi, esim. Helsinki toimisto 2.krs',
    lang VARCHAR(5) DEFAULT 'fi',
    is_active TINYINT(1) DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME DEFAULT NULL COMMENT 'Milloin viimeksi käytetty',
    last_used_ip VARCHAR(45) DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL COMMENT 'NULL = ei vanhene',
    INDEX idx_api_key (api_key),
    INDEX idx_site (site)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2. Include Assets

Add to your publish modal page:

```php
<?php require_once __DIR__ . '/assets/partials/publish_display_ttl.php'; ?>
<?php require_once __DIR__ . '/assets/partials/publish_display_duration.php'; ?>
```

Add to your view page (right column):

```php
<?php require_once __DIR__ . '/assets/partials/view_playlist_status.php'; ?>
```

Include CSS in your header:

```html
<link rel="stylesheet" href="<?= $config['base_url'] ?>/assets/css/display-ttl.css">
```

Include JavaScript before closing body tag:

```html
<script>
    window.SF_BASE_URL = '<?= $config['base_url'] ?>';
    window.SF_CSRF_TOKEN = '<?= sf_csrf_token() ?>';
    window.SF_TERMS = {
        confirm_remove_from_playlist: '<?= sf_term('confirm_remove_from_playlist', $currentUiLang) ?>'
    };
</script>
<script src="<?= $config['base_url'] ?>/assets/js/display-playlist.js"></script>
```

## API Endpoints

### 1. API Key Management (Admin Only)

**Endpoint**: `/app/api/display_api_keys_manage.php`

**Authentication**: Session-based, role_id = 1 (admin)

**Create New API Key**:

```bash
POST /app/api/display_api_keys_manage.php
Content-Type: application/x-www-form-urlencoded

csrf_token=xxx&site=SITE_ID&label=Helsinki+Office&lang=fi
```

Response:
```json
{
  "ok": true,
  "api_key": "sf_dk_abc123...",
  "xibo_url": "https://your-domain.com/app/api/display_playlist.php?key=sf_dk_abc123...",
  "site": "SITE_ID",
  "label": "Helsinki Office",
  "lang": "fi"
}
```

**List API Keys**:

```bash
GET /app/api/display_api_keys_manage.php
```

Response:
```json
{
  "ok": true,
  "keys": [
    {
      "id": 1,
      "api_key_preview": "sf_dk_abc1...",
      "site": "SITE_ID",
      "label": "Helsinki Office",
      "lang": "fi",
      "is_active": true,
      "created_at": "2026-02-19 12:00:00",
      "last_used_at": "2026-02-19 13:30:00",
      "last_used_ip": "192.168.1.100",
      "expires_at": null
    }
  ]
}
```

**Deactivate API Key**:

```bash
POST /app/api/display_api_keys_manage.php?action=delete
Content-Type: application/x-www-form-urlencoded

csrf_token=xxx&api_key_id=1
```

Response:
```json
{
  "ok": true
}
```

### 2. Display Playlist (Public, API Key Required)

**Endpoint**: `/app/api/display_playlist.php`

**Method**: GET

**Authentication**: API key (query parameter)

**Parameters**:
- `key` (required): API key that determines site and language automatically
- `format` (optional): json|html|slideshow, default: json

**Examples**:

```bash
# JSON format (for Embedded widget)
curl "https://your-domain.com/app/api/display_playlist.php?key=sf_dk_abc123..."

# HTML slideshow (for Webpage widget)
curl "https://your-domain.com/app/api/display_playlist.php?key=sf_dk_abc123...&format=html"

# Slideshow content only (for iframe)
curl "https://your-domain.com/app/api/display_playlist.php?key=sf_dk_abc123...&format=slideshow"
```

**JSON Response**:

```json
{
  "ok": true,
  "site": "SITE_ID",
  "lang": "fi",
  "count": 5,
  "items": [
    {
      "id": 123,
      "title": "Flash Title",
      "image_url": "https://your-domain.com/uploads/previews/preview_123.jpg",
      "duration_seconds": 30,
      "type": "yellow",
      "published_at": "2026-02-19 10:00:00",
      "sort_order": 0
    }
  ],
  "updated_at": "2026-02-19T11:45:00+02:00"
}
```

**Error Responses**:

```json
// Missing API key (401)
{
  "error": "Missing required parameter: key"
}

// Invalid API key (403)
{
  "error": "Invalid API key"
}

// Deactivated API key (403)
{
  "error": "API key is deactivated"
}

// Expired API key (403)
{
  "error": "API key has expired"
}
```

### 3. Playlist Management (Authenticated)

**Endpoint**: `/app/api/display_playlist_manage.php`

**Method**: POST

**Headers**:
- `Content-Type: application/json`
- `X-CSRF-Token: YOUR_TOKEN`

**Body**:

```json
{
  "flash_id": 123,
  "action": "remove",
  "csrf_token": "YOUR_TOKEN"
}
```

**Actions**:
- `remove`: Remove flash from playlist
- `restore`: Restore flash to playlist (auto-extends expired flashes by 30 days)

**Response**:

```json
{
  "ok": true,
  "status": "removed",
  "message": "Poistettu playlistasta"
}
```

## Xibo Integration

### Quick Setup Guide

**📖 For complete setup instructions with ready-to-use templates, see:**
- **[docs/XIBO_EMBEDDED_WIDGET.md](docs/XIBO_EMBEDDED_WIDGET.md)** - Copy-paste templates for HTML, CSS, and JavaScript

### Option 1: Webpage Widget (Simplest)

1. Get your API key from SafetyFlash admin panel
2. In Xibo CMS, create a new **Webpage** widget
3. Set URL to:
   ```
   https://your-domain.com/app/api/display_playlist.php?key=sf_dk_YOUR_API_KEY&format=html
   ```
4. Set widget duration to `0` (runs indefinitely)
5. The widget auto-refreshes every 5 minutes

**Pros:**
- Simple one-line configuration
- No HTML/JavaScript editing needed
- Server-side rendering

**Cons:**
- Less customizable than Embedded widget

### Option 2: Embedded Widget (Recommended)

For full control and customization, use the **Embedded** widget with our ready-to-use templates:

1. Get your API key from SafetyFlash admin panel
2. Create a new **Embedded** widget in Xibo Layout Designer
3. Follow the instructions in **[docs/XIBO_EMBEDDED_WIDGET.md](docs/XIBO_EMBEDDED_WIDGET.md)**
4. Copy-paste the HTML, CSS, and JavaScript templates
5. Update `API_URL` and `API_KEY` in the JavaScript
6. Save and publish

**Features:**
- Per-image display duration from database
- Progress bar animation
- 0.8s fade transitions
- Auto playlist refresh (5 minutes)
- Black background, object-fit: contain
- Handles empty playlists gracefully

**Pros:**
- Full customization
- Per-image timing
- Progress bar
- Better fade transitions

**Cons:**
- Requires copying three code blocks (but we provide ready templates!)

### Migration from Site Parameter

If you're upgrading from the old site-based authentication:

**Old URL:**
```
https://your-domain.com/app/api/display_playlist.php?site=SITE_ID&lang=fi
```

**New URL (API key):**
```
https://your-domain.com/app/api/display_playlist.php?key=sf_dk_abc123...
```

The API key automatically determines the site and language, so you don't need those parameters anymore.

### API Key Management

**Creating API Keys** (Admin only):

```bash
POST /app/api/display_api_keys_manage.php
Content-Type: application/x-www-form-urlencoded

csrf_token=xxx&site=SITE_ID&label=Helsinki+Office&lang=fi
```

**Best Practices:**
- Create one API key per display or location
- Use descriptive labels (e.g., "Helsinki Office 2nd Floor")
- Monitor `last_used_at` to track key usage
- Deactivate unused keys for security
- Set expiry dates for temporary displays

### Legacy Embedded Content Example (Not Recommended)

⚠️ **Use the templates in [docs/XIBO_EMBEDDED_WIDGET.md](docs/XIBO_EMBEDDED_WIDGET.md) instead.**

This is kept for reference only:

```html
<!DOCTYPE html>
<html>
<head>
    <style>
        body { margin: 0; padding: 0; background: #000; }
        .slide { width: 100vw; height: 100vh; display: none; }
        .slide.active { display: flex; align-items: center; justify-content: center; }
        .slide img { max-width: 100%; max-height: 100%; object-fit: contain; }
    </style>
</head>
<body>
    <div id="slideshow"></div>
    <script>
        const API_URL = 'https://your-domain.com/app/api/display_playlist.php?key=sf_dk_YOUR_KEY&format=json';
        let slides = [];
        let currentIndex = 0;

        async function fetchPlaylist() {
            const response = await fetch(API_URL);
            const data = await response.json();
            if (data.ok && data.items) {
                slides = data.items;
                renderSlides();
            }
        }

        function renderSlides() {
            const container = document.getElementById('slideshow');
            container.innerHTML = '';
            slides.forEach((item, index) => {
                const div = document.createElement('div');
                div.className = 'slide' + (index === 0 ? ' active' : '');
                const img = document.createElement('img');
                img.src = item.image_url;
                img.alt = item.title;
                div.appendChild(img);
                container.appendChild(div);
            });
            startSlideshow();
        }

        function startSlideshow() {
            if (slides.length <= 1) return;
            setInterval(() => {
                const slideElements = document.querySelectorAll('.slide');
                slideElements[currentIndex].classList.remove('active');
                currentIndex = (currentIndex + 1) % slides.length;
                slideElements[currentIndex].classList.add('active');
            }, slides[currentIndex]?.duration_seconds * 1000 || 30000);
        }

        fetchPlaylist();
        setInterval(fetchPlaylist, 300000); // Refresh every 5 minutes
    </script>
</body>
</html>
```

### Option 3: Direct Iframe

```html
<iframe 
    src="https://your-domain.com/app/api/display_playlist.php?site=YOUR_SITE&format=slideshow&duration=10" 
    style="width: 100%; height: 100%; border: none;">
</iframe>
```

## Permissions

The following roles can manage playlist (remove/restore):
- Admin (role_id: 1)
- Safety Team (role_id: 3)
- Communications (role_id: 4)

## Rate Limiting

The public playlist API is rate-limited to **60 requests per minute per IP address** to prevent abuse.

## Logging

All playlist management actions are logged to the `safetyflash_logs` table with event types:
- `display_removed`: Flash removed from playlist
- `display_restored`: Flash restored to playlist

## Troubleshooting

### Flash not appearing in playlist

Check that:
1. Flash is published (`state = 'published'`)
2. Flash has not expired (`display_expires_at IS NULL OR > NOW()`)
3. Flash has not been manually removed (`display_removed_at IS NULL`)
4. Correct language is selected in API call

### Rate limit errors

If you receive `429 Too Many Requests`:
- Reduce polling frequency
- Implement caching on Xibo side
- Contact administrator to adjust rate limits

### Images not loading

Verify:
- `preview_filename` exists in database
- Preview images exist in `/uploads/previews/` directory
- Base URL is correctly configured in `config.php`
- File permissions allow web server to read images

## License

Part of the SafetyFlash system. © 2026
