<?php

require_once __DIR__ . "/../Db/db.php";
require_once __DIR__ . "/../Http/Response.php";
require_once __DIR__ . "/../Http/Request.php";
require_once __DIR__ . "/../Http/Errors.php";
require_once __DIR__ . "/../Validation/validators.php";

final class ListingsController
{
    /**
     * POST /api/listings
     * Body:
     * {
     *   "event_id": 1,
     *   "ticket_id": 10,
     *   "seller_user_id": 1,      // nullable for PRIMARY if needed
     *   "price_lovelace": 20000000,
     *   "type": "PRIMARY" | "RESALE"
     * }
     */

    public function create(): void
    {
        $pdo = DB::conn();
        $body = Request::json();
        $errors = [];

        $eventId = V::int(V::required($body, "event_id", $errors), "event_id", $errors, 1);
        $ticketId = V::int(V::required($body, "ticket_id", $errors), "ticket_id", $errors, 1);
        $sellerUserId = V::int($body["seller_user_id"] ?? null, "seller_user_id", $errors, 1);
        $price = V::int(V::required($body, "price_lovelace", $errors), "price_lovelace", $errors, 0);
        $type = V::enum($body["type"] ?? "RESALE", "type", $errors, ["PRIMARY", "RESALE"]);

        if (!empty($errors)){
            Errors::validation($errors);
            return;
        }

        // Event must exist
        $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ? LIMIT 1");
        $stmt->execute([$eventId]);
        if (!$stmt->fetch()){
            Errors::validation(["event_id" => "Event not found"]);
            return;
        }

        // Ticket must exist and belong to the event
        $stmt = $pdo->prepare("
            SELECT id, event_id, owner_user_id 
            FROM tickets
            WHERE id = ? 
            LIMIT 1
        ");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket){
            Errors::validation(["ticket_id" => "Ticket not found"]);
            return;
        }

        if ((int)$ticket["event_id"] !== $eventId){
            Errors::validation(["ticket_id" => "Ticket does not belong to the event"]);
            return;
        }

        // For resale, seller should match ticket owner (for now)
        if ($type === "RESALE"){
            if ($sellerUserId === null) {
                Errors::validation(["seller_user_id" => "Required for RESALE listings"]);
                return;
            }

            $ticketOwnerId = $ticket["owner_user_id"] !== null ? (int)$ticket["owner_user_id"] : null;
            if ($ticketOwnerId === null || $ticketOwnerId !== $sellerUserId) {
                Errors::validation(["seller_user_id" => "Seller must be current ticket owner"]);
                return;
            }
        }

        // Optional seller existence check
        if ($sellerUserId !== null){
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$sellerUserId]);
            if (!$stmt->fetch()) {
                Errors::validation(["seller_user_id" => "Seller user not found"]);
                return;
            }
        }

        // Prevent multiple ACTIVE listings for the same ticket
        $stmt = $pdo->prepare("
            SELECT id FROM listings 
            WHERE ticket_id = ? AND status = 'ACTIVE' 
            LIMIT 1
        ");
        $stmt->execute([$ticketId]);
        if ($stmt->fetch()) {
            Errors::validation(["ticket_id" => "An active listing already exists for this ticket"]);
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO listings (
                event_id, ticket_id, seller_user_id,
                price_lovelace, type, status
            ) VALUES (
                :event_id, :ticket_id, :seller_user_id,
                :price_lovelace, :type, 'ACTIVE'
            )
        ");

        $stmt->execute([
            ":event_id" => $eventId,
            ":ticket_id" => $ticketId,
            ":seller_user_id" => $sellerUserId,
            ":price_lovelace" => $price,
            ":type" => $type
        ]);

        $id = (int)$pdo->lastInsertId();

        Response::json([
            "ok" => true,
            "listing" => $this->findById($pdo, $id)
        ], 201);
    }

    /**
     * GET /api/listings
     * Query:
     *  - event_id
     *  - ticket_id
     *  - status
     *  - type
     *  - seller_user_id
     *  - limit
     *  - offset
     */

    public function index(): void
    {
        $pdo = DB::conn();

        $eventId = Request::query("event_id");
        $ticketId = Request::query("ticket_id");
        $status = Request::query("status");
        $type = Request::query("type");
        $sellerUserId = Request::query("seller_user_id");
        
        $limit = (int)(Request::query("limit", "100") ?? "100");
        $offset = (int)(Request::query("offset", "0") ?? "0");

        if ($limit <= 0) $limit = 100;
        if ($offset > 500) $limit = 500;
        if ($offset < 0) $offset = 0;
        
        $where = [];
        $params = [];

        if ($eventId !== null && $eventId !== "") {
            if (!preg_match('/^\d+$/', $eventId)) {
                Errors::validation(["event_id" => "Must be an integer"]);
                return;
            }
            $where[] = "l.event_id = :event_id";
            $params[":event_id"] = (int)$eventId;
        }

        if ($ticketId !== null && $ticketId !== "") {
            if (!preg_match('/^\d+$/', $ticketId)) {
                Errors::validation(["ticket_id" => "Must be an integer"]);
                return;
            }
            $where[] = "l.ticket_id = :ticket_id";
            $params[":ticket_id"] = (int)$ticketId;
        }

        if ($sellerUserId !== null && $sellerUserId !== "") {
            if (!preg_match('/^\d+$/', $sellerUserId)) {
                Errors::validation(["seller_user_id" => "Must be an integer"]);
                return;
            }
            $where[] = "l.seller_user_id = :seller_user_id";
            $params[":seller_user_id"] = (int)$sellerUserId;
        }

        if ($status !== null && $status !== "") {
            $allowed = ["ACTIVE", "SOLD", "CANCELLED", "EXPIRED"];
            if (!in_array($status, $allowed, true)) {
                Errors::validation(["status" => "Must be one of: " . implode(", ", $allowed)]);
                return;
            }
            $where[] = "l.status = :status";
            $params[":status"] = $status;
        }

        if ($type !== null && $type !== "") {
            $allowed = ["PRIMARY", "RESALE"];
            if (!in_array($type, $allowed, true)) {
                Errors::validation(["type" => "Must be one of: " . implode(", ", $allowed)]);
                return;
            }
            $where[] = "l.type = :type";
            $params[":type"] = $type;
        }

        $sql = "
            SELECT
              l.*,
              t.asset_id,
              t.owner_user_id,
              s.seat_code,
              s.section,
              s.row_label,
              s.seat_number,
              s.class,
              e.title AS event_title
            FROM listings l
            INNER JOIN tickets t ON t.id = l.ticket_id
            INNER JOIN seats s   ON s.id = t.seat_id
            INNER JOIN events e  ON e.id = l.event_id
        ";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY l.created_at DESC, l.id DESC LIMIT {$limit} OFFSET {$offset}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::json([
            "ok" => true,
            "limit" => $limit,
            "offset" => $offset,
            "listing" => $stmt->fetchAll()
        ]);
    }

    /**
     * GET /api/listings/:id
     */

    public function show(array $p): void
    {
        $pdo = DB::conn();
        $id = $this->parseId($p, "id");
        if ($id === null) return;

        $listing = $this->findById($pdo, $id);
        if (!$listing){
            Errors::notFound("Listing not found");
            return;
        }
        Response::json(["ok" => true, "listing" => $listing]);
    }

    /**
     * POST /api/listings/:id/cancel
     */
    public function cancel(array $p): void
    {
        $pdo = DB::conn();
        $id = $this->parseId($p, "id");
        if ($id === null) return;

        $listing = $this->findById($pdo, $id);
        if (!$listing) {
            Errors::notFound("Listing not found");
            return;
        }

        if (($listing["status"] ?? null) !== "ACTIVE") {
            Errors::validation(["status" => "Only ACTIVE listings can be cancelled"]);
            return;
        }

        $stmt = $pdo->prepare("
            UPDATE listings
            SET status = 'CANCELLED'
            WHERE id = ?
        ");
        $stmt->execute([$id]);

        Response::json([
            "ok" => true,
            "listing" => $this->findById($pdo, $id)
        ]);
    }

    private function findById(PDO $pdo, int $id): ?array 
    {
        $stmt = $pdo->prepare("
            SELECT
              l.*,
              t.asset_id,
              t.owner_user_id,
              s.seat_code,
              s.section,
              s.row_label,
              s.seat_number,
              s.class,
              e.title AS event_title
            FROM listings l
            INNER JOIN tickets t ON t.id = l.ticket_id
            INNER JOIN seats s   ON s.id = t.seat_id
            INNER JOIN events e  ON e.id = l.event_id
            WHERE l.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function parseId(array $p, string $key): ?int
    {
        $raw = $p[$key] ?? null;
        if ($raw === null || !preg_match('/^\d+$/', (string)$raw)){
            Errors::validation([$key => "Invalid id"]);
            return null;
        }
        return (int)$raw;
    }
}