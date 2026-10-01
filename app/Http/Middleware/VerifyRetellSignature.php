<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyRetellSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = (string) config('services.retell.api_key');
        $signature = (string) $request->header('X-Retell-Signature', '');

        if ($apiKey === '' || ! self::isValid($request->getContent(), $apiKey, $signature)) {
            Log::warning('Retell: firma inválida.', ['path' => $request->path()]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }

    public static function isValid(string $rawBody, string $apiKey, string $signature): bool
    {
        if (! preg_match('/v=(\d+),d=(.*)/', $signature, $matches)) {
            return false;
        }

        [, $timestamp, $digest] = $matches;

        $now = (int) round(microtime(true) * 1000);

        if (abs($now - (int) $timestamp) > 5 * 60 * 1000) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody.$timestamp, $apiKey), $digest);
    }
}