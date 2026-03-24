<?php 

final class V
{
    public static function required(array $data, string $key, array &$errors) : mixed 
    {
        if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === "") {
            $errors[$key] = "Required";
            return null;
        }
        return $data[$key];
    }

    public static function str(mixed $value, string $key, array &$errors, int $min = 0, int $max = 10000): ?string
    {
        if ($value === null) return null;

        if (!is_string($value)){
            $errors[$key] = "Must be a string";
            return null;
        }

        $s = trim($value);
        $len = mb_strlen($s);
        if ($len < $min) $errors[$key] = "Must be at least {$min} characters";
        if ($len > $max) $errors[$key] = "Must be at most {$max} characters";
        return $s;
    }

    public static function int(mixed $value, string $key, array &$errors, ?int $min = null, ?int $max = null) : ?int 
    {
        if ($value === null) return null;

        if (is_int($value)) $i = $value;
        else if (is_string($value) && preg_match('/^-?\d+$/', $value)) $i = (int)$value;
        else {
            $errors[$key] = "Must be an integer";
            return null;
        }

        if ($min !== null && $i < $min) $errors[$key] = "Must be >= {$min}";
        if ($max !== null && $i > $max) $errors[$key] = "Must be <= {$max}";

        return $i;
    }

    public static function enum(mixed $value, string $key, array &$errors, array $allowed) : ?string 
    {
        if ($value === null) return null;
        if(!is_string($value)){
            $errors[$key] = "Must be a string";
            return null;
        }

        if(!in_array($value, $allowed, true)){
            $errors[$key] = "Must be one of: " . implode(", ", $allowed);
            return null;
        }

        return $value;
    }

    public static function datetime(mixed $value, string $key, array &$errors) : ?string 
    {
        if ($value === null) return null;
        if(!is_string($value)){
            $errors[$key] = "Must be a datetime string";
            return null;
        }
        
        $s = trim($value);

        $dt = date_create($s);
        if($dt === false){
            $errors[$key] = "Invalid datetime";
            return null;
        }

        return $dt->format("Y-m-d H:i:s");
    }
}