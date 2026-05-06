# Expenses PHP Tailwind

Mobile-friendly web app for tracking personal expenses, with interactive charts/reports.Built with PHP and styled with Tailwind CSS. Backed by MySQL.

## Features

- **Expense Tracking**: Add, edit, and delete expenses with categories
- **2-Step Add Flow**: Pick category, then enter amount/description — designed for ~3 taps on mobile
- **Dashboard**: Month-to-date total with % change vs. the same day range of the previous month, plus an infinite-scroll list of recent expenses
- **Interactive Reports**: 12-month trend line, average and linear-regression trend overlays, top-10 categories bar chart
- **Category Management**: Add, rename, and delete categories (delete blocked while expenses are linked)
- **Mobile-First UI**: Bottom tab bar, context-aware FAB (add expense or add category depending on current page), collapsing sticky header on dashboard and categories pages
- **User Authentication**: Session-based login with 8h inactivity timeout, bcrypt password hashing, and CSRF protection on all state-changing requests

## Prerequisites

- PHP 7.4 or higher with `pdo_mysql`
- MySQL 5.7+ (local) or a managed MySQL service (e.g., Aiven.io)
- Web server with PHP support (Apache, Nginx, or `php -S` for local dev)
- Git

## Installation

1. Clone the repository:

   ```bash
   git clone https://github.com/gpolic/expenses_php_tailwind.git
   cd expenses_php_tailwind
   ```

2. Create the database and import the schema:

   ```bash
   mysql -u root -p -e "CREATE DATABASE expenses_db"
   mysql -u root -p expenses_db < database.sql
   ```

3. Configure database access (see below).

4. Serve the project:

   ```bash
   php -S localhost:8000
   ```

5. Open `http://localhost:8000` and log in with `admin` / `admin`. Change the password after first login.

## Database Configuration

`config.php` is gitignored. Copy `config - UPDATE THIS.php` to `config.php` and fill in credentials.

**Local dev**:

```php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "expenses_db";
$port       = 3306;
```

**Production (Aiven.io)**: download the `ca.pem` SSL certificate from the Aiven service overview and reference it in `config.php`.

## Database Schema

### `expenses`
- `expense_id` — primary key
- `category_id` — foreign key to `expense_categories`
- `expense_amount` — decimal(19,4)
- `created_at` — expense date/time
- `expense_description` — varchar(50)
- `updated_at` — auto-updated timestamp

### `expense_categories`
- `category_id` — primary key
- `category_name` — varchar(25)

### `users`
- `id` — primary key
- `username` — unique
- `password` — bcrypt hash (`password_hash()` / `password_verify()`)

## Project Structure

| File | Purpose |
|------|---------|
| `index.php` | Dashboard with month total and infinite-scroll expense list |
| `api_expenses.php` | JSON endpoint for paginated expenses (used by dashboard scroll) |
| `select_category.php` | Add flow — step 1, pick category |
| `add_expense_details.php` | Add flow — step 2, amount/description/date |
| `edit.php` | Edit or delete an existing expense |
| `manage_category.php` | Category list — sticky mini-header on mobile, tap row to edit |
| `add_category.php` | Add new category — dedicated page, redirects on success |
| `edit_category.php` | Rename or delete a category |
| `reports.php` | Charts and analytics |
| `profile.php` | Logout page |
| `nav.php` | Shared navigation: desktop top bar, mobile tab bar, context-aware FAB |
| `login.php`, `auth.php`, `logout.php` | Auth pages and handlers |
| `session_check.php` | Auth guard, session timeout, CSRF helpers |
| `database.sql` | Schema with FK constraints and default admin user |
| `styles.css` | Global CSS overrides |

## Navigation

- **Expenses** — dashboard / recent expenses
- **Add Record** — 2-step add flow
- **Reports** — charts and analytics
- **Categories** — category list; FAB opens add category page
- **Logout** (mobile tab) — logs out directly

## Reports

The Reports page provides:

- **Monthly Trend Chart**: line chart of expenses over the last 12 completed months
- **Average Line**: mean monthly spending
- **Trend Analysis**: linear-regression trend line indicating spending direction
- **Category Analysis**: bar chart of top-10 categories by total amount
- **Summary Cards**: averages, totals, and trend indicators

## Security

- PDO with prepared statements throughout
- `htmlspecialchars()` on all output of user-provided strings
- CSRF token (`session_check.php`) required on every POST handler
- 8-hour session inactivity timeout
- HttpOnly + SameSite=Strict cookies; Secure flag set when served over HTTPS
- Passwords stored with `password_hash()` (bcrypt)

## License

See [LICENSE](LICENSE).
