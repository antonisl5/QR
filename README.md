# QR Coupon SaaS

A complete, production-ready backend and web interface for a two-stage QR Coupon lifecycle system. Built strictly with custom PHP & MySQL, featuring a robust JWT authentication system and a strict state machine.

## Features

*   **Custom JWT Authentication:** Pure PHP implementation using HMAC-SHA256, strictly enforcing `admin`, `campaign_owner`, and `store_staff` roles without external frameworks.
*   **Strict State Machine:** Enforces `Idle` -> `Activated` -> `Confirmed` -> `Reset`. Prevents invalid transitions and race conditions.
*   **Database Optimization:** Uses a PDO Singleton pattern with chunked bulk inserts wrapped in transactions for high-performance coupon generation.
*   **Automated Reset System:** Includes a CLI-protected Cron script (`scripts/cron_reset.php`) to automatically reset expired coupons based on configurable hours.
*   **Coupon Builder & Print Engine:** A Canva-like interface to design coupons, alongside a fast client-side QR rendering system (`qrcode.js`) for A4 batch printing.
*   **Camera Scanner:** Integrated `html5-qrcode` scanner with anti-AJAX flooding logic for store staff.
*   **Comprehensive Analytics:** Admin dashboard with real-time conversion rates and activity logs.

## Tech Stack

*   **Backend:** Custom PHP 8.x (No Frameworks)
*   **Database:** MySQL (Normalized, Singleton PDO, Prepared Statements)
*   **Frontend:** HTML5, CSS3, Bootstrap 5, Vanilla JS (SweetAlert2, Chart.js, DataTables)
*   **Auth:** Custom JWT (HttpOnly Cookies)
*   **Deployment:** Ubuntu 22.04/24.04 Bash Script (`setup.sh`)

## Installation Guide (1-Click Deployment)

The included deployment scripts will configure Apache, PHP, MySQL, set up the database, and generate secure `.env` credentials automatically.

1.  **Clone the Repository**
    ```bash
    git clone <repository_url> qr-coupon-saas
    cd qr-coupon-saas
    ```

2.  **Run the Setup Script (Ubuntu)**
    This script requires `sudo` privileges. It installs all dependencies, configures Apache mod_rewrite, sets up a MySQL database with a randomized password, and generates a `.env` file with strong secrets.
    ```bash
    sudo ./setup.sh
    ```

3.  **Seed the Database**
    Navigate to your server's IP or domain in a web browser to run the seeder:
    ```text
    http://<your-server-ip>/install.php
    ```
    Click **Run Installation**. This will execute the `database/schema.sql` and create the Super Admin account.

4.  **Security Cleanup (CRITICAL)**
    Immediately delete the installation files to prevent unauthorized access.
    ```bash
    rm setup.sh install.php
    ```

5.  **Configure Cron Job**
    Add the reset script to your server's crontab to run hourly:
    ```bash
    crontab -e
    # Add the following line:
    0 * * * * /usr/bin/php /var/www/html/scripts/cron_reset.php >> /var/log/cron_reset.log 2>&1
    ```

## Default Credentials

After successfully running `install.php`, you can log in at `http://<your-server-ip>/public/login.php` with:

*   **Email:** `admin@example.com`
*   **Password:** `password123`

*(Change this password immediately after your first login!)*

## Directory Structure

*   `admin/` - Admin and Campaign Owner UI pages (Dashboard, Campaigns, Print Engine).
*   `api/` - Secure JSON endpoints for CRUD and Core flow (Activate, Confirm, Analytics).
*   `assets/` - Static files (CSS, JS, Images, Libraries).
*   `database/` - `schema.sql` and raw DB documentation.
*   `includes/` - Core PHP files (`auth_guard.php`, `db.php`, `config.php`) and Global UI shells (`header.php`, `footer.php`).
*   `public/` - Public-facing pages (Login, Landing/Activation page).
*   `scripts/` - CLI scripts (`cron_reset.php`).
*   `store/` - Store Staff UI (Scanner, Manual Confirmation).

## Security Notes

*   Internal directories (`/includes`, `/database`, `/scripts`) are protected via `.htaccess` from direct browser access.
*   The API endpoints strictly validate JWT payloads and user roles.
*   The Cron script requires a `CRON_SECRET` environment variable to run manually via URL (fallback), but is designed to run via CLI.
*   All user input is sanitized, and DB operations use prepared statements to prevent SQL Injection.
