# Sunrise Films — Authentication System

Role-based, token-authenticated access for **Admin**, **Manager** and **Employee**,
built to the functional specification in `docs/` terms: separate entry points, no public
sign-up, hierarchical account creation, and hierarchical password reset.

Hand-rolled PHP MVC (no framework), MySQL, Tailwind CSS. **No Composer dependencies.**

---

## Setup

### 1. Database

```bash
mysql -u root -p < database/schema.sql
```

Creates the `sunrise_films` database and four tables: `users`, `auth_tokens`,
`password_resets`, `login_attempts`.

### 2. Environment

`.env` is already created from `.env.example` with a generated `APP_KEY`.
Set your MySQL credentials:

```dotenv
DB_USERNAME=root
DB_PASSWORD=your-password
```

> `APP_KEY` peppers the stored token hashes. Changing it invalidates every
> issued token and every outstanding password-reset link.

### 3. Seed the first Admin

```bash
php database/seed.php
```

| | |
|---|---|
| URL | `/admin` |
| Email | `admin@gmail.com` |
| Password | `admin123` |

Change this password after the first sign-in (My Account → Change password).

### 4. Run

```bash
php -S localhost:8000 -t public public/index.php
```

Then open <http://localhost:8000/admin>.

For Apache/XAMPP, point the document root at `public/`. If the document root is the
project folder instead, the root `.htaccess` forwards everything into `public/`.

### 5. Verify

```bash
php tests/auth_check.php
```

Runs the spec's acceptance criteria (§21) against the real database: login per role,
cross-role refusal, token issue/tamper/revoke, account status, both password-reset paths
and the single-use reset link. It creates a throwaway Manager and Employee and deletes
them again, so it is safe to re-run.

---

## Entry points

| Role | Login | Panel | Creates |
|---|---|---|---|
| Admin | `/admin` | `/admin/dashboard` | Managers |
| Manager | `/manager` | `/manager/dashboard` | Employees |
| Employee | `/employee` | `/employee/dashboard` | — |

Each role's routes are generated from the same definitions in `routes/web.php`, so the
three panels cannot drift apart. A token issued at one entry point carries its role and
is refused everywhere else.

---

## How authentication works

**Token-based, not session-based.** On a successful login the app issues a token and
stores it in an HttpOnly, SameSite=Lax cookie; non-browser clients may send it as
`Authorization: Bearer <token>`. Every protected request re-derives the identity from
that token.

A token is `<selector>.<validator>`:

- the **selector** is the indexed lookup key, so verification is one indexed read;
- only an **HMAC-SHA256 of the validator** (keyed with `APP_KEY`) is stored, so a leaked
  `auth_tokens` table cannot be replayed as a login;
- comparison is constant time, and a valid selector presented with a bad validator
  revokes the token rather than allowing a guessing loop.

A token stops working when it expires, when it is revoked (logout, password change,
password reset by a superior, deactivation), when the account stops being `active`, or
when the account's role no longer matches the role the token was issued for.

A PHP session **is** used, but only for presentation state — flash messages, repopulated
form input, and the CSRF token. It never holds the identity, so clearing it cannot sign
anybody in or out.

### Password reset flow

| Path | Who |
|---|---|
| Forgot Password (self-service) | Manager, Employee — and Admin only when `ADMIN_FORGOT_PASSWORD_ENABLED=true` |
| Admin resets a Manager | `/admin/managers/{id}/password` |
| Manager resets an Employee | `/manager/employees/{id}/password` |
| Anyone changes their own | `/{panel}/profile` |

Self-service reset links are single-use, expire after `RESET_TOKEN_LIFETIME` minutes, and
are bound to the role whose entry point issued them. Whether or not the email exists, the
response is the same neutral message — existence is checked server-side and never
disclosed.

**Before SMTP is configured** (`MAIL_DRIVER=log`, `APP_ENV=local`), the reset link is both
written to `storage/mail/` and shown on the page, so the flow is testable without an inbox.
Outside local+log, the link is only ever emailed.

---

## Security

| Requirement (spec §18) | Where |
|---|---|
| No plain-text passwords | `PasswordPolicy::hash()` — `PASSWORD_DEFAULT` (bcrypt), with automatic rehash on login |
| No public registration | No such route exists; `UserService` requires a creating actor |
| Role-based access control | `Authenticate` middleware, role pinned into the token |
| Separate entry points | `routes/web.php`, generated per role |
| Invalid/expired tokens refused | `TokenService::resolve()` |
| Reset requires verification | `PasswordResetService::authorise()` |
| Scoped account management | `UserService::findSubordinateOrFail()` — 403 on anything out of scope |
| No account enumeration | Same message and comparable timing for unknown email, wrong role and wrong password |
| Logout invalidates access | `AuthService::logout()` revokes the stored token |
| Only active accounts sign in | `AuthService::attempt()`, after the password check |

Also present: CSRF protection on every state-changing form, login/reset throttling
(`LOGIN_MAX_ATTEMPTS` per email+IP, `LOGIN_LOCKOUT_MINUTES` lockout), and
`X-Frame-Options` / `X-Content-Type-Options` / `Referrer-Policy` headers on every response.

---

## Layout

```
app/
  Core/          Router, Request, Response, View, Database, Session, Csrf, Config, Env, Validator
  Controllers/   HomeController, DashboardController, AccountController, ProfileController
                 Auth/AuthController        login, logout, forgot + reset password
  Models/        User, AuthToken, PasswordReset, LoginAttempt
  Services/      AuthService, TokenService, PasswordResetService, UserService,
                 PasswordPolicy, RateLimiter, MailService, AuthResult
  Middleware/    Authenticate, RedirectIfAuthenticated, VerifyCsrfToken
  Helpers/       functions.php     view helpers (e, csrf_field, old, field_error, ...)
  Views/         layouts, auth, accounts, partials, errors
config/          config.php
database/        schema.sql, seed.php
routes/          web.php
public/          index.php (front controller), assets/, uploads/
storage/         logs/, mail/
tests/           auth_check.php    acceptance-criteria smoke test
```

---

## Panel navigation

Every signed-in panel (`app/Views/layouts/panel.php`) renders a single sidebar shell,
built from the authenticated user's role — there is only one authentication system in
this project. The sidebar lists Dashboard, the role one level down (Managers for Admin,
Employees for Manager), My Account, and the wider set of modules the product is heading
toward (Customer Registration, Work Management, Reports, ...). Modules without a real
controller yet (`app/Support/PanelModules.php`, routed through `ModuleController`) render
a "not built yet" placeholder inside the same authenticated shell instead of a dead link.

## Notes for production

- Tailwind is loaded from the CDN for development. Compile it into
  `public/assets/css/` before going live.
- Set `APP_ENV=production`, `APP_DEBUG=false`, and a real `APP_URL`.
- Point `MAIL_DRIVER` at a real sender (or swap `MailService::send()` for PHPMailer/SMTP)
  so reset links are emailed rather than written to disk.
- Serve over HTTPS — the auth cookie sets its `Secure` flag from the request scheme.
