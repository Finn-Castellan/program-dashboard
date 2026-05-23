# MIIC Program Dashboard

An activity-tracking dashboard for the **Makerere Innovation & Incubation Centre (MIIC)**. It provides a live overview of all incubation programs, logged activities, KPIs, analytics charts, and automated alerts — served from a local PHP/MySQL backend.

---

## Features

- **Program overview** — pipeline cards, completion percentages, budget tracking, and status indicators for all 8 MIIC programs
- **Activity logging** — add and view program activities with type, date, status, and assigned person
- **Analytics** — bar, line, doughnut, and timeline charts powered by Chart.js
- **Automated alerts** — server-side scans detect overdue activities, upcoming deadlines, behind-schedule programs, and budget overruns; new alerts are generated automatically on load and refreshed every 60 seconds
- **Alert acknowledgement** — persisted to the database via the REST API
- **Notification bell** — live unread count badge updates whenever alerts change
- **Search** — quick-access command bar for programs, alerts, and navigation
- **PDF / Excel export**
- **Dark-mode UI** — responsive sidebar layout

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | Vanilla HTML / CSS / JavaScript (ES2020) |
| Charts | [Chart.js 4.4.0](https://www.chartjs.org/) + chartjs-plugin-datalabels |
| Backend | PHP 8+ (PDO, REST-style endpoints) |
| Database | MySQL 8 (InnoDB, utf8mb4) |
| Local server | [XAMPP](https://www.apachefriends.org/) |

---

## Project Structure

```
programdashboard/
├── index.html          # Main SPA dashboard
├── admin.html          # Admin panel
├── database.sql        # Schema + seed data
├── README.md
└── api/
    ├── config.php      # DB connection (PDO), CORS, shared helpers
    ├── programs.php    # GET all programs / PATCH one program
    ├── activities.php  # GET activities / POST new activity
    ├── alerts.php      # GET alerts / POST acknowledge
    └── auto_alerts.php # GET — scans DB and auto-generates alerts
```

---

## Getting Started

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP 8+)

### Setup

1. **Clone the repository** into your XAMPP web root:

   ```bash
   git clone https://github.com/your-username/programdashboard.git C:/xampp/htdocs/programdashboard
   ```

2. **Import the database schema and seed data:**

   - Open [phpMyAdmin](http://localhost/phpmyadmin)
   - Click **Import** → choose `database.sql` → click **Go**

   Or via the MySQL CLI:

   ```bash
   mysql -u root < C:/xampp/htdocs/programdashboard/database.sql
   ```

3. **Start Apache and MySQL** in the XAMPP Control Panel.

4. **Open the dashboard** in your browser:

   ```
   http://localhost/programdashboard/
   ```

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `api/programs.php` | Returns all programs with metrics, KPIs, and trend data |
| `PATCH` | `api/programs.php` | Updates a program field (e.g. status, budget_used) |
| `GET` | `api/activities.php` | Returns activities, optionally filtered by `program_id` |
| `POST` | `api/activities.php` | Logs a new activity; updates program counters |
| `GET` | `api/alerts.php` | Returns all unacknowledged alerts |
| `POST` | `api/alerts.php` | Create, acknowledge, or acknowledge-all alerts |
| `GET` | `api/auto_alerts.php` | Scans DB and inserts auto-generated alerts; returns current alerts |

---

## Configuration

Database credentials are set in `api/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'miic_dashboard');
define('DB_USER', 'root');
define('DB_PASS', '');
```

> **Important:** `api/config.php` is listed in `.gitignore` to avoid committing credentials. Copy the example below to create your own local version.

---

## .gitignore

Create a `.gitignore` with at minimum:

```
api/config.php
```

---

## License

This project is for internal use at the Makerere Innovation & Incubation Centre. Contact the project maintainer for licensing information.
