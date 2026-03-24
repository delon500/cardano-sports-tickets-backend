<?php 

final class Errors 
{
    public static function badRequest(string $message,  array $details = []): void
    {
        Response::json([
            "error" => [
                "code" => "bad_request",
                "message" => $message,
                "details" => $details
            ]
        ], 400);
    }

    public static function unauthorized(string $message = "Unauthorized", array $details = []) : void 
    {
        Response::json([
            "error" => [
                "code" => "unauthorized",
                "message" => $message,
                "details" => $details
            ]
        ], 401);    
    }

    public static function forbidden(string $message = "Forbidden", array $details = []): void
    {
        Response::json([
            "error" =>[
                "code" => "forbidden",
                "message" => $message,
                "details" => $details
            ]
        ],403);
    }

    public static function notFound(string $message = "Not found", array $details = []): void
    {
        Response::json([
            "error" => [
                "code" => "not_found",
                "message" => $message,
                "details" => $details
            ]
        ], 404);
    }

    public static function validation(array $fieldErrors, string $message = "Validation failed") : void 
    {
        Response::json([
            "error" => [
                "code" => "validation_error",
                "message" => $message,
                "fields" => $fieldErrors
            ]
        ], 422);
    }

    public static function server(string $message = "Server error", array $details = []) : void 
    {
        Response::json([
            "error" =>[
                "code" => "server_error",
                "message" => $message,
                "details" => $details
            ]
        ], 500);
    }
}