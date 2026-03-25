<?php 
// src/Controllers/OrdersController.php

require_once __DIR__ . "/../Db/db.php";
require_once __DIR__ . "/../Http/Request.php";
require_once __DIR__ . "/../Http/Response.php";
require_once __DIR__ . "/../Http/Errors.php";
require_once __DIR__ . "/../Validation/validators.php";

final class OrdersController
{
    public function create(): void
    {
        $pdo = DB::conn();
        $body = Request::json();
        $errors = [];

        $listingId = V::int(V::required($body, "listing_id", $errors), "listing_id", $errors, 1);
        $buyerUserId = V::int($body["buyer_user_id"] ?? null, "buyer_user_id", $errors, 1);

        if (!empty($errors)){
            Errors::validation($errors);
            return;
        }

        // Buyer is optional in schema, but recommanded
        if ($buyerUserId !== null){
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$buyerUserId]);
            if (!$stmt->fetch()){
                Errors::validation(["buyer_user_id" => "Buyer user not found"]);
                return;
            }
        }

        $listing = $this->findListingById($pdo, $listingId);
        if (!$listing) {
            Errors::notFound("Listing not found");
            return;
        }

        if (($listing["status"] ?? null) !== "ACTIVE") {
            Errors::validation(["listing_id" => "Only ACTIVE listings can be ordered"]);
            return;
        }

        //Prevent buying your own listing
        $sellerUserId = $listing["seller_user_id"] !== null ? (int)$listing["seller_user_id"] : null;
        if ($buyerUserId !== null && $sellerUserId !== null && $buyerUserId === $sellerUserId) {
            Errors::validation(["buyer_user_id" => "Buyer cannot be the same as seller"]);
            return;
        }

        // Prevent duplicate pending/active orders for same listing
        $stmt = $pdo->prepare("
            SELECT id
            FROM orders
            WHERE listing_id = ?
              AND status IN ('INIT', 'PENDING_ONCHAIN')
            LIMIT 1
        ");
        $stmt->execute([$listingId]);
        if ($stmt->fetch()) {
            Errors::validation(["listing_id" => "An active order already exists for this listing"]);
            return;
        }

        $pdo->beginTransaction();
        try {
            // Create order
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    listing_id,
                    buyer_user_id,
                    status,
                    expected_amount_lovelace,
                    pay_tx_hash,
                    transfer_tx_hash
                ) VALUES (
                    :listing_id,
                    :buyer_user_id,
                    'PENDING_ONCHAIN',
                    :expected_amount_lovelace,
                    NULL,
                    NULL
                )
            ");
            $stmt->execute([
                ":listing_id" => $listingId,
                ":buyer_user_id" => $buyerUserId,
                ":expected_amount_lovelace" => (int)$listing["price_lovelace"],
            ]);

            $orderId = (int)$pdo->lastInsertId();

            // Reserve listing by marking it SOLD for now
            // Later you may introduce a RESERVED status.
            $stmt = $pdo->prepare("
                UPDATE listings
                SET status = 'SOLD'
                WHERE id = ? 
            ");
            $stmt->execute([$listingId]);

            $pdo->commit();

            Response::json([
                "ok" => true,
                "order" => $this->findOrderById($pdo, $orderId)
            ], 201);
        } catch (PDOException $e) {
            $pdo->rollBack();
            Errors::server("Failed to create order", ["db" => $e->getMessage()]);
        }
    }

    /**
     * GET /api/orders/:id
     */

    public function show(array $p): void
    {
        $pdo = DB::conn();
        $id = $this->parseId($p, "id");
        if ($id === null) return;

        $order = $this->findOrderById($pdo, $id);
        if (!$order){
            Errors::notFound("Order not found");
            return;
        }

        Response::json([
            "ok" => true,
            "order" => $order
        ]);
    }

    /**
     * POST /api/orders/:id/cancel
     *
     * Cancels an INIT or PENDING_ONCHAIN order.
     * Reopens listing by setting it back to ACTIVE.
     */
    
    public function cancel(array $p): void
    {
        $pdo = DB::conn();
        $id = $this->parseId($p, "id");
        if ($id === null) return;

        $order = $this->findOrderById($pdo, $id);
        if (!$order) {
            Errors::notFound("Order not found");
            return;
        }

        $status = $order["status"] ?? null;
        if (!in_array($status, ["INIT", "PENDING_ONCHAIN"], true)) {
            Errors::validation(["status" => "Only INIT or PENDING_ONCHAIN orders can be cancelled"]);
            return;
        }

        $listingId = (int)$order["listing_id"];

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = 'CANCELLED'
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            $stmt = $pdo->prepare("
                UPDATE listings
                SET status = 'ACTIVE'
                WHERE id = ?
            ");
            $stmt->execute([$listingId]);

            $pdo->commit();

            Response::json([
                "ok" => true,
                "order" => $this->findOrderById($pdo, $id)
            ]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            Errors::server("Failed to cancel order", ["db" => $e->getMessage()]);
        }
    }

    /**
     * POST /api/orders/:id/confirm
     *
     * Confirms a PENDING_ONCHAIN order.
     * Keeps the listing SOLD and transfers ticket ownership to buyer_user_id.
     */
    public function confirm(array $p): void
    {
        $pdo = DB::conn();
        $id = $this->parseId($p, "id");
        if ($id === null) return;

        $order = $this->findOrderById($pdo, $id);
        if (!$order) {
            Errors::notFound("Order not found");
            return;
        }

        if (($order["status"] ?? null) !== "PENDING_ONCHAIN") {
            Errors::validation(["status" => "Only PENDING_ONCHAIN orders can be confirmed"]);
            return;
        }

        $buyerUserId = $order["buyer_user_id"] !== null ? (int)$order["buyer_user_id"] : null;
        if ($buyerUserId === null) {
            Errors::validation(["buyer_user_id" => "Order buyer_user_id is required for confirmation"]);
            return;
        }

        $listingId = (int)$order["listing_id"];
        $ticketId = (int)$order["ticket_id"];

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = 'CONFIRMED'
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            $stmt = $pdo->prepare("
                UPDATE listings
                SET status = 'SOLD'
                WHERE id = ?
            ");
            $stmt->execute([$listingId]);

            $stmt = $pdo->prepare("
                UPDATE tickets
                SET owner_user_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$buyerUserId, $ticketId]);

            $pdo->commit();

            Response::json([
                "ok" => true,
                "order" => $this->findOrderById($pdo, $id)
            ]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            Errors::server("Failed to confirm order", ["db" => $e->getMessage()]);
        }
    }

    private function findListingById(PDO $pdo, int $id): ?array 
    {
        $stmt = $pdo->prepare("
            SELECT
              l.*,
              t.asset_id,
              s.seat_code,
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

    private function findOrderById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare("
            SELECT
              o.*,
              l.event_id,
              l.ticket_id,
              l.seller_user_id,
              l.price_lovelace,
              l.type AS listing_type,
              l.status AS listing_status,
              t.asset_id,
              s.seat_code,
              s.section,
              s.row_label,
              s.seat_number,
              s.class,
              e.title AS event_title
            FROM orders o
            INNER JOIN listings l ON l.id = o.listing_id
            INNER JOIN tickets t  ON t.id = l.ticket_id
            INNER JOIN seats s    ON s.id = t.seat_id
            INNER JOIN events e   ON e.id = l.event_id
            WHERE o.id = ?
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
