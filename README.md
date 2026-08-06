# PRIME Maintenance System V.01

PRIME Maintenance System is a Laravel-based web application for managing preventive maintenance and breakdown reporting on production machines. This project is built to help teams monitor machine conditions, execute PM routines, review operational work, and generate maintenance reports from a single system.

## Main Features

- Dashboard for maintenance monitoring and operational summary
- Master data management for machines, locations, and PM checksheets
- QR-based machine access flow for operators
- Preventive maintenance execution workflow for operators
- PM review and approval flow for administrators
- Breakdown input, follow-up, and review workflow
- Maintenance calendar for scheduled activities
- PM and Breakdown reports with PDF and Excel export
- User management for internal system access

## User Roles

- `admin` for master data, review, reports, and user management
- `operator` for machine access, PM execution, and breakdown input
- `guest` for limited dashboard monitoring access

## Tech Stack

- PHP 8.3
- Laravel 12
- MySQL
- Tailwind CSS 4
- Vite
- DomPDF for PDF export
- Laravel Excel for spreadsheet export
- Simple QR Code for machine QR generation

## Application Modules

- `Dashboard`
- `PM Management`
  - Master Mesin
  - Master Lokasi
  - Master PM Checksheet
  - PM Review
- `Breakdown Management`
  - Input Breakdown
  - Review Breakdown
- `Kalender`
- `Report`
  - Report PM
  - Report Breakdown
- `Pengaturan User`

## Getting Started

### 1. Clone Repository

```bash
git clone https://github.com/your-username/Preventive_Maintenance.git
cd Preventive_Maintenance
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Prepare Environment

```bash
cp .env.example .env
php artisan key:generate
```

Update your `.env` values for:

- application URL
- database connection
- mail configuration if needed

Recommended local defaults used by this project include:

- `APP_TIMEZONE=Asia/Jakarta`
- `SESSION_DRIVER=database`
- `CACHE_STORE=database`
- `QUEUE_CONNECTION=database`
- `FILESYSTEM_DISK=local`

### 4. Run Database Migration

```bash
php artisan migrate
```

### 5. Create Storage Symlink

```bash
php artisan storage:link
```

### 6. Run the Application

For local development, this project already provides a combined dev command:

```bash
composer run dev
```

That command runs:

- Laravel development server
- queue listener
- log viewer
- scheduler worker
- Vite development server

If you prefer to run services manually:

```bash
php artisan serve
php artisan queue:listen --tries=1
php artisan schedule:work
npm run dev
```

## Scheduled Task

This project includes a scheduled command to keep PM schedule statuses in sync:

```bash
php artisan pm:sync-schedule-status
```

Local development needs the scheduler worker (`php artisan schedule:work`) so scheduled commands run automatically.
For production, run `php artisan schedule:run` every minute from the server's scheduler (for example, with cron).

## Reports

The system supports maintenance reporting for:

- PM report export to PDF and Excel
- Breakdown report export to PDF and Excel
- generated export download history through the application flow

## Development Notes

- Frontend assets are handled with Vite
- Background jobs use the database queue driver
- Sessions and cache are stored in the database
- File storage uses Laravel local storage

If frontend changes do not appear, run one of the following:

```bash
npm run dev
```

or:

```bash
npm run build
```
