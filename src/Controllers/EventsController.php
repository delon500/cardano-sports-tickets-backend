<?php 

require_once __DIR__ . "/../Db/db.php";
require_once __DIR__ . "/../Http/Request.php";
require_once __DIR__ . "/../Http/Response.php";
require_once __DIR__ . "/../Http/Errors.php";
require_once __DIR__ . "/../Validation/validators.php";

final class EventsController 
{
    public function create(): void 
    {
        $body = Request::json();
        $errors = [];

        $organizerUserId = V::int(V::required($body, "organizer_user_id", $errors), "organizer_user_id", $errors, 1);
        $venueId = V::int($body["venue_id"] ?? null, "venue_id", $errors, 1);

        $title = V::str(V::required($body, "title", $errors), "title", $errors, 3, 255);
        $description = V::str($body["description"] ?? null, "description", $errors, 0, 5000);

        $eventCode = V::str(V::required($body, "event_code", $errors), "event_code", $errors, 3, 64);
        $startsAt = V::datetime(V::required($body, "starts_at", $errors), "starts_at", $errors);
        $endsAt = V::datetime($body["ends_at"] ?? null, "ends_at", $errors);

        if (!empty($errors)){
            Errors::validation($errors);
            return;        
        }

        $pdo = DB::conn();

        // Ensure organizer exists (basic integrity; can remove if you trust FK)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$organizerUserId]);
        if(!$stmt->fetch()){
            Errors::validation(["organizer_user_id" => "Organizer user not found"]);
            return;
        }

        // Optional venue existence check
        if($venueId !== null){
            $stmt = $pdo->prepare("SELECT id FROM venues WHERE id = ?");
            $stmt->execute([$venueId]);
            if(!$stmt->fetch()){
                Errors::validation(["venue_id" => "Venue not found"]);
                return;
            }
        }

        //Insert
        try {
            $stmt = $pdo->prepare("
                INSERT INTO events (
                    organizer_user_id, venue_id,
                    title, description,
                    event_code, starts_at, ends_at,
                    status
                ) VALUES (
                    :organizer_user_id, :venue_id,
                    :title, :description,
                    :event_code, :starts_at, :ends_at,
                    'DRAFT'
                )
            ");

            $stmt->execute([
                ":organizer_user_id" => $organizerUserId,
                ":venue_id" => $venueId,
                ":title" => $title,
                ":description" => $description,
                ":event_code" => $eventCode,
                ":starts_at" => $startsAt,
                ":ends_at" => $endsAt,
            ]);

            $id = (int)$pdo->lastInsertId();

            Response::json([
                "ok" => true,
                "event" => $this->showRowById($pdo, $id)
            ], 201);
        } catch (PDOException $e) {
            if(str_contains($e->getMessage(), "Duplicate") || $e->getCode() === 23000){
                Errors::validation(["event_code" => "event_code already exists"]);
                return;
            }
            Errors::server("Failed to create event", ["db" => $e->getMessage()]);
        }
    }

    public function index(): void
    {
        $pdo = DB::conn();

        $status = Request::query("status");
        $organizerUserId = Request::query("organizer_user_id");
        $q = Request::query("q");

        $where = [];
        $params = [];

        if($status !== null && $status !== ""){
            $allowed = ["DRAFT", "PUBLISHED", "CANCELLED", "COMPLETED"];
            if (!in_array($status, $allowed, true)){
                Errors::validation(["status" => "Must be one of: " . implode(", ", $allowed)]);
                return;
            }
            $where[] = "e.status = :status";
            $params[":status"] = $status;
        }

        if($organizerUserId !== null && $organizerUserId !== ""){
            if (!preg_match('/^\d+$/', $organizerUserId)){
                Errors::validation(["organizer_user_id" => "Must be an integer"]);
                return;
            }
            $where[] = "e.organizer_user_id = :organizer_user_id";
            $params[":organizer_user_id"] = (int)$organizerUserId;
        }
        if ($q !== null && trim($q) !== ""){
            $where[] = "(e.title LIKE :q OR e.event_code LIKE :q)";
            $params[":q"] = "%" . trim($q) . "%";
        }

        $sql = "
            SELECT
                e.*, 
                v.name AS venue_name,
                v.city AS venue,
                v.country AS country
            FROM events e
            LEFT JOIN venues v ON v.id = e.venue_id
        ";

        if(!empty($where)){
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY e.starts_at ASC, e.id ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();

        Response::json([
            "ok" => true,
            "events" => $rows
        ]);
    }

    /* GET /api/events/:id */
    public function show(array $p): void 
    {
        $pdo = DB::conn();
        $id = $this->parseId($p, "id");
        if ($id === null) return;
        
        $row = $this->showRowById($pdo, $id);
        if($row === null){
            Errors::notFound("Event not found");
            return;
        }
        Response::json(["ok" => true, "event" => $row]);
    }

    /**
     * PATCH /api/events/:id
     * Allow patching: title, description, starts_at, ends_at, venue_id, status (optional)
     * Note: status change should typically be via publish/cancel endpoints; kept flexible here.
     */
    public function update(array $p): void 
    {
        $pdo = DB::conn();
        $id = $this->parseId($p, "id");
        if($id === null) return;

        $existing = $this->showRowById($pdo, $id);
        if ($existing === null){
            Errors::notFound("Event not found");
            return;
        }

        $body = Request::json();
        $errors = [];

        $title = V::str($body["title"] ?? null, "title", $errors, 3, 255);
        $description = V::str($body["description"] ?? null, "description", $errors, 0, 5000);
        $startsAt = V::datetime($body["starts_at"] ?? null, "starts_at", $errors);
        $endsAt = V::datetime($body["ends_at"] ?? null, "ends_at", $errors);
        $venueId = V::int($body["venue_id"] ?? null, "venue_id", $errors, 1);
        $status = V::enum($body["status"] ?? null, "status", $errors, ["DRAFT", "PUBLISHED", "CANCELLED", "COMPLETED"]);

        if (!empty($errors)){
            Errors::validation($errors);
            return;
        }

        if($venueId !== null){
            $stmt = $pdo->prepare("SELECT id FROM venues WHERE id = ?");
            $stmt->execute([$venueId]);
            if(!$stmt->fetch()){
                Errors::validation(["venue_id" => "Venue not found"]);
                return;
            }
        }

        $fields = [];
        $params = [":id" => $id];

        if ($title !== null) { $fields[] = "title = :title"; $params[":title"] = $title; }
        if ($description !== null) { $fields[] = "description = :description"; $params[":description"] = $description; }
        if ($startsAt !== null)    { $fields[] = "starts_at = :starts_at"; $params[":starts_at"] = $startsAt; }
        if ($endsAt !== null)      { $fields[] = "ends_at = :ends_at"; $params[":ends_at"] = $endsAt; }
        if (array_key_exists("venue_id", $body)){
            $fields[] = "venue_id = :venue_id";
            $params[":venue_id"] = $venueId;
        }

        if($status !== null) { $fields[] = "status = :status"; $params[":status"] = $status; }
        if(empty($fields)){
            Response::json(["ok" => true, "event" => $existing, "message" => "No changes"]);
            return;
        }

        $sql = "UPDATE events SET " . implode(", ", $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::json([
            "ok" => true,
            "event" => $this->showRowById($pdo, $id)
        ]);
    }

    /**
     * POST /api/events/:id/publish
     * Sets status to PUBLISHED (only from DRAFT).
     */
    public function publish(array $p): void
    {
        $pdo = DB::conn();
        $id = $this->parseId($p, "id");
        if ($id === null) return;

        $existing = $this->showRowById($pdo, $id);
        if ($existing === null){
            Errors::notFound("Event not found");
            return;
        }

        if (($existing["status"] ?? null) !== "DRAFT"){
            Errors::validation(["status" => "Only DRAFT events can be published"]);
            return;
        }

        $stmt = $pdo->prepare("UPDATE events SET status = 'PUBLISHED' WHERE id = ?");
        $stmt->execute([$id]);

        Response::json([
            "ok" => true,
            "event" => $this->showRowById($pdo, $id)
        ]);
    }

    /* Helpers */
    private function parseId(array $p, string $key): ?int
    {
        $raw = $p[$key] ?? null;
        if($raw === null || !preg_match('/^\d+$/', (string)$raw)){
            Errors::validation([$key => "Invalid id"]);
            return null;
        }
        return (int)$raw;
    }

    private function showRowById(PDO $pdo, int $id): ?array  
    {
        $stmt = $pdo->prepare("
            SELECT
                e.*, 
                v.name AS venue_name,
                v.city AS venue_city,
                v.country AS venue_country
            FROM events e
            LEFT JOIN venues v ON v.id = e.venue_id
            WHERE e.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}