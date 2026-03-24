<?php

require_once __DIR__ . "/../DB/db.php";
require_once __DIR__ . "/../Http/Response.php";
require_once __DIR__ . "/../Http/Request.php";
require_once __DIR__ . "/../Http/Errors.php";
require_once __DIR__ . "/../Validation/validators.php";

final class SeatMapController
{
    // PUT /api/events/:id/seatmap
    // Body: { map_json: <any JSON object/array> }
    public function upsert(array $p): void
    {
        $eventId = $this->parseId($p, "id");
        if ($eventId === null) return;

        $pdo = DB::conn();

        // Ensure event exists
        $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
        if (!$stmt->fetch()) {
            Errors::notFound("Event not found");
            return;
        }

        $body = Request::json();
        $errors = [];

        $mapJson = $body["map_json"] ?? null;
        if ($mapJson === null) {
            $errors["map_json"] = "Required";
        } elseif (!is_array($mapJson)) {
            $errors["map_json"] = "Must be a JSON object/array";
        }

        if (!empty($errors)) {
            Errors::validation($errors);
            return;
        }

        $jsonStr = json_encode($mapJson, JSON_UNESCAPED_SLASHES);
        if ($jsonStr === false){
            Errors::badRequest("Failed to encode map_json");
            return;
        }

        // Upsert (event_id UNIQUE)
        $stmt = $pdo->prepare("
            INSERT INTO seat_maps (event_id, map_json) 
            VALUES (:event_id, :map_json)
            ON DUPLICATE KEY UPDATE 
            map_json = VALUES(map_json)
        ");
        $stmt->execute([
            ":event_id" => $eventId,
            ":map_json" => $jsonStr
        ]);

        Response::json(["ok" => true]);
    }

    // GET /api/events/:id/seatmap
    public function show(array $p): void
    {
        $eventId = $this->parseId($p, "id");
        if ($eventId === null) return;

        $pdo = DB::conn();

        $stmt = $pdo->prepare("
            SELECT event_id, map_json, created_at 
            FROM seat_maps 
            WHERE event_id = ? LIMIT 1
        ");
        $stmt->execute([$eventId]);
        $row = $stmt->fetch();

        if (!$row) {
            Errors::notFound("Seat map not found");
            return;
        }

        //map_json is stored as JSON, fetched as string in many PDO configs; decode safely
        $map = $row["map_json"];
        if (is_string($map)) {
            $decoded = json_decode($map, true);
            $row["map_json"] = $decoded ?? $map;
        }

        Response::json(["ok" => true, "seat_map" => $row]);
    }

    private function parseId(array $p, string $key):  ?int 
    {
        $raw = $p[$key] ?? null;
        if ($raw === null || !preg_match('/^\d+$/', (string)$raw)) {
            Errors::validation([$key => "Invalid id"]);
            return null;
        }
        return (int)$raw;
    }
}