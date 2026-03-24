# Sports Tickets Backend

Backend API for a sports ticket marketplace built with PHP, MySQL/MariaDB, and Apache.

This service manages events, media uploads, seat maps, seats, listings, and later will support orders, wallet verification, ticket mint workflows, on-chain verification, and settlement flows.

---

## Tech Stack

- PHP 8.2
- MySQL / MariaDB
- Apache (XAMPP)
- REST-style JSON API

---

## Current Status

Implemented:

- health check
- database connection test
- events
  - create
  - list
  - show
  - update
  - publish
- event media
  - upload
  - list
  - delete
- seat maps
  - upsert
  - show
- seats
  - bulk create
  - list
  - update
- listings
  - create
  - list
  - show
  - cancel

Planned next:

- orders
- auth
- wallet challenge / verify
- ticket mint lifecycle
- chain verification
- revenue split workflow

---

## Project Purpose

This backend is part of a fullstack sports ticket marketplace that combines:

- traditional marketplace features
- Cardano-ready ticket lifecycle flows
- event media and seat management
- future Lucid + Blockfrost integration

The long-term goal is to support:

- event creation
- NFT ticket mint planning
- ticket listing and resale
- gate check-in
- settlement and revenue distribution

---

## Project Structure

```text
public/
  index.php
  .htaccess
  uploads/

src/
  Config/
    env.php
  Controllers/
    EventMediaController.php
    EventsController.php
    ListingsController.php
    SeatMapController.php
    SeatsController.php
  Db/
    db.php
  Http/
    Errors.php
    Request.php
    Response.php
    Router.php
  Validation/
    validators.php
  routes.php

storage/
  logs/

migrations/

.env
.env.example
README.md
```
---

## Local Development

### Requirements:

- XAMPP
- PHP 8.2+
- MySQL / MariaDB

### Local path

```text
C:\xampp\htdocs\sports-tickets-backend
```

### Base URL

```text
http://localhost/sports-tickets-backend/public
```

### Start locally
1. Start Apache in XAMPP
2. Start MySQL in XAMPP
3. Open:

```text
http://localhost/sports-tickets-backend/public/api/health
```

### Test database connection
```text
http://localhost/sports-tickets-backend/public/api/db/ping
```

---

## API Routes

### Health
- GET /api/health
- GET /api/db/ping

### Events
- POST /api/events
- GET /api/events
- GET /api/events/:id
- PATCH /api/events/:id
- POST /api/events/:id/publish

### Event Media
- POST /api/events/:id/media
- GET /api/events/:id/media
- DELETE /api/events/:id/media/:mediaId

### Seat Maps
- PUT /api/events/:id/seatmap
- GET /api/events/:id/seatmap

### Seats
- POST /api/events/:id/seats/bulk
- GET /api/events/:id/seats
- PATCH /api/seats/:seatId

### Listings
- POST /api/listings
- GET /api/listings
- GET /api/listings/:id
- POST /api/listings/:id/cancel

### Orders
- POST /api/orders
- GET /api/orders/:id
- POST /api/orders/:id/cancel

---

## Current Business Flow
The backend currently supports this marketplace flow:

1. Create an event
2. Upload event media
3. Add seat map JSON
4. Bulk insert seats
5. Create ticket listings
6. Create and cancel orders

---
## Database Notes

This backend uses a relational structure for:
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

Important notes:
- seat_maps.event_id should be unique
- uploaded files are stored on disk and referenced by URL in event_media
- listings depend on tickets
- seat creation is designed to happen in bulk

---

## Uploads
Uploaded files are stored in:

```text
public/uploads/events/{eventId}/
```

Supported mime types:

- image/jpeg
- image/png
- image/webp

---

## Git Notes

Do not commit:
- .env
- uploaded files
- local logs
- secrets

