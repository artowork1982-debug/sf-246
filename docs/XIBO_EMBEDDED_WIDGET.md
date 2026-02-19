# Xibo Embedded Widget - SafetyFlash Integration

This guide provides ready-to-use copy-paste templates for integrating SafetyFlash with Xibo using the **Embedded Widget**.

## Table of Contents

- [Quick Start](#quick-start)
- [Embedded Widget Setup](#embedded-widget-setup)
  - [HTML Field](#html-field)
  - [CSS Field](#css-field)
  - [JavaScript Field](#javascript-field)
- [Configuration](#configuration)
- [Alternative: Webpage Widget](#alternative-webpage-widget)
- [Widget Duration Setting](#widget-duration-setting)
- [Troubleshooting](#troubleshooting)
- [Player Compatibility](#player-compatibility)

---

## Quick Start

1. Get your API key from SafetyFlash admin panel
2. Create a new "Embedded" widget in Xibo Layout Designer
3. Copy-paste the templates below into the three fields (HTML, CSS, JavaScript)
4. Update `API_URL` and `API_KEY` in the JavaScript field
5. Save and publish to your displays

---

## Embedded Widget Setup

In Xibo CMS, create a new **Embedded** widget and fill in the three fields:

### HTML Field

Copy-paste this exactly as-is:

```html
<!-- SafetyFlash soittolista -->
<div id="sf-container" style="width:100%;height:100%;position:relative;overflow:hidden;">
    <img id="sf-slide" src="" alt="" style="width:100%;height:100%;object-fit:contain;opacity:0;" />
    <div id="sf-empty" style="display:none;color:#555;text-align:center;padding-top:40%;font-size:1.5em;">
        Ei aktiivisia SafetyFlasheja
    </div>
</div>
<div id="sf-progress" style="position:fixed;bottom:0;left:0;height:4px;background:rgba(255,255,255,0.5);width:0;"></div>
```

### CSS Field

Copy-paste this exactly as-is:

```css
body, html {
    margin: 0;
    padding: 0;
    background: #000;
    width: 100%;
    height: 100%;
    overflow: hidden;
}
#sf-slide {
    transition: opacity 0.8s ease;
}
#sf-progress {
    transition: width linear;
    z-index: 10;
}
```

### JavaScript Field

Copy-paste this template and **UPDATE the first two lines** with your values:

```javascript
// ====== MUUTA NÄMÄ ======
var API_URL = 'https://your-domain.com/app/api/display_playlist.php';
var API_KEY = 'sf_dk_VAIHDA_TÄHÄN_TYÖMAAN_AVAIN';
var DEFAULT_DURATION = 30;
var REFRESH_MIN = 5;
// =========================

var slides = [];
var current = 0;
var slideTimer = null;
var img = document.getElementById('sf-slide');
var emptyMsg = document.getElementById('sf-empty');
var progress = document.getElementById('sf-progress');

function loadPlaylist() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', API_URL + '?key=' + API_KEY + '&format=json');
    xhr.onload = function() {
        if (xhr.status === 200) {
            var data = JSON.parse(xhr.responseText);
            if (data.ok && data.items && data.items.length > 0) {
                slides = data.items;
                emptyMsg.style.display = 'none';
                if (current >= slides.length) current = 0;
                if (!slideTimer) showSlide();
            } else {
                slides = [];
                img.style.opacity = '0';
                emptyMsg.style.display = 'block';
            }
        }
    };
    xhr.onerror = function() {
        setTimeout(loadPlaylist, 30000);
    };
    xhr.send();
}

function showSlide() {
    if (slides.length === 0) return;
    if (current >= slides.length) current = 0;

    var slide = slides[current];
    var duration = (slide.duration_seconds || DEFAULT_DURATION) * 1000;

    // Fade out
    img.style.opacity = '0';

    setTimeout(function() {
        var newImg = new Image();
        newImg.onload = function() {
            img.src = slide.image_url;
            img.style.opacity = '1';

            // Progress bar
            progress.style.transition = 'none';
            progress.style.width = '0%';
            setTimeout(function() {
                progress.style.transition = 'width ' + (duration / 1000) + 's linear';
                progress.style.width = '100%';
            }, 50);
        };
        newImg.src = slide.image_url;
    }, 800);

    clearTimeout(slideTimer);
    slideTimer = setTimeout(function() {
        current = (current + 1) % slides.length;
        showSlide();
    }, duration);
}

loadPlaylist();
setInterval(loadPlaylist, REFRESH_MIN * 60 * 1000);
```

---

## Configuration

### Required Changes (JavaScript Field)

You **must** update these two values in the JavaScript field:

1. **API_URL**: Your SafetyFlash domain
   ```javascript
   var API_URL = 'https://your-domain.com/app/api/display_playlist.php';
   ```

2. **API_KEY**: The unique API key for your site/display
   ```javascript
   var API_KEY = 'sf_dk_abc123xyz...';
   ```
   
   Get your API key from the SafetyFlash admin panel.

### Optional Settings (JavaScript Field)

- **DEFAULT_DURATION**: Fallback duration in seconds if an image doesn't specify one (default: 30)
- **REFRESH_MIN**: How often to refresh the playlist from the server (default: 5 minutes)

---

## Alternative: Webpage Widget

If you prefer a simpler setup without Embedded widget:

1. Create a **Webpage** widget in Xibo
2. Set the URL to:
   ```
   https://your-domain.com/app/api/display_playlist.php?key=sf_dk_YOUR_API_KEY&format=html
   ```
3. Done! The server generates the complete HTML page.

**Pros:**
- Simpler setup (just a URL)
- No need to edit HTML/CSS/JavaScript

**Cons:**
- Less customizable
- Server-side rendering only

---

## Widget Duration Setting

In Xibo, when configuring your Embedded or Webpage widget:

- **Duration field**: Set to `0` or leave empty
- This makes the widget run indefinitely while the layout is active
- The JavaScript handles image transitions internally
- The playlist auto-refreshes every 5 minutes to get new content

**Important:** The widget duration controls how long the widget stays in the layout rotation, not individual image timing.

---

## Troubleshooting

### No Images Displayed

**Symptom:** Black screen or "Ei aktiivisia SafetyFlasheja" message

**Solutions:**
1. Check API key is correct
2. Verify there are published flashes in SafetyFlash for that site
3. Check network connectivity from player to SafetyFlash server
4. Open browser developer tools (F12) and check Console for errors

### CORS Errors

**Symptom:** Console shows "CORS policy blocked" errors

**Solution:**
- SafetyFlash API already includes CORS headers
- If you see this error, check that `API_URL` uses the correct protocol (https vs http)
- Some players may require additional CORS configuration on the server

### Images Not Rotating

**Symptom:** First image shows but doesn't change

**Solutions:**
1. Check JavaScript console for errors
2. Verify `duration_seconds` values in API response
3. Try refreshing the widget or layout
4. Check player clock/time is correct

### Network Errors

**Symptom:** "Failed to fetch" or connection timeouts

**Solutions:**
1. Test API URL in a browser: `https://your-domain.com/app/api/display_playlist.php?key=YOUR_KEY&format=json`
2. Check firewall rules allow outbound HTTPS from player
3. Verify DNS resolution works on player
4. Check network proxy settings if applicable

### API Key Errors

**Symptom:** 401 or 403 HTTP errors in console

**Solutions:**
- **401 Unauthorized**: API key parameter is missing
- **403 Forbidden**: API key is invalid, deactivated, or expired
- Verify key in SafetyFlash admin panel
- Check key hasn't been deactivated
- Check expiry date if set

### Progress Bar Not Animating

**Symptom:** Progress bar appears but doesn't move

**Solutions:**
1. Check `duration_seconds` value in API response
2. Verify CSS transition is not overridden
3. Some older players may not support CSS transitions

### Slow Image Loading

**Symptom:** Delay between image changes

**Solutions:**
1. Image preloading is built-in (uses `new Image()`)
2. Check network speed between player and server
3. Consider optimizing image file sizes in SafetyFlash
4. Adjust fade duration in CSS if needed

---

## Player Compatibility

### Tested Platforms

- ✅ **Xibo for Windows** (v3+): Full support
- ✅ **Xibo for Android** (v3+): Full support
- ✅ **Xibo for Linux** (v3+): Full support
- ⚠️ **Older Players** (v1-2): May work but not officially tested

### Browser Engine

Xibo players use modern web rendering engines:
- **Windows**: CEF (Chromium Embedded Framework)
- **Android**: WebView (Chromium-based)
- **Linux**: CEF or QtWebEngine

### Known Limitations

1. **Progress bar animation**: Older players may not support CSS transitions
2. **Fade effects**: Should work on all modern players
3. **CORS**: Generally supported, but some configurations may need server adjustments

### Testing Your Setup

1. Test in a modern web browser first (Chrome, Firefox, Edge)
2. Open the API URL directly to verify JSON response
3. Use browser DevTools to inspect network requests and console logs
4. Deploy to a test layout before production

---

## Additional Resources

- [SafetyFlash API Documentation](../XIBO_INTEGRATION.md)
- [Xibo Official Documentation](https://xibo.org.uk/docs/)
- [SafetyFlash Support](mailto:support@your-domain.com)

---

## Version History

- **2026-02-19**: Initial version with API key authentication and per-image duration
