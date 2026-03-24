# AGENTS.md

## Project
PHP backend for a sports ticket marketplace.

## Goal
Maintain and extend this backend incrementally using the existing code style and project structure.

## Stack
- PHP 8.2
- MySQL / MariaDB
- Apache via XAMPP
- REST-style JSON API

## Current structure
- `public/index.php` is the HTTP entry point
- `public/uploads/` stores uploaded files
- `src/routes.php` defines routes and wires controllers
- `src/Controllers` contains HTTP controllers
- `src/Db/db.php` contains the PDO connection
- `src/Config/env.php` loads environment variables
- `src/Http` contains request/response/router/error helpers
- `src/Middleware/cors.php` handles CORS headers
- `src/Validation/validators.php` contains validation helpers
- `src/Repositories` exists for future DB abstraction
- `src/Services` exists for future business/domain services
- `storage/logs` is for logs
- `migrations` is for database migration scripts

## Existing controller pattern
Follow the style used in:
- `EventsController`
- `EventMediaController`
- `SeatMapController`
- `SeatsController`
- `ListingsController`
- `OrdersController`

When adding backend features:
- add or extend a controller in `src/Controllers`
- wire the route in `src/routes.php`
- use existing helpers before introducing new abstractions

## Coding rules
- keep changes minimal and focused
- do not refactor unrelated files
- preserve the current route structure unless explicitly asked
- return JSON responses only
- do not add a framework
- do not introduce Composer packages unless explicitly asked
- keep code readable and consistent with the existing project style

## Request / response rules
Use existing helpers:
- `Request::json()`
- `Request::query()`
- `Request::file()`
- `Response::json()`
- `Errors::*`
- `V::*`

## Error handling
- use `Errors::validation()` for validation failures
- use `Errors::notFound()` for missing resources
- use `Errors::badRequest()` for malformed requests
- use `Errors::server()` for unexpected failures
- keep error response shapes consistent with the current API

## Database rules
- use `DB::conn()` for PDO access
- prefer prepared statements
- use transactions for multi-step operations
- preserve current schema assumptions unless explicitly asked to change them

## Current domain model
The backend currently includes:
- users
- venues
- events
- event_media
- seat_maps
- seats
- tickets
- listings
- orders
- chain_txs
- revenue_splits

## Business flow implemented so far
- create and manage events
- upload and list event media
- upsert and fetch seat maps
- bulk insert and query seats
- create and manage listings
- create, show, and cancel orders

## Important constraints
- this project is framework-free PHP
- this project is being built in vertical slices
- do not change frontend-facing response shapes casually
- do not commit secrets or rely on `.env` being committed
- uploaded files live under `public/uploads`

## Preferred development style
When asked to add a feature:
1. inspect existing controllers and routes first
2. match the established patterns
3. make the smallest clean change that completes the task
4. mention which files changed
5. avoid touching unrelated behavior

## Testing guidance
When adding backend functionality:
- keep routes testable with curl
- preserve local XAMPP compatibility
- prefer examples using:
  - `http://localhost/sports-tickets-backend/public/api/...`

## What to avoid
- large refactors
- introducing frameworks
- changing naming conventions without reason
- changing database schema unless requested
- replacing helper utilities with a new style

## Example task style
Good tasks for this repo:
- add a new controller following existing patterns
- add a new route in `src/routes.php`
- extend an existing controller with one focused action
- add validation to an existing endpoint
- fix a response shape bug without unrelated refactoring