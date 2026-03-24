<?php

final class Request
{
    public static function json(): array
    {
        $raw = file_get_contents("php://input");
        if($raw === false || trim($raw) === "") return [];

        $data = json_decode($raw, true);

        if(json_last_error() !== JSON_ERROR_NONE){
            Errors::badRequest("Invalid JSON body", [
                "json_error" => json_last_error_msg()
            ]);
            exit;
        }

        if (!is_array($data)) return [];
        return $data;
    }

    /** Get query param from $_GET safely. */
    public static function query(string $key, ?string $default = null) : ?string 
    {
        $v = $_GET[$key] ?? $default;
        if ($v === null) return null;
        if (is_array($v)) return $default;
        return (string) $v;
    }

    public static function queryAll() : array 
    {
        return $_GET ?? [];  
    }

    public static function file(string $key) : ?array 
    {
        if (!isset($_FILES[$key])) return null;
        
        $f = $_FILES[$key];

        if (!is_array($f) || !isset($f["error"])) return null;
        if(is_array($f["error"])){
            Errors::badRequest("Multiple file upload not supported for this field", ["field" => $key]);
            exit;
        }
        return $f;
    }
}