<?php

require_once __DIR__ . "/../Db/db.php";
require_once __DIR__ . "/../Http/Request.php";
require_once __DIR__ . "/../Http/Response.php";
require_once __DIR__ . "/../Http/Errors.php";
require_once __DIR__ . "/../Validation/validators.php";

final class SeatsController 
{
    public function bulkCreate(array $p): void 
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
        $seats = $body["seats"] ?? null;

        if (!is_array($seats) || count($seats) === 0) {
            Errors::validation(["seats" => "Must be a non-empty array"]);
            return;
        }

        $allowedClass = ["VIP", "REGULAR", "ECONOMY"];
        $errors = [];
        $clean = [];

        foreach ($seats as $index => $seat){
            if (!is_array($seat)){
                $errors["seats[$index]"] = "Must be an object";
                continue;
            }

            $err = [];

            $section = V::str(V::required($seat, "section", $err), "section", $err, 1, 64);
            $row = V::str(V::required($seat, "row_label", $err), "row_label", $err, 1, 64);
            $num = V::str(V::required($seat, "seat_number", $err), "seat_number", $err, 1, 64);
            $code = V::str(V::required($seat, "seat_code", $err), "seat_code", $err, 1, 128);

            $class = V::enum($seat["class"] ?? "REGULAR", "class", $err, $allowedClass);

            $face = V::int(V::required($seat, "face_price_lovelace", $err), "face_price_lovelace", $err,0);
            $maxRes = V::int(V::required($seat, "max_resale_lovelace", $err), "max_resale_lovelace", $err, 0);

            if (!empty($err)){
                $errors["seats[$index]"] = $err;
                continue;
            }

            if ($maxRes !== null && $face !== null && $maxRes < $face) {
                $errors["seats[$index]"] = ["max_resale_lovelace" => "Must be >= face_price_lovelace"];
                continue;
            }

            $clean[] = [
                "section" => $section,
                "row_label" => $row,
                "seat_number" => $num,
                "seat_code" => $code,
                "class" => $class,
                "face_price_lovelace" => $face,
                "max_resale_lovelace" => $maxRes
            ];
        }

        if (!empty($errors)) {
            Errors::validation($errors);
            return;
        }

        // Insert in a transaction
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO seats (
                    event_id, section, row_label, seat_number, seat_code, class, 
                    face_price_lovelace, max_resale_lovelace, status
                ) VALUES (
                    :event_id, :section, :row_label, :seat_number, :seat_code, :class, 
                    :face_price_lovelace, :max_resale_lovelace, 'AVAILABLE'
                )
            ");
            $inserted = 0;
            foreach ($clean as $seat) {
                $stmt->execute([
                    ":event_id" => $eventId,
                    ":section" => $seat["section"],
                    ":row_label" => $seat["row_label"],
                    ":seat_number" => $seat["seat_number"],
                    ":seat_code" => $seat["seat_code"],
                    ":class" => $seat["class"],
                    ":face_price_lovelace" => $seat["face_price_lovelace"],
                    ":max_resale_lovelace" => $seat["max_resale_lovelace"],
                ]);
                $inserted++;
            }

            $pdo->commit();

