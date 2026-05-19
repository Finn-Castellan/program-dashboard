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

-- ═══════════════════════════════════════════════════════════════
--  SEED DATA — Programs (8 MIIC programs from Masterplan V3)
-- ═══════════════════════════════════════════════════════════════
INSERT INTO programs
  (id, name, abbr, stage, icon, color, description, status,
   budget_used, today_count, total_count, completion_pct,
   trend_json, type_counts_json, distribution_json, kpis_json)
VALUES
(
  'GIP',
  'Graduate Innovation Program', 'GIP',
  'Phase 1 — Pre-incubation', '🎓', '#3b82f6',
  'Structured 3-month pre-incubation for Makerere University student and alumni ventures.',
  'on-track', 61, 12, 64, 78,
  '[4,6,5,8,9,7,11,10,8,12,9,7]',
  '[14,18,6,4,8,5,0,0]',
  '[34,25,18,12,11]',
  '{"enrolled":46,"target_enrolled":50,"women_pct":42,"youth_pct":88,"prototypes":38,"graduation_rate":78,"vbp_progression":68,"cohorts_ytd":1,"cohorts_target":2,"satisfaction":4.2}'
),
(
  'TES',
  'Technology Entrepreneurship Seed', 'TES',
  'Phase 1 — Pre-incubation', '🌱', '#10b981',
  'Broader ecosystem pipeline for non-university technology innovators.',
  'on-track', 58, 9, 51, 74,
  '[5,7,6,9,10,8,12,11,9,13,10,8]',
  '[16,20,5,3,6,4,0,0]',
  '[32,28,20,11,9]',
  '{"enrolled":44,"target_enrolled":50,"women_pct":41,"youth_pct":85,"prototypes":28,"graduation_rate":76,"vbp_progression":62,"cohorts_ytd":1,"cohorts_target":2,"satisfaction":4.3}'
),
(
  'VBP',
  'Venture Building Program', 'VBP',
  'Phase 2 — Venture Building', '🏗️', '#8b5cf6',
  'Transforms validated prototypes into market-ready ventures with traction and revenue.',
  'caution', 72, 5, 38, 55,
  '[3,5,4,7,8,6,9,8,7,10,8,6]',
  '[12,15,8,10,4,6,2,4]',
  '[28,22,24,16,10]',
  '{"enrolled":18,"target_enrolled":20,"women_pct":39,"youth_pct":80,"mvps":14,"paying_customers":280,"revenue_usd":38000,"graduation_rate":72,"irp_routing":48,"gmp_routing":31,"cohorts_ytd":1,"cohorts_target":1,"satisfaction":4.0}'
),
(
  'PCTP',
  'Product Crowd Testing Program', 'PCTP',
  'Cross-cutting — Evidence Module', '👥', '#f59e0b',
  'Structured crowd testing cycles delivering product evidence across all pipeline stages.',
  'on-track', 48, 3, 22, 80,
  '[1,2,1,3,3,2,4,3,2,4,3,2]',
  '[2,4,2,18,2,2,0,0]',
  '[45,30,15,7,3]',
  '{"products_tested":8,"target_products":10,"testers_engaged":240,"target_testers":300,"expert_tester_pct":7,"feedback_completion":74,"cycles_ytd":3,"cycles_target":4,"ip_compliance":100,"data_incidents":0,"satisfaction":4.4}'
),
(
  'SAP',
  'Startup Advisory Program', 'SAP',
  'Cross-cutting — Advisory Layer', '💡', '#14b8a6',
  'Always-on advisory front door providing diagnostics, routing, and alumni support.',
  'on-track', 44, 18, 412, 85,
  '[28,32,35,38,40,44,42,50,48,55,52,49]',
  '[8,22,4,2,120,6,0,0]',
  '[40,25,15,12,8]',
  '{"startups_supported":412,"target_supported":600,"clinic_sessions":14,"target_sessions":20,"advisors":9,"target_advisors":10,"alumni_supported":38,"routing_accuracy":82,"satisfaction":4.1,"conversion_rate":32}'
),
(
  'IRP',
  'Investment Readiness Program', 'IRP',
  'Phase 3A — Capital Readiness', '💰', '#ef4444',
  'Prepares ventures for equity, grants, and structured fundraising through a 6-week bootcamp.',
  'caution', 65, 4, 28, 60,
  '[2,3,2,4,4,3,5,4,3,5,4,3]',
  '[6,8,3,1,4,4,8,0]',
  '[30,25,20,15,10]',
  '{"enrolled":14,"target_enrolled":15,"women_pct":43,"youth_pct":71,"pitch_decks":11,"data_rooms":10,"financial_models":12,"graduation_rate":80,"investor_meetings":9,"capital_raised_usd":90000,"cohorts_ytd":1,"cohorts_target":2,"satisfaction":4.2}'
),
(
  'GMP',
  'Growth Management Program', 'GMP',
  'Phase 3B — Scaling & Bankability', '📈', '#6366f1',
  '24–36 month portfolio program for operational scaling, governance and debt readiness.',
  'on-track', 78, 6, 89, 71,
  '[6,7,8,9,10,9,11,12,11,13,12,10]',
  '[10,18,6,2,8,2,4,4]',
  '[35,28,18,12,7]',
  '{"portfolio_active":22,"target_portfolio":30,"women_pct":40,"sector_diversity":6,"growth_plans":22,"kpi_dashboards":20,"bankability_certified":14,"debt_accessed":9,"revenue_growth_pct":48,"jobs_created":118,"satisfaction":4.0,"default_rate":4.5}'
),
(
  'IDIA',
  'Industry Digital Innovation Accelerator', 'IDIA',
  'Ecosystem Plug-in', '🏭', '#ec4899',
  'Industry challenge cohorts linking ventures to pilots, POCs, and commercial contracts.',
  'behind', 55, 2, 31, 42,
  '[1,2,2,3,2,3,4,3,2,4,3,2]',
  '[6,10,3,3,2,3,2,8]',
  '[25,20,22,18,15]',
  '{"partners":3,"target_partners":5,"startups_admitted":14,"target_admitted":20,"pilots":4,"target_pilots":5,"contracts_lois":2,"revenue_usd":28000,"miic_pipeline_pct":52,"cohorts_ytd":1,"cohorts_target":2,"satisfaction":3.8}'
);

-- ═══════════════════════════════════════════════════════════════
--  SEED DATA — Alerts
-- ═══════════════════════════════════════════════════════════════
INSERT INTO alerts (program_id, type, title, description, tag, time_label, icon) VALUES
('IDIA', 'overdue',  'Q2 Cohort Launch Delayed',
 'IDIA Cohort 2 scheduled for May 1 has not been launched. Only 3 of 5 industry partner MoUs signed. Risk to annual target of 2 cohorts.',
 'IDIA', '3 days overdue', '🔴'),
('VBP',  'deadline', 'VBP Commercialization Exit Gate in 2 Weeks',
 '6 of 18 enrolled ventures have incomplete PCTP evidence. PCTP reports must be uploaded before graduation panel on May 29.',
 'VBP',  'Due May 29, 2026', '🟡'),
('IRP',  'deadline', 'IRP Cohort 2 Applications Close Soon',
 'Cohort 2 application deadline is May 22. Only 9 applications received against a target of 25+. Outreach needed urgently.',
 'IRP',  'Due May 22, 2026', '🟡'),
('GIP',  'overdue',  'GIP Cohort 1 Evidence Pack Backlog',
 '4 ventures have incomplete evidence packs — missing user-test logs. Coach sign-off pending for 3 ventures.',
 'GIP',  '2 days overdue', '🔴'),
('GMP',  'info',     'GMP Monthly Reports Due',
 'Monthly reporting packages for May 2026 are due from all 22 active portfolio companies by May 20. 7 submissions received so far.',
 'GMP',  'Due May 20, 2026', '🔵'),
('SAP',  'info',     'SAP Clinic #15 Scheduled',
 'Legal & IP clinic session scheduled for May 18. 12 ventures pre-registered. Venue and advisor confirmed.',
 'SAP',  'May 18, 2026', '🔵'),
('TES',  'deadline', 'TES Demo Day Preparations',
 'TES Cohort 1 Demo Day scheduled for May 28. 8 pitch decks not yet reviewed by coach. Logistics finalisation needed.',
 'TES',  'Due May 20, 2026', '🟡'),
('PCTP', 'info',     'PCTP Cycle 4 Portal Open',
 'Product Crowd Testing Cycle 4 portal is now open. 3 products registered. Target: 10 products per year. Encourage VBP ventures to enroll.',
 'PCTP', 'Open now', '🔵');
