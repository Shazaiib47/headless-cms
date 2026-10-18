# Headless CMS & Page Management System

A full-stack headless CMS that lets non-technical staff create, edit, preview,
publish and manage website pages without changing code. Published content is
delivered through a Laravel REST API to a public-facing Vue 3 frontend.

>> > **Status:** In development. Milestones 01 to 07 complete.

## Tech stack

| Layer      | Technology                                              |
| ---------- | ------------------------------------------------------- |
| Frontend   | Vue 3, TypeScript, Vite, Vue Router, Pinia, Tailwind CSS |
| Backend    | PHP 8.3+, Laravel, Laravel Sanctum, MySQL 8             |
| Testing    | Vitest, Vue Test Utils, PHPUnit/Pest                    |

## Planned features

- Page management with search, filtering and pagination
- Reusable content blocks (hero, rich text, image, CTA, card grid)
- Draft, preview, publish and unpublish workflow
- Media library with validated uploads
- Role-based access control (administrator, editor, viewer)
- Revision history with restore
- Public website that renders published pages dynamically

## Repository structure

headless-cms/
├── backend/ # Laravel API (added in Milestone 02)
├── frontend/ # Vue 3 + TypeScript app (added in Milestone 03)
├── .gitignore
└── README.md


## Setup

## Backend setup (Laravel 13)

Requires PHP 8.3+, Composer and MySQL 8.

    cd backend
    composer install
    cp .env.example .env
    php artisan key:generate
    # create a MySQL database and user, then set DB_* values in .env
    php artisan migrate
    php artisan serve

Health check: `GET http://127.0.0.1:8000/api/health`

## Data model

```mermaid
erDiagram
    USERS ||--o{ PAGES : "creates / updates"
    USERS ||--o{ MEDIA : uploads
    USERS ||--o{ PAGE_REVISIONS : authors
    PAGES ||--o{ PAGE_REVISIONS : has
    MEDIA |o--o{ PAGES : "og image"
```

- **Content blocks** are stored as a validated JSON array on `pages.content`.
- **Roles** (`admin`, `editor`, `viewer`) are an enum column on `users`.
- **Revisions** are immutable snapshots with a per-page version number.
- `role`, `status` and `published_at` are not mass assignable.

Seed demo data locally with `php artisan migrate:fresh --seed`
(local development only; the seeder refuses to run in production).


## Authentication and roles

Cookie-based Laravel Sanctum authentication for the Vue SPA: an HttpOnly
session cookie plus a CSRF token. The SPA first calls `GET /sanctum/csrf-cookie`,
then `POST /api/login`. Failed logins are rate limited per email and IP.

| Role | Pages |
|------|-------|
| viewer | list and view |
| editor | viewer, plus create and update |
| admin | editor, plus delete |

Permissions are enforced server-side by `PagePolicy`, not only in the UI.
Use `localhost` (not `127.0.0.1`) in local URLs so cookies match. Required
settings are in `backend/.env.example`.


## API (admin, work in progress)

Authentication and authorisation are added in Milestone 06; until then these
routes are open and for local development only.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/pages` | List pages (summaries). Params: `search`, `status`, `sort`, `per_page`, `page` |
| POST | `/api/pages` | Create a draft page |
| GET | `/api/pages/{id}` | Retrieve a page with its content blocks |
| PUT/PATCH | `/api/pages/{id}` | Update a page |
| DELETE | `/api/pages/{id}` | Delete a page |

Responses use a `data` wrapper; lists add `links` and `meta`. Validation
errors return HTTP 422 with an `errors` object. `status` and `published_at`
cannot be set through these endpoints.

## Frontend setup (Vue 3 + TypeScript)

Admin: http://localhost:5173/admin (needs the backend running at http://localhost:8000).

Requires Node.js LTS and npm.

    cd frontend
    npm install
    cp .env.example .env
    npm run dev

Run tests with `npm run test:unit` and build with `npm run build`.