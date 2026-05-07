# Campus Library Portal

A full-stack library management system built with PHP 8 and MySQL. Members can browse the catalogue, place reservations, and track their loans. Staff have a dedicated admin workspace for managing the full circulation lifecycle.

**Live demo:** _link goes here once deployed_

## Tech Stack

- **Backend:** PHP 8.2, PDO
- **Database:** MySQL 8
- **Frontend:** Vanilla HTML, CSS, JavaScript
- **Deployment:** Docker (Railway)

## Quick Start

```bash
# 1. Clone the repo
git clone https://github.com/YOUR_USERNAME/campus-library-portal.git
cd campus-library-portal

# 2. Copy environment template
cp .env.example .env
# Edit .env with your local DB credentials

# 3. Import the database schema
mysql -u root -p < database/library_portal.sql

# 4. Serve locally
php -S localhost:8080
```

## Demo Credentials

Password for all accounts: `Password123!`

| Role   | Email                             |
|--------|-----------------------------------|
| Admin  | `admin@campuslibrary.com`         |
| Member | `maya.chen@campuslibrary.com`     |
| Member | `ahmed.khan@campuslibrary.com`    |
| Member | `sophie.turner@campuslibrary.com` |

## Deploying to Railway

1. Push this repo to GitHub.
2. Create a new Railway project → **Deploy from GitHub repo**.
3. Add a **MySQL** plugin and note the connection variables.
4. Set environment variables: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
5. Import `database/library_portal.sql` into the Railway MySQL instance.
6. Railway will build and deploy the `Dockerfile` automatically.

## Project Structure

```
/admin/         Staff workspace (admin-only routes)
/app/includes/  Config, auth, DB helpers, shared layout
/assets/        CSS and JavaScript
/database/      SQL schema and seed data
/docs/          ERD diagram source
```

## License

MIT
