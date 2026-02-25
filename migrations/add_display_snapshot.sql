-- Migration: Add display snapshot columns to sf_flashes
-- Purpose: Preserve display continuity when a published flash is converted to an investigation report.
--          The snapshot columns allow Xibo displays to continue showing the original preview image
--          while the investigation report workflow is in progress (state != 'published').
ALTER TABLE sf_flashes
  ADD COLUMN display_snapshot_preview VARCHAR(255) DEFAULT NULL
    COMMENT 'Preserved preview filename for display continuity during investigation workflow',
  ADD COLUMN display_snapshot_active TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = show snapshot on displays while investigation report is in progress';
