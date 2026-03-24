<?php

require_once __DIR__ . "/../Db/db.php";
require_once __DIR__ . "/../Http/Request.php";
require_once __DIR__ . "/../Http/Response.php";
require_once __DIR__ . "/../Http/Errors.php";
require_once __DIR__ . "/../Config/env.php";
require_once __DIR__ . "/../Validation/validators.php";

final class EventMediaController {
    // POST /api/events/:id/media (multipart/form-data)
    // Fields:
    //  - file (required)
    //  - kind (optional enum: POSTER,BANNER,GALLERY,SPONSOR_LOGO,SEATMAP_IMAGE) default POSTER
    public function upload(array $p) : void 
    {
        $eventId = $this->parseId($p, "id");
        if ($eventId === null) return;

        $pdo = DB::conn();

        $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
        if(!$stmt->fetch()){
            Errors::notFound("Event not found");
            return;        
        }

        // Load env 
        Env::load(dirname(__DIR__, 2) . "/.env");

        $allowedKinds = ["POSTER", "BANNER", "GALLERY", "SPONSOR_LOGO", "SEATMAP_IMAGE"];
        $kind = $_POST["kind"] ?? "POSTER";
        if (!in_array($kind, $allowedKinds, true)){
            Errors::validation(["kind" => "Must be one of: " . implode(", ", $allowedKinds)]);
            return;
        }

        $file = Request::file("file");
        if ($file === null){
            Errors::validation(["file" => "Required"]);
            return;
        }

        if (($file["error" ?? UPLOAD_ERR_NO_FILE]) !== UPLOAD_ERR_OK){
            Errors::badRequest("upload failed", ["upload_error" => $file["error"]]);
            return;            
        }

        $maxBytes = Env::getInt("UPLOAD_MAX_BYTES", 5_242_880);
        if(($file["size"] ?? 0) > $maxBytes){
            Errors::validation(["file" => "File too large (max {$maxBytes} bytes)"]);
            return; 
        }

        $tmpPath = $file["tmp_name"] ?? "";
        if($tmpPath === "" || !is_uploaded_file($tmpPath)){
            Errors::badRequest("Invalid upload temp file");
            return;
        }

        // Detect mime type server-side 
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath) ?: "application/octet-stream";

        $allowedMimes = Env::getList("UPLOAD_ALLOWED_MIMES", ["image/jpeg", "image/png", "image/webp"]);
        if(!in_array($mime, $allowedMimes, true)){
            Errors::validation(["file" => "Unsupported mime type: {$mime}"]);
            return;
        }

        $ext = match ($mime) {
            "image/jpeg" => "jpg",
            "image/png"  => "png",
            "image/webp" => "webp",
            default => "bin",
        };

        $baseDir = Env::get("UPLOAD_DIR");
        $publicBase = Env::get("UPLOAD_PUBLIC_BASE", "/uploads");

        if ($baseDir === null || trim($baseDir) === "") {
            Errors::server("UPLOAD_DIR not configured");
            return;
        }

        // Target folder: {UPLOAD_DIR}/events/{eventId}/
        $targetDir = rtrim($baseDir, "\\/") . DIRECTORY_SEPARATOR . "events" . DIRECTORY_SEPARATOR . $eventId;
        if (!is_dir($targetDir)){
            if (!mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                Errors::server("Failed to create upload directory", ["dir" => $targetDir]);
                return; 
            }
        }

        // Safe filename
        $uniq = bin2hex(random_bytes(8));
        $filename = strtolower($kind) . "-" . $uniq . "." . $ext;

        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpPath, $targetPath)){
            Errors::server("Failed to move uploaded file");
            return;
        }

        //  Build URL (relative to server)
        $url = rtrim($publicBase, "/") . "/events/{$eventId}/{$filename}";

        // Extract dimensions
        $width = null;
        $height = null;
        $imgInfo = @getimagesize($targetPath);
        if (is_array($imgInfo)) {
            $width = $imgInfo[0] ?? null;
            $height = $imgInfo[1] ?? null;
        }

        $sizeBytes = filesize($targetPath) ?: ($file["size"] ?? null);

        //Insert DB row
        $stmt = $pdo->prepare("
            INSERT INTO event_media (event_id, kind, url, mime, width, height, size_bytes)
            VALUES (:event_id, :kind, :url, :mime, :width, :height, :size_bytes)
        ");
        $stmt->execute([
            ":event_id" => $eventId,
            ":kind" => $kind,
            ":url" => $url,
            ":mime" => $mime,
            ":width" => $width,
            ":height" => $height,
            ":size_bytes" => $sizeBytes,
        ]);

        $id = (int)$pdo->lastInsertId();

        Response::json([
            "ok" => true,
            "media" => [
                "id" => $id,
                "event_id" => $eventId,
                "kind" => $kind,
                "url" => $url,
                "mime" => $mime,
                "width" => $width,
                "height" => $height,
                "size_bytes" => $sizeBytes,
            ]
        ], 201);
    }

    // GET /api/events/:id/media
    public function index(array $p): void
    {
        $eventId = $this->parseId($p, "id");
        if ($eventId === null) return;

        $pdo = DB::conn();

        $stmt = $pdo->prepare("
            SELECT id, event_id, kind, url, mime, width, height, size_bytes, created_at
            FROM event_media
            WHERE event_id = ?
            ORDER BY created_at DESC, id DESC 
        ");
        $stmt->execute([$eventId]);

        Response::json(["ok" => true, "media" => $stmt->fetchAll()]);
    }

    // DELETE /api/events/:id/media/:mediaId
    public function delete(array $p) : void 
    {
        $eventId = $this->parseId($p, "id");
        if ($eventId === null) return;

        $mediaId = $this->parseId($p, "mediaId");
        if ($mediaId === null) return;

        $pdo = DB::conn();

        //Load env
        Env::load(dirname(__DIR__, 2) . "/.env");
        $baseDir = Env::get("UPLOAD_DIR");
        if ($baseDir === null || trim($baseDir) === "") {
            Errors::server("UPLOAD_DIR not configured");
            return;
        }

        // Find media row
        $stmt = $pdo->prepare("
            SELECT id, event_id, url 
            FROM event_media 
            WHERE id = ? AND event_id = ?
            LIMIT 1
        ");
        $stmt->execute([$mediaId, $eventId]);
        $row = $stmt->fetch();
        if (!$row) {
            Errors::notFound("Media not found");
            return;
        }

        // Compute file path from URL
        $publicBase = Env::get("UPLOAD_PUBLIC_BASE", "/uploads");
        $url = (string)$row["url"];

        $relative = $url;
        if (str_starts_with($relative, $publicBase)){
            $relative = substr($relative, strlen($publicBase));
        }
        $relative = ltrim(str_replace("/", DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
        $filePath = rtrim($baseDir, "\\/") . DIRECTORY_SEPARATOR . $relative;

        // DeleteDB row first (or after) - do after successful unlink, but tolerate missing file
        $fileDeleted = true;
        if (file_exists($filePath)){
            $fileDeleted = @unlink($filePath);
        }

        if (!$fileDeleted) {
            Errors::server("Failed to delete file", ["path" => $filePath]);
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM event_media WHERE id = ? AND event_id = ?");
        $stmt->execute([$mediaId, $eventId]);

        Response::json(["ok" => true]);
    }
    private function parseId(array $p, string $key): ?int {
        $raw = $p[$key] ?? null;
        if ($raw === null || !preg_match('/^\d+$/', (string) $raw)) {
            Errors::validation([$key => "Invalid id"]);
            return null;
        }
        return (int) $raw;
    }
}