<?php 

require_once __DIR__ . "/Http/Router.php";
require_once __DIR__ . "/Http/Response.php";

require_once __DIR__ . "/Http/Request.php";
require_once __DIR__ . "/Http/Errors.php";
require_once __DIR__ . "/Validation/validators.php";
require_once __DIR__ . "/Db/db.php";
require_once __DIR__ . "/Controllers/EventsController.php";
require_once __DIR__ . "/Controllers/EventMediaController.php";
require_once __DIR__ . "/Controllers/SeatMapController.php";
require_once __DIR__ . "/Controllers/SeatsController.php";
require_once __DIR__ . "/Controllers/ListingsController.php";

/**
 * Controllers (create these files later; for now we use inline handlers).
 * When you're ready, replace inline handlers with:
 *  require_once __DIR__ . "/Controllers/EventsController.php";
 *  $events = new EventsController();
 *  $router->add("GET", "/api/events", [$events, "index"]);
 */

$router = new Router();

/* Health*/
$router->add("GET", "/api/health", function(){
    Response::json(["ok" => true, "time" => time()]);
});

$router->add("GET", "/api/db/ping", function(){
    require_once __DIR__ . "/Db/db.php";
    $pdo = DB::conn();
    $row = $pdo->query("SELECT 1 AS ok")->fetch();
    Response::json(["db" => "ok", "result" => $row]);
});

$router->add("POST", "/api/_test/validate", function () {
    $body = Request::json();
    $errors = [];

    $title = V::str(V::required($body, "title", $errors), "title", $errors, 3, 255);
    $price = V::int($body["price"] ?? null, "price", $errors, 0, 10_000_000);

    if(!empty($errors)){
        Errors::validation($errors);
        return;
    }
    Response::json(["ok" => true, "title" => $title, "price" => $price]);
});

/* Auth */
$router->add("POST", "/api/auth/register", fn() => Response::json(["todo" => "auth/register"]));
$router->add("POST", "/api/auth/login",    fn() => Response::json(["todo" => "auth/login"]));
$router->add("POST", "/api/auth/logout",   fn() => Response::json(["todo" => "auth/logout"]));
$router->add("GET", "/api/auth/me", fn() => Response::json(["todo" => "auth/me"]));

$router->add("POST", "/api/auth/wallet/challenge", fn() => Response::json(["todo" => "auth/wallet/challenge"]));
$router->add("POST", "/api/auth/wallet/verify",    fn() => Response::json(["todo" => "auth/wallet/verify"]));

/* Users */
$router->add("GET", "/api/users/me", fn() => Response::json(["todo" => "users/me"]));
$router->add("PATCH", "/api/users/me",         fn() => Response::json(["todo" => "users/me update"]));
$router->add("PATCH", "/api/users/me/wallet",  fn() => Response::json(["todo" => "users/me wallet"]));
$router->add("GET", "/api/users/:id", fn($p) => Response::json(["todo" => "users/show", "params" => $p]));

/* Venues */
$router->add("POST", "/api/venues", fn() => Response::json(["todo" => "venues/create"]));
$router->add("GET", "/api/venues", fn() => Response::json(["todo" => "venues/index"]));
$router->add("GET", "/api/venues/:id", fn($p) => Response::json(["todo" => "venues/show", "params" => $p]));
$router->add("PATCH", "/api/venues/:id", fn($p) => Response::json(["todo" => "venues/update", "params" => $p]));
$router->add("DELETE", "/api/venues/:id", fn($p) => Response::json(["todo" => "venues/delete", "params" => $p]));

$events = new EventsController();

/* Events */
$router->add("POST",  "/api/events",           [$events, "create"]);
$router->add("GET",   "/api/events",           [$events, "index"]);
$router->add("GET",   "/api/events/:id",       [$events, "show"]);
$router->add("PATCH", "/api/events/:id",       [$events, "update"]);
$router->add("POST",  "/api/events/:id/publish", [$events, "publish"]);

$router->add("POST", "/api/events/:id/cancel",  fn($p) => Response::json(["todo" => "events/cancel", "params" => $p]));
$router->add("GET",  "/api/events/:id/dashboard", fn($p) => Response::json(["todo" => "events/dashboard", "params" => $p]));

$eventMedia = new EventMediaController();
/* Event media (posters, banners, gallery) */
$router->add("POST","/api/events/:id/media", [$eventMedia, "upload"]);
$router->add("GET", "/api/events/:id/media", [$eventMedia, "index"]);
$router->add("DELETE", "/api/events/:id/media/:mediaId", [$eventMedia, "delete"]);

$seatMap = new SeatMapController();
$seats = new SeatsController();

/* Seat map + Seats */
$router->add("PUT", "/api/events/:id/seatmap", [$seatMap, "upsert"]);
$router->add("GET", "/api/events/:id/seatmap", [$seatMap, "show"]);

