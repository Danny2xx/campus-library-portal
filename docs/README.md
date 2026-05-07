# Campus Library Portal

A full-stack library management web application built with PHP and MySQL. Provides a public-facing catalogue and member portal alongside a staff workspace for managing the full circulation lifecycle.

## Features

- Searchable book catalogue with live availability
- Member registration, authentication, and personal dashboard
- Reservation and loan tracking with status management
- Staff admin panel: catalogue, inventory, members, loans, and reporting
- CSRF protection, parameterised queries, and bcrypt password hashing
- Responsive layout across all screen sizes

## Application Structure

```
/index.php              Public homepage
/catalogue.php          Searchable catalogue
/book.php               Book detail and reservation
/login.php              Member sign-in
/register.php           Member registration
/dashboard.php          Member dashboard
/logout.php             Session termination
/admin/                 Staff workspace (admin-only)
/app/includes/          Shared config, auth, and layout includes
/assets/                CSS and JavaScript
/database/              SQL schema and seed data
/docs/                  ERD diagram source
```

## Local Development

**Prerequisites:** PHP 8.1+, MySQL 8+

1. Clone the repository.
2. Import `database/library_portal.sql` into your MySQL instance.
3. Copy `.env.example` to `.env` and set your database credentials, or export the variables directly to your shell.
4. Serve the project root with your preferred local PHP server (e.g. `php -S localhost:8080`).

## Environment Variables

| Variable  | Default         | Description              |
|-----------|-----------------|--------------------------|
| `DB_HOST` | `localhost`     | MySQL host               |
| `DB_NAME` | `library_portal`| Database name            |
| `DB_USER` | `root`          | Database user            |
| `DB_PASS` | *(empty)*       | Database password        |

## Demo Accounts

All seeded accounts use the password `Password123!`

| Role   | Email                           |
|--------|---------------------------------|
| Admin  | `admin@campuslibrary.com`       |
| Member | `maya.chen@campuslibrary.com`   |
| Member | `ahmed.khan@campuslibrary.com`  |
| Member | `sophie.turner@campuslibrary.com` |

## Deployment

See the `Dockerfile` in the project root for Railway / Docker-based deployment. Set the four `DB_*` environment variables in your hosting dashboard and import the SQL schema on first run.
