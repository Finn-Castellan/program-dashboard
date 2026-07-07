-- ═══════════════════════════════════════════════════════════════
--  MIIC Program Activity Tracker — MySQL Database
--  Import via: MySQL Workbench > Server > Data Import > SQL file
--              OR phpMyAdmin > Import tab
-- ═══════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS miic_dashboard
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE miic_dashboard;

-- ───────────────────────────────────────────────────────────────
--  TABLE: programs
-- ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS programs (
  id               VARCHAR(10)  NOT NULL,
  name             VARCHAR(200) NOT NULL,
  abbr             VARCHAR(10)  NOT NULL,
  stage            VARCHAR(120) NOT NULL DEFAULT '',
  icon             VARCHAR(10)  NOT NULL DEFAULT '',
  color            VARCHAR(20)  NOT NULL DEFAULT '#ffffff',
  description      TEXT,
  status           ENUM('on-track','caution','behind') NOT NULL DEFAULT 'on-track',
  budget_used      TINYINT  UNSIGNED NOT NULL DEFAULT 0,
  today_count      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  total_count      INT      UNSIGNED NOT NULL DEFAULT 0,
  completion_pct   TINYINT  UNSIGNED NOT NULL DEFAULT 0,
  trend_json       JSON,
  type_counts_json JSON,
  distribution_json JSON,
  kpis_json        JSON,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
--  TABLE: activities
-- ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activities (
  id                 INT      UNSIGNED AUTO_INCREMENT,
  program_id         VARCHAR(10)  NOT NULL,
  name               VARCHAR(200) NOT NULL,
  type               ENUM('Workshops','Coaching','Gate Reviews','Testing',
                          'Advisory','Demo Days','Investor Eng.','Pilots') NOT NULL,
  activity_date      DATE         NOT NULL,
  status             ENUM('on-track','caution','behind') NOT NULL DEFAULT 'on-track',
  responsible_person VARCHAR(100) DEFAULT NULL,
  notes              TEXT         DEFAULT NULL,
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_program_id   (program_id),
  INDEX idx_activity_date (activity_date),
  CONSTRAINT fk_act_program FOREIGN KEY (program_id)
    REFERENCES programs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
--  TABLE: alerts
-- ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS alerts (
  id           INT UNSIGNED AUTO_INCREMENT,
  program_id   VARCHAR(10) DEFAULT NULL,
  type         ENUM('overdue','deadline','info') NOT NULL DEFAULT 'info',
  title        VARCHAR(200) NOT NULL,
  description  TEXT         DEFAULT NULL,
  tag          VARCHAR(20)  DEFAULT NULL,
  time_label   VARCHAR(60)  DEFAULT NULL,
  icon         VARCHAR(10)  DEFAULT NULL,
  acknowledged TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_acknowledged (acknowledged),
  CONSTRAINT fk_alert_program FOREIGN KEY (program_id)
    REFERENCES programs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
--  TABLE: program_snapshots  (audit trail)
--  One row written each time a manager saves changes to a program.
-- ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS program_snapshots (
  id             INT UNSIGNED AUTO_INCREMENT,
  program_id     VARCHAR(10)  NOT NULL,
  changed_by     VARCHAR(100) NOT NULL DEFAULT 'admin',
  status         ENUM('on-track','caution','behind') NOT NULL,
  budget_used    TINYINT  UNSIGNED NOT NULL,
  today_count    SMALLINT UNSIGNED NOT NULL,
  total_count    INT      UNSIGNED NOT NULL,
  completion_pct TINYINT  UNSIGNED NOT NULL,
  kpis_json      JSON,
  changed_fields VARCHAR(500) NOT NULL DEFAULT '',
  snapshot_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_snap_prog (program_id),
  INDEX idx_snap_at   (snapshot_at),
  CONSTRAINT fk_snap_program FOREIGN KEY (program_id)
    REFERENCES programs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

