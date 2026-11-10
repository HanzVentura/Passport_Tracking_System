# Passport Tracking System

## Environment & Server Setup
- Local Environment: XAMPP (Apache + MySQL)
- Base Path: `C:\xampp\htdocs\Passport Tracking System\`
- Local Web URL: `http://localhost/Passport%20Tracking%20System/`
- Database Credentials: Host `127.0.0.1`, DB `passport_db`, User `root`, Pass ``
- DB Connection File: `db.php` (Provides `$pdo` via PDO Singleton)

---

## Architectural Rules & Strict Guidelines

### 1. API & Response Standards (`api/*.php`)
- **JSON Format Only:** All API scripts MUST output `header('Content-Type: application/json')` and return structured JSON: `{"success": true|false, "message": "...", ...}`.
- **No Direct Exits:** NEVER use `die()`, `exit()`, or raw `echo` with plain text or HTML in API files. Always return a proper HTTP status code and JSON payload.
- **Input Parsing:** Always parse inputs cleanly using `$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;` to handle both AJAX JSON payloads and standard form posts.

### 2. Security & Anti-Pattern Restrictions
- **No Hardcoded Auth:** NEVER add hardcoded admin credentials, backdoor accounts, or bypasses.
- **Server-Side Authorization:** NEVER rely solely on `localStorage.userRole` for access control. Always check `$_SESSION` server-side before executing privileged operations.
- **Plaintext Passwords/PINs:** Never store 2FA PINs or passcodes in plain text. Use `password_hash()` and `password_verify()`.
- **Prevent XSS:** Sanitize all dynamic HTML output or user inputs (like support/emergency replies) using `htmlspecialchars()`.
- **No Inline Schema DDL:** Do not put `CREATE TABLE IF NOT EXISTS` queries inside API request handlers. All schema changes belong in `migrate-mysql.php`.

### 3. Database & Query Conventions
- **PDO Prepared Statements:** Every SQL query MUST use prepared statements (`$pdo->prepare()`) with bound parameters (`?`). Never concatenate variables into SQL strings.
- **Session Keys:** Standardize all session reads/writes strictly to `$_SESSION['user_id']` and `$_SESSION['user_email']`. Do NOT use nested arrays like `$_SESSION['user']['id']`.
- **Data Mapping:** Database columns use `snake_case` (e.g., `receiving_option`), while JavaScript objects use `camelCase` (e.g., `receivingOption`). Map these explicitly in PHP responses when fetching records.

### 4. Frontend & Pathing (`public/`)
- **Relative Pathing:** Always use relative paths for static assets and API fetches (e.g., `../api/login.php` or `api/login.php`) to avoid breakage caused by folder name URL encoding space (`/Passport%20Tracking%20System/`).
- **Fetch Patterns:** Use `async/await` with `fetch()` and always handle JSON parse errors gracefully.

---

## Data Model & Lifecycle Quick Reference

### Status Lifecycle (`applications.status`)
`Form Submitted` ➔ `Payment Pending` ➔ `Paid` ➔ `Appointment Scheduled` ➔ `Processing` ➔ `Ready for Release` / `Cancelled`

### Key Tables
- `users`: `id`, `fullname`, `email` (UNIQUE), `password` (bcrypt), `two_factor_enabled`, `passcode`, `created_at`
- `applications`: `id`, `application_id` (e.g., `PH-123456` or `PH-GRP-123456`), `email`, `application_type`, `status`, `processing_type`, `amount`, `payment_status`, `payment_method`, `receiving_option`, `delivery_address`, `appointment_date`, `appointment_time`, `appointment_location`
- `enquiries` & `emergencies`: Tracks user support tickets (`status`: `Open`/`Closed`) linked by `application_id`/`email`.