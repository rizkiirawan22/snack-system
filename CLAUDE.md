# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Snack System** is a full-stack POS and inventory management application for a snack shop ("Snack Kiloan"). It consists of two separate projects:
- `snack-backend/` — Laravel 13 REST API (PHP)
- `snack-frontend/` — Vue 3 + Vite SPA

## Commands

### Backend (`snack-backend/`)

```bash
composer install              # Install PHP dependencies
php artisan migrate           # Run database migrations
php artisan serve             # Start dev server (port 8000)
php artisan test              # Run all PHPUnit tests
php artisan test --filter=TestName  # Run a single test

composer run dev              # Start all services concurrently (server, queue, pail, vite)
composer run setup            # Full setup: install, env, key, migrate, npm install, build
```

### Frontend (`snack-frontend/`)

```bash
npm install       # Install dependencies
npm run dev       # Start Vite dev server with HMR
npm run build     # Build for production
npm run preview   # Preview production build
```

## Architecture

### Backend

- **API-first**: All routes in `routes/api.php` return JSON. Authentication uses Laravel Sanctum (Bearer tokens, not sessions).
- **Role-based access**: Middleware `auth:sanctum` and `role:admin` gate endpoints. Admin-only routes: reports, user management.
- **Database**: SQLite by default (`database/database.sqlite`). Key models: `User`, `Category`, `Product`, `Stock`, `StockIn`, `StockInItem`, `Sale`, `SaleItem`.
- **Stock deduction on sale**: `SaleController` wraps sale creation + stock updates in a database transaction to ensure consistency.

### Frontend

- **Entry**: `src/main.js` → Vue app → mounted to `#app`.
- **Auth state**: Pinia store (`stores/auth`) persists token/user to localStorage. Router guard in `router/` redirects unauthenticated users and enforces role-based view access.
- **API client**: Single axios instance in `src/api/axios.js` attaches the Bearer token from the auth store to every request.
- **Views**: Page components in `src/views/` are lazy-loaded by Vue Router. Role-gated views: `ReportsView`, `UsersView` (admin only).

### Data Flow

```
Vue view → axios (Bearer token) → Laravel route → Middleware → Controller → Eloquent → SQLite
                                                                ↓
                                                          JSON response → Pinia store → UI
```

### Key API Routes

| Method | Path | Access |
|--------|------|--------|
| POST | `/login` | Public |
| POST | `/logout`, GET `/me` | Authenticated |
| GET | `/dashboard` | Authenticated |
| CRUD | `/categories`, `/products`, `/stock-ins`, `/sales` | Authenticated |
| GET | `/products/low-stock` | Authenticated |
| GET | `/reports/*` | Admin only |
| CRUD | `/users` | Admin only |
