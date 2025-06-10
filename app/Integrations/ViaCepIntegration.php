<?php

namespace App\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ViaCepIntegration
{
    protected string $LOG_CONTEXT = '[VIA CEP INTEGRATION]';

    /**
     * @param string $cep
     * @return array|null
     * @throws Throwable
     */
    public function checkAddress(string $cep): ?array
    {
        $context = "$this->LOG_CONTEXT - ViaCepIntegration";

        Log::info("$context - Starting call", ['cep' => $cep]);

        try {
            $result = retry(3, function () use ($cep, $context) {
                Log::info("$context - Attempting HTTP request", ['cep' => $cep]);

                $response = Http::get("https://viacep.com.br/ws/{$cep}/json/");

                if ($response->failed()) {
                    Log::error("$context - HTTP request failed", [
                        'cep' => $cep,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    throw new \Exception('Error searching for zip code');
                }

                Log::info("$context - HTTP request succeeded", ['response' => $response->json()]);

                return $response->json();
            }, 1000);

            Log::info("$context - Completed call successfully", ['cep' => $cep]);

            return $result;
        } catch (\Throwable $e) {
            Log::error("$context - Exception caught after retries", [
                'cep' => $cep,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

}