            Response::json(["ok" => true, "inserted" => $inserted], 201);
        } catch (PDOException $e) {
            $pdo->rollBack();
            
            // Duplicate seat_code per event (uniq_event_seat)
            if ((int)$e->getCode() === 23000) {
                Errors::validation(["seats" => "Duplicate seat_code for this event (check uniq_event_seat constraint)"]);
                return;
            }

            Errors::server("Failed to insert seats", ["db" => $e->getMessage()]);
        }
    }

    public function index(array $p): void
    {
        $eventId = $this->parseId($p, "id");
        if ($eventId === null) return;

        $pdo = DB::conn();

        $section = Request::query("section");
        $class = Request::query("class");
        $status = Request::query("status");
        $q = Request::query("q");

        $limit = (int)(Request::query("limit", "200") ?? "200");
        $offset = (int)(Request::query("offset", "0") ?? "0");

        if ($limit <= 0) $limit = 200;
        if ($limit > 1000) $limit = 1000;
        if ($offset < 0) $offset = 0;

        $where = ["s.event_id = :event_id"];
        $params = [":event_id" => $eventId];

        if ($section !== null && trim($section) !== "") {
            $where[] = "s.section = :section";
            $params[":section"] = trim($section);
        }

        if ($class !== null && trim($class) !== "")  {
            $allowed = ["VIP", "REGULAR", "ECONOMY"];
            if (!in_array($class, $allowed, true)){
                Errors::validation(["class" => "Must be one of: " . implode(", ", $allowed)]);
                return;
            }
            $where[] = "s.class = :class";
            $params[":class"] = $class;
        }

        if ($status !== null && trim($status) !== "") {
            $allowed = ["AVAILABLE","MINTED","LISTED","SOLD","USED","CANCELLED"];
            if (!in_array($status, $allowed, true)) {
                Errors::validation(["status" => "Must be one of: " . implode(", ", $allowed)]);
                return;
            }
            $where[] = "s.status = :status";
            $params[":status"] = $status;
        }

        if ($q !== null && trim($q) !== "") {
            $where[] = "s.seat_code LIKE :q";
            $params[":q"] = "%" . trim($q) . "%";
        }

        $sql = "
            SELECT
              s.id, s.event_id, s.section, s.row_label, s.seat_number,
              s.seat_code, s.class, s.face_price_lovelace, s.max_resale_lovelace,
              s.status, s.created_at
            FROM seats s
            WHERE " . implode(" AND ", $where) . "
            ORDER BY s.section ASC, s.row_label ASC, s.seat_number ASC, s.id ASC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetchAll();

        Response::json([
            "ok" => true,
            "limit" => $limit,
            "offset" => $offset,
            "seats" => $row
        ]);
    }


    public function update(array $p): void
    {
        $seatId = $this->parseId($p, "seatId");
        if ($seatId === null) return;

        $pdo = DB::conn();

        $stmt = $pdo->prepare("SELECT * FROM seats WHERE id = ? LIMIT 1");
        $stmt->execute([$seatId]);
        $existing = $stmt->fetch();

        if (!$existing){
            Errors::notFound("Seat not found");
            return;
        }

        $body = Request::json();
        $error = [];

        $class = V::enum($body["class"] ?? null, "class", $error, ["VIP", "REGULAR", "ECONOMY"]);
        $face = V::int($body["face_price_lovelace"] ?? null, "face_price_lovelace", $error, 0);
        $maxR = V::int($body["max_resale_lovelace"] ?? null, "max_resale_lovelace", $error, 0);
        $status = V::enum($body["status"] ?? null, "status", $error, ["AVAILABLE","MINTED","LISTED","SOLD","USED","CANCELLED"]);

        if (!empty($error)) {
            Errors::validation($error);
            return;
        }

        $fields = [];
        $params = [":id" => $seatId];

        if ($class !== null) { $fields[] = "class = :class"; $params[":class"] = $class;}
        if ($face !== null) { $fields[] = "face_price_lovelace = :face"; $params[":face"] = $face;}
        if ($maxR !== null) {$fields[] = "max_resale_lovelace = :maxr"; $params[":maxr"] = $maxR;}
        if ($status !== null) {$fields[] = "status = :status"; $params[":status"] = $status;}

        //rule: max >= face if both are present/changed
        $newFace = $face ?? (int)$existing["face_price_lovelace"];
        $newMax = $maxR ?? (int)$existing["max_resale_lovelace"];
        if ($newMax < $newFace) {
            Errors::validation(["max_resale_lovelace" => "Must be >= face_price_lovelace"]);
            return;
        }

        if (empty($fields)) {
            Response::json(["ok" => true, "seat" => $existing, "message" => "No changes"]);
            return;
        }

        $sql = "UPDATE seats SET " . implode(", ", $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $stmt = $pdo->prepare("
            SELECT 
                id, event_id, section, row_label, seat_number, seat_code, class,
                face_price_lovelace, max_resale_lovelace, status, created_at
            FROM seats WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$seatId]);

        Response::json(["ok" => true, "seat" => $stmt->fetch()]);
    }


    private function parseId(array $p, string $key) : ?int 
    {
        $raw = $p[$key] ?? null;
        if ($raw === null || !preg_match('/^\d+$/', (string)$raw)){
            Errors::validation([$key => "Invalid id"]);
            return null;
        }
        return (int)$raw;
    }    
}