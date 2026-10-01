<?php

namespace App\Services\Retell;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RetellApi
{
    /**
     * Crea una llamada web (API v3). Se llama solo desde el servidor: la API key nunca llega al navegador.
     *
     * @return array{call_id: string, access_token: string, transport: string, ice_servers: array, expires_at: int}
     */
    public function createWebCall(array $payload): array
    {
        $response = Http::withToken((string) config('services.retell.api_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->post(rtrim((string) config('services.retell.base_url'), '/').'/v3/create-web-call', $payload);

        if ($response->failed()) {
            Log::error('Retell: create-web-call falló.', ['status' => $response->status(), 'body' => $response->body()]);

            throw new RuntimeException('Retell rechazó la llamada ('.$response->status().'): '.($response->json('message') ?? 'sin detalle'));
        }

        return $response->json();
    }
}