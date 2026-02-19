# Xibo Information Display Integration

This feature adds Xibo information display playlist integration to SafetyFlash, allowing published flashes to be displayed on information screens with TTL (Time To Live) management and manual removal capabilities.

## Features

- **TTL Selection**: Set display expiry time when publishing (1 week, 2 weeks, 1 month, 2 months, 3 months, or no limit)
- **Playlist Status**: View flash display status on view page (active, expired, removed)
- **Manual Management**: Admin, safety team, and communications can remove/restore flashes from playlist
- **Public API**: Xibo can fetch active flashes via JSON/HTML/slideshow format
- **Rate Limiting**: 60 requests per minute per IP
- **CORS Support**: Cross-origin requests allowed for Xibo integration

## Installation

### 1. Database Migration

Run the migration to add new columns to `sf_flashes` table:

```bash
mysql -u your_user -p your_database < migrations/add_display_ttl.sql
```

Or manually execute:

```sql
ALTER TABLE sf_flashes
    ADD COLUMN display_expires_at DATETIME DEFAULT NULL 
        COMMENT 'Milloin flash poistuu automaattisesti infonäyttö-playlistasta. NULL = ei vanhenemista',
    ADD COLUMN display_removed_at DATETIME DEFAULT NULL
        COMMENT 'Manuaalinen poisto playlistasta. NULL = näytetään normaalisti',
    ADD COLUMN display_removed_by INT DEFAULT NULL
        COMMENT 'Kuka poisti playlistasta';

ALTER TABLE sf_flashes
    ADD INDEX idx_display_active (state, display_expires_at, display_removed_at);
```

### 2. Include Assets

Add to your publish modal page:

```php
<?php require_once __DIR__ . '/assets/partials/publish_display_ttl.php'; ?>
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

### 1. Display Playlist (Public)

**Endpoint**: `/app/api/display_playlist.php`

**Method**: GET

**Parameters**:
- `site` (required): Worksite identifier
- `lang` (optional): Language code (fi, sv, en, it, el), default: fi
- `format` (optional): json|html|slideshow, default: json
- `duration` (optional): Seconds per image in slideshow (3-60), default: 10

**Examples**:

```bash
# JSON format
curl "https://your-domain.com/app/api/display_playlist.php?site=SITE_ID&lang=fi"

# HTML slideshow
curl "https://your-domain.com/app/api/display_playlist.php?site=SITE_ID&format=html&duration=10"

# Slideshow content only (for iframe)
curl "https://your-domain.com/app/api/display_playlist.php?site=SITE_ID&format=slideshow&duration=10"
```

**JSON Response**:

```json
{
  "site": "SITE_ID",
  "lang": "fi",
  "count": 5,
  "items": [
    {
      "id": 123,
      "title": "Flash Title",
      "image_url": "https://your-domain.com/uploads/previews/preview_123.jpg",
      "duration_seconds": 10,
      "type": "yellow",
      "published_at": "2026-02-19 10:00:00",
      "sort_order": 0
    }
  ],
  "updated_at": "2026-02-19T11:45:00+02:00"
}
```

### 2. Playlist Management (Authenticated)

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

### Option 1: Webpage Widget

1. In Xibo CMS, create a new **Webpage** widget
2. Set URL to:
   ```
   https://your-domain.com/app/api/display_playlist.php?site=YOUR_SITE&format=html&duration=10
   ```
3. Set duration to cover all slides
4. Enable auto-refresh (5 minutes)

### Option 2: Embedded Content with JavaScript

1. Create an **Embedded** widget
2. Add HTML/JavaScript:

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
        const API_URL = 'https://your-domain.com/app/api/display_playlist.php?site=YOUR_SITE&lang=fi';
        let slides = [];
        let currentIndex = 0;

        async function fetchPlaylist() {
            const response = await fetch(API_URL);
            const data = await response.json();
            slides = data.items;
            renderSlides();
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
            }, slides[currentIndex]?.duration_seconds * 1000 || 10000);
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
