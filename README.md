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

Requirements:

- XAMPP
- PHP 8.2+
- MySQL / MariaDB
- Local path