$router->add("POST", "/api/events/:id/seats/bulk", [$seats, "bulkCreate"]);
$router->add("GET", "/api/events/:id/seats", [$seats, "index"]);
$router->add("PATCH", "/api/seats/:seatId", [$seats, "update"]);

/* Chain config (event-level)*/
$router->add("PUT", "/api/events/:id/chain-config", fn($p) => Response::json(["todo" => "events/chain_config", "params" => $p]));

/* Tickets */
$router->add("GET", "/api/tickets/:ticketId", fn($p) => Response::json(["todo" => "tickets/show", "params" => $p]));
$router->add("GET", "/api/events/:id/tickets", fn($p) => Response::json(["todo" => "tickets/event_index", "params" => $p]));
$router->add("GET", "/api/users/me/tickets", fn() => Response::json(["todo" => "tickets/my_tickets"]));

/* Ticket mint lifecycle */
$router->add("POST", "/api/events/:id/tickets/mint-plan", fn($p) => Response::json(["todo" => "tickets/mint_plan", "params" => $p]));
$router->add("POST", "/api/events/:id/tickets/mint/unsigned", fn($p) => Response::json(["todo" => "tickets/mint_unsigned", "params" => $p]));
$router->add("POST", "/api/events/:id/tickets/mint/confirm",  fn($p) => Response::json(["todo" => "tickets/mint_confirm", "params" => $p]));

$listings = new ListingsController();
/* Listings */
$router->add("POST", "/api/listings", [$listings, "create"]);
$router->add("GET",  "/api/listings", [$listings, "index"]);
$router->add("GET",  "/api/listings/:id", [$listings, "show"]);
$router->add("POST", "/api/listings/:id/cancel", [$listings, "cancel"]);

/* Orders (buy flow) */
$router->add("POST", "/api/orders", fn() => Response::json(["todo" => "orders/create"]));
$router->add("GET",  "/api/orders/:id",fn($p) => Response::json(["todo" => "orders/show", "params" => $p]));

$router->add("POST", "/api/orders/:id/checkout/unsigned",fn($p) => Response::json(["todo" => "orders/checkout_unsigned", "params" => $p]));
$router->add("POST", "/api/orders/:id/confirm", fn($p) => Response::json(["todo" => "orders/confirm", "params" => $p]));
$router->add("POST", "/api/orders/:id/cancel", fn($p) => Response::json(["todo" => "orders/cancel", "params" => $p]));

/*Gate (check-in)*/
$router->add("GET",  "/api/gate/events/today", fn() => Response::json(["todo" => "gate/events_today"]));
$router->add("GET",  "/api/gate/events/:id/stats", fn($p) => Response::json(["todo" => "gate/event_stats", "params" => $p]));

$router->add("POST", "/api/gate/checkin/resolve",  fn() => Response::json(["todo" => "gate/checkin_resolve"]));
$router->add("POST", "/api/gate/checkin/unsigned", fn() => Response::json(["todo" => "gate/checkin_unsigned"]));
$router->add("POST", "/api/gate/checkin/confirm",  fn() => Response::json(["todo" => "gate/checkin_confirm"]));

/* Revenue split + Settlement */
$router->add("PUT", "/api/events/:id/revenue-split", fn($p) => Response::json(["todo" => "revenue_split/upsert", "params" => $p]));
$router->add("GET", "/api/events/:id/revenue-split", fn($p) => Response::json(["todo" => "revenue_split/show", "params" => $p]));

$router->add("POST", "/api/events/:id/revenue/pot/prepare",         fn($p) => Response::json(["todo" => "revenue/pot_prepare", "params" => $p]));
$router->add("POST", "/api/events/:id/revenue/distribute/unsigned", fn($p) => Response::json(["todo" => "revenue/distribute_unsigned", "params" => $p]));
$router->add("POST", "/api/events/:id/revenue/distribute/confirm",  fn($p) => Response::json(["todo" => "revenue/distribute_confirm", "params" => $p]));

/*Chain utilities (Blockfrost proxy)*/
$router->add("GET",  "/api/chain/tx/:hash", fn($p) => Response::json(["todo" => "chain/tx", "params" => $p]));
$router->add("GET",  "/api/chain/address/:bech32/utxos", fn($p) => Response::json(["todo" => "chain/address_utxos", "params" => $p]));
$router->add("GET",  "/api/chain/asset/:assetId", fn($p) => Response::json(["todo" => "chain/asset", "params" => $p]));
$router->add("POST", "/api/chain/verify", fn() => Response::json(["todo" => "chain/verify"]));

/*Admin*/
$router->add("GET",   "/api/admin/users", fn() => Response::json(["todo" => "admin/users"]));
$router->add("PATCH", "/api/admin/users/:id", fn($p) => Response::json(["todo" => "admin/users_update", "params" => $p]));
$router->add("GET",   "/api/admin/audit", fn() => Response::json(["todo" => "admin/audit"]));

return $router;



