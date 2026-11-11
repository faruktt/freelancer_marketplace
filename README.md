# freelancer_marketplace

**Kraftr** — A compact freelancer marketplace built with raw HTML5, CSS, vanilla JavaScript, PHP 8, and MySQL (PDO), using Google Material Symbols Rounded icons and Google Fonts (`Plus Jakarta Sans`, `JetBrains Mono`).

## Features

- **Marketplace & Search**: Category browsing, sorting, pricing filters, gig detail pages (`gig.php`), and favorites.
- **Role-Separated Panels & Logins**:
  - **Buyer** (`buyer-login.php` → `buyer-panel.php`): Orders, wallet balance, saved gigs, and profile settings.
  - **Seller** (`seller-login.php` → `seller-panel.php`): Create/manage gigs, process orders, deliver milestones, track earnings, and request withdrawals.
  - **Admin** (`admin-login.php` → `admin-panel.php` / `admin.php`): Approve/reject new gigs, manage buyers & sellers, oversee orders & disputes, approve payouts, manage categories, and customize site branding (logo & favicon) and marketplace settings.
- **Real-Time Polling Inbox** (`messages.php`): Thread search, file attachments, order-linked threads, and unread counters.
- **Database Bootstrap** (`config/db.php` & `setup.php`): Automatically creates `kraftr_db` and imports `database/schema.sql` with sample categories, users, and gigs.

## Requirements

- PHP 8.x (`pdo_mysql`, `fileinfo`)
- MySQL / MariaDB

## Local Setup

1. Ensure MySQL/MariaDB is running (`127.0.0.1:3306`, user `root`, empty password by default — override via environment variables `KRAFTR_DB_HOST`, `KRAFTR_DB_PORT`, `KRAFTR_DB_NAME`, `KRAFTR_DB_USER`, `KRAFTR_DB_PASS` or in `config/config.php`).
2. Start PHP's built-in server from the project root:

   ```bash
   php -S 127.0.0.1:8000
   ```

3. Visit `http://127.0.0.1:8000/setup.php` (or `index.php`) to initialize `kraftr_db` from `database/schema.sql`.

## Portals

| Role | Login URL | Panel URL |
| :--- | :--- | :--- |
| Buyer | `/buyer-login.php` | `/buyer-panel.php` |
| Seller | `/seller-login.php` | `/seller-panel.php` |
| Admin | `/admin-login.php` | `/admin-panel.php` |
