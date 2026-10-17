# Movie Recommendation Engine

A simple movie recommendation app. Pick a few movies you like, and it suggests others based on what similar users enjoyed (item-based collaborative filtering) using the MovieLens dataset.

## How it works

- `backend` exposes `GET /movies` (full movie list) and `POST /recommend` (takes liked movie IDs, returns scored recommendations).
- `frontend` lets you search movies, mark the ones you like, and view recommendations.

## Tech Stack

- **Backend:** Laravel 13 (PHP 8.3+), API-only
- **Frontend:** Nuxt 4 (Vue 3, TypeScript)
- **Data:** `backend/storage/app/private/movies.json` (MovieLens-style dataset)

## Running locally

### Backend
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

### Frontend
```bash
cd frontend
npm install
npm run dev
```

The frontend runs on `http://localhost:3000` and talks to the backend API (set via `apiBase` in the Nuxt runtime config).
