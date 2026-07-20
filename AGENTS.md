# AGENTS.md

## Project

Cash book application for "Akhtar Husain Traders". Pure PHP + MySQL (PDO), Bootstrap 5, no framework, no build tools. Currency: PKR.

## Runtime

- XAMPP (Apache + MySQL) at `C:\xampp\htdocs\Akhtar_Husain_Traders`
- Database: `akhtar_husain_traders`, user `root`, no password (`config/database.php`)
- PHP files served directly — no composer, no npm, no build step

## Database tables (inferred from queries)

- `users` — `id`, `username`, `password` (bcrypt)
- `cash_entries` — `id`, `entry_type`, `customer_name`, `amount`, `entry_date`, `description`, `created_at`
- `daily_opening` — `id`, `opening_date`, `opening_balance`
- `settings` — `setting_key`, `setting_value` (used: `company_name`, `company_address`, `company_phone`)

## Entry types

`cash_in`, `cash_out`, `adjustment_in`, `adjustment_out`

## Key architecture

- **`includes/functions.php`**: All core logic — auth, balance calculations, helpers. Calls `session_start()` at top. Every page includes this.
- **`$pdo` is a global**: Created in `config/database.php`, accessed via `global $pdo` inside functions. Not injected.
- **Balance math is computed, not stored**: `getOpeningBalance($date)` finds the nearest `daily_opening` record on or before the date, then sums all `cash_entries` between that record's date and the target date. `getClosingBalance($date)` = opening + total_in − total_out.
- **Auto carry-forward**: `index.php` creates a `daily_opening` row for today from yesterday's closing if none exists.
- **Entry pages**: `login.php`, `index.php` (dashboard + close day + set opening), `cash-book.php` (CRUD + filters), `print-cashbook.php`, `logout.php`

## Gotchas

- No CSRF tokens on any form — all POST forms submit without CSRF protection.
- No framework, no ORM — raw PDO with prepared statements throughout.
- `functions.php` starts session unconditionally on include — do not call `session_start()` again.
- `formatCurrency()` returns `'PKR ' . number_format($amount, 2)` — hard-coded currency.
- Date handling uses server timezone (`date('Y-m-d')`).
- `getUniqueCustomers()` queries `cash_entries` directly for datalist autocomplete.
- No input validation library — relies on `floatval()`, `trim()`, and `sanitize()` (htmlspecialchars).

## Modifying this codebase

- Add new pages by following the pattern: `require functions.php` → `requireLogin()` → business logic → `require header.php` → HTML → `require footer.php`.
- Add new DB tables: update queries in `includes/functions.php` or new files following existing PDO pattern.
- All form submissions use PRG (POST → redirect). Use `setFlash()` for success/error messages.
- Print layout is in `print-cashbook.php` with inline styles and `@media print` rules.
