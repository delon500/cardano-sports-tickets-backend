<?php

final class Env 
{
    private static bool $loaded = false;

    public static function load(string $envPath): void
    {
        if(self::$loaded) return;

        if(!file_exists($envPath)){
            self::$loaded = true;
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if($lines === false){
            self::$loaded = true;
            return;
        }

        foreach($lines as $line){
            $line = trim($line);

            if($line === "" || str_starts_with($line, "#")) continue;

            $pos = strpos($line, "=");
            if($pos === false) continue;

            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));

            // Remove surrounding quotes if present
            if( (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'")) && str_ends_with($val, "'")){
                $val = substr($val, 1, -1);
            }

            $_ENV[$key] = $val;
        }

        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string 
    {
        return $_ENV[$key] ?? $default;
    }

    public static function getInt(string $key, ?int $default = 0): int
    {
        $v = self::get($key);
        if($v === null || $v === "") return $default;
        return (int) $v;
    }

    public static function getBool(string $key, bool $default = false): bool 
    {
       $v = strtolower((string)self::get($key, $default ? "1" : "0"));
       return in_array($v, ["1", "true", "yes", "on"], true);
    }

    public static function getList(string $key, array $default = []): array 
    {
        $v = self::get($key);
        if($v === null || $v === "") return $default;
        return array_values(array_filter(array_map("trim", explode(",", $v))));
    }
}