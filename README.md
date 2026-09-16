# Sunrise Films

Role-based, token-authenticated access for **Admin**, **Manager** and **Employee**,
built to the functional specification in `docs/` terms: separate entry points, no public
sign-up, hierarchical account creation, and hierarchical password reset.

Built so far:

- **Authentication** — three entry points, token auth, forgot/reset password, account status.
- **Photographer Management** — register, view, edit, search, deactivate and delete photographers.
- **Employee Management** — create Manager and Employee accounts with role assignment,
  scoped listing and search, edit, password reset, deactivate and delete, plus a welcome
  email to every new account.

Hand-rolled PHP MVC (no framework), MySQL, Tailwind CSS. **No Composer dependencies.**

---

## Setup

### 1. Database

```bash
mysql -u root -p < database/schema.sql
```

Creates the `sunrise_films` database and five tables: `users`, `photographers`,
`auth_tokens`, `password_resets`, `login_attempts`.

If the database was created before Photographer & Employee Management existed, bring it
up to date instead — this adds `users.address`, the `photographers` table and the
`task_descriptions` table, and is safe to run more than once:

```bash
php database/migrate_modules.php
```

**Upgrading an install that still calls photographers "customers".** Run this once,
before `migrate_modules.php`. It renames in place — no row is dropped and no value is
retyped:

```bash
php database/migrate_photographers.php
```

| Was | Is now |
|---|---|
| `customers` table | `photographers` |
| `projects.customer_id` | `projects.photographer_id` |
| `projects.name` | `projects.customer_name` |

A project no longer carries a name of its own: it is identified by the photographer it
belongs to plus `customer_name`, the photographer's own client. Existing project names
carry over as customer names, so nothing has to be re-entered.

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
  Controllers/   HomeController, DashboardController, ProfileController,
                 PhotographerController, EmployeeController, ModuleController
                 Auth/AuthController        login, logout, forgot + reset password
  Models/        User, Photographer, AuthToken, PasswordReset, LoginAttempt,
                 Project, Task, TaskDescription, TaskSalaryCredit, Payment, ...
  Services/      AuthService, TokenService, PasswordResetService, UserService,
                 PhotographerService, PasswordPolicy, RateLimiter, MailService,
                 WelcomeMailer, PhotoUploadService, AuthResult
  Middleware/    Authenticate, AuthorizeRoles, RedirectIfAuthenticated, VerifyCsrfToken
  Helpers/       functions.php     view helpers (e, csrf_field, old, field_error, ...)
  Views/         layouts, auth, photographers, employees, projects, tasks, my-work,
                 payments, salary, reports, modules, partials, errors
config/          config.php
database/        schema.sql, schema/ (one file per table), seed.php, seed_demo.php,
                 migrate_modules.php, migrate_photographers.php
routes/          web.php
public/          index.php (front controller), assets/, uploads/
storage/         logs/, mail/
tests/           auth_check.php     authentication acceptance criteria
                 modules_check.php  photographer + employee management acceptance criteria
                 work_management_check.php, task_management_check.php,
                 payment_management_check.php, monthly_salary_check.php,
                 reports_check.php
```

---

## Photographer & Employee Management

Both modules are open to **Admin** and **Manager** and closed to **Employee**. That is
enforced three times over: the routes are never registered on the `/employee` panel, the
`roles:admin,manager` middleware guards the group, and `UserService` / `PhotographerService`
re-check scope on every read and write.

| | Admin | Manager | Employee |
|---|---|---|---|
| Photographers — view / add / edit / search | yes | yes | no |
| Photographers — deactivate | yes | yes | no |
| Photographers — delete outright | yes | no | no |
| Employees — view | all users | their own employees | no |
| Employees — add Manager | yes | no | no |
| Employees — add Employee | yes | yes | no |
| Employees — edit / deactivate / reset password | in scope | in scope | no |
| Employees — delete outright | yes | no | no |

Notes on the decisions the spec left open:

- **Deletion policy.** Deactivating is reversible and keeps history, so both roles may do
  it; deleting destroys the record and is Admin-only. A Manager who still owns Employee
  accounts cannot be deleted — deactivate them instead, so their team is never orphaned.
- **Manager scope.** A Manager sees and manages only the Employees they created. An Admin
  is system-wide. Photographers are shared: every Admin and Manager sees every photographer.
- **Passwords.** The Add User form asks for no password (module spec s7). The system
  generates a temporary one, emails it with the welcome message, and shows it once to
  whoever created the account in case mail delivery is not configured yet.
- **Roles are fixed at creation.** Editing an account does not offer a role change.

---

## Who a job belongs to

Two different people are named on every job, and the app keeps them apart:

- the **photographer** is the studio's own client — the account registered in
  Photographer Management, who sends the work and pays the bill;
- the **customer** is the photographer's client — the wedding, the brand, the school the
  shoot is actually for. It is a plain text field on the project (`customer_name`), not a
  record anybody registers.

So Work Management asks for a photographer from the dropdown and a customer name in the
box beside it, and everything downstream — Task Management, Payment Management, every
report, the invoices — shows the customer as the job's name with the photographer beside
it.

## The task description thread

A photographer rarely sends the whole brief at once: corrections and extra notes keep
arriving for work that is already out with an employee. So a task's description is a
thread rather than a field.

- Row one of every thread is the task's opening description, written when the task is
  assigned. `tasks.description` still holds that text and is never rewritten.
- An Admin or Manager adds a new round from the task page (`POST
  /{panel}/tasks/{id}/descriptions`). It is appended, so the employee sees what changed
  as well as what it changed from.
- The employee reads the whole thread, oldest first, on their My Work page, and My Work's
  list flags how many rounds arrived after the original.
- Reassigning a task copies the thread onto the new task, so the new employee inherits
  the full brief rather than only its opening round.
- A task that is completed, exited or reassigned takes no further rounds — anything new
  belongs on the task that superseded it.
## Panel navigation

Every signed-in panel (`app/Views/layouts/panel.php`) renders a single sidebar shell,
built from the authenticated user's role — there is only one authentication system in
this project. The sidebar lists Dashboard, the modules that are built and routed for that
role (Photographer Management and Employee Management, for Admin and Manager), My Account,
and the wider set of modules the product is heading toward (Work Management, Reports, ...).

`app/Support/PanelModules.php` holds both lists: `BUILT` entries link to real controllers,
while `PLACEHOLDERS` are routed through `ModuleController` and render a "not built yet"
placeholder inside the same authenticated shell instead of a dead link.

## Notes for production

- Tailwind is loaded from the CDN for development. Compile it into
  `public/assets/css/` before going live.
- Set `APP_ENV=production`, `APP_DEBUG=false`, and a real `APP_URL`.
- Point `MAIL_DRIVER` at a real sender (or swap `MailService::send()` for PHPMailer/SMTP)
  so reset links are emailed rather than written to disk.
- Serve over HTTPS — the auth cookie sets its `Secure` flag from the request scheme.
