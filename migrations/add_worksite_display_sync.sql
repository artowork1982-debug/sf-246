-- Migration: Worksite ↔ Display API key sync + playlist sort order
-- Purpose: Link sf_display_api_keys to sf_worksites (1:1) and add per-display sort order
-- Date: 2026-02-22

-- 1. Add worksite_id to sf_display_api_keys
ALTER TABLE sf_display_api_keys
    ADD COLUMN IF NOT EXISTS worksite_id INT DEFAULT NULL
        COMMENT 'sf_worksites.id — 1:1 link to worksite' AFTER id,
    ADD INDEX IF NOT EXISTS idx_worksite_id (worksite_id);

-- 2. Add sort_order to sf_flash_display_targets (for per-display playlist ordering)
ALTER TABLE sf_flash_display_targets
    ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0
        COMMENT 'Display-specific sort order for playlist manager' AFTER activated_at;

-- 3. Create missing API keys for existing worksites that don't have one yet
INSERT INTO sf_display_api_keys (api_key, site, label, lang, site_group, worksite_id, is_active, created_at)
SELECT
    CONCAT('sf_dk_', MD5(CONCAT(w.name, NOW(), RAND()))),
    LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(w.name, ' ', '_'), '/', '_'), 'ä', 'a'), 'ö', 'o'), 'å', 'a')),
    w.name,
    CASE
        WHEN w.name LIKE '%Hellas%' THEN 'el'
        WHEN w.name LIKE '%Italia%' THEN 'it'
        ELSE 'fi'
    END,
    CASE
        WHEN w.name LIKE '%Hellas%' THEN 'Kreikka'
        WHEN w.name LIKE '%Italia%' THEN 'Italia'
        ELSE 'Suomi'
    END,
    w.id,
    w.is_active,
    NOW()
FROM sf_worksites w
WHERE w.id NOT IN (
    SELECT COALESCE(worksite_id, 0)
    FROM sf_display_api_keys
    WHERE worksite_id IS NOT NULL
);
