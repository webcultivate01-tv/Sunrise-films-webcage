<?php

declare(strict_types=1);

use App\Core\Env;

Env::load(BASE_PATH . '/.env');

return [
    'app' => [
        'name'  => Env::get('APP_NAME', 'Sunrise Films'),
        'env'   => Env::get('APP_ENV', 'production'),
        'debug' => Env::bool('APP_DEBUG', false),
        'url'   => rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/'),
        'key'   => (string) Env::get('APP_KEY', ''),
    ],

    'database' => [
        'host'     => Env::get('DB_HOST', '127.0.0.1'),
        'port'     => Env::get('DB_PORT', '3306'),
        'database' => Env::get('DB_DATABASE', 'sunrise_films'),
        'username' => Env::get('DB_USERNAME', 'root'),
        'password' => Env::get('DB_PASSWORD', ''),
        'charset'  => Env::get('DB_CHARSET', 'utf8mb4'),
    ],

    'auth' => [
        // Lifetime of an issued authentication token, in minutes.
        'token_lifetime'   => Env::int('AUTH_TOKEN_LIFETIME', 480),
        // Lifetime of a password reset authorisation, in minutes.
        'reset_lifetime'   => Env::int('RESET_TOKEN_LIFETIME', 60),
        'password_min'     => Env::int('PASSWORD_MIN_LENGTH', 8),
        'max_attempts'     => Env::int('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes'  => Env::int('LOGIN_LOCKOUT_MINUTES', 15),
        // Spec s7: Admin self-service password recovery is policy controlled.
        'admin_forgot_password' => Env::bool('ADMIN_FORGOT_PASSWORD_ENABLED', false),
        // Name of the cookie carrying the authentication token.
        'cookie'           => 'sf_auth_token',
    ],

    'uploads' => [
        // Where profile photos are written to and served from.
        'photos_path' => BASE_PATH . '/public/uploads',
        'photos_url'  => '/uploads',
        'max_kb'      => Env::int('PROFILE_PHOTO_MAX_KB', 2048),
    ],

    'mail' => [
        'driver'       => Env::get('MAIL_DRIVER', 'log'),
        'from_address' => Env::get('MAIL_FROM_ADDRESS', 'no-reply@sunrisefilms.local'),
        'from_name'    => Env::get('MAIL_FROM_NAME', 'Sunrise Films'),
        'log_path'     => BASE_PATH . '/storage/mail',
    ],

    // Printed on every salary settlement bill (Monthly Salary spec s8).
    'company' => [
        'name'    => Env::get('COMPANY_NAME', 'Sunrise Films'),
        'address' => Env::get('COMPANY_ADDRESS', ''),
        'phone'   => Env::get('COMPANY_PHONE', ''),
        'email'   => Env::get('COMPANY_EMAIL', ''),
        'website' => Env::get('COMPANY_WEBSITE', ''),
    ],

    // Everything the rest of the application needs to know about a role lives
    // here, so adding or renaming a panel is a single-file change.
    //
    // Which roles a role may create and manage is NOT here: an Admin manages
    // both Managers and Employees, which a single value cannot express, so
    // that rule lives in UserService::assignableRoles() (module spec s8).
    'roles' => [
        'admin' => [
            'label'     => 'Admin',
            'login'     => '/admin',
            'dashboard' => '/admin/dashboard',
        ],
        'manager' => [
            'label'     => 'Manager',
            'login'     => '/manager',
            'dashboard' => '/manager/dashboard',
        ],
        'employee' => [
            'label'     => 'Employee',
            'login'     => '/employee',
            'dashboard' => '/employee/dashboard',
        ],
    ],
];
