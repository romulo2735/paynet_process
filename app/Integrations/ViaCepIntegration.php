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
        Log::info("$this->LOG_CONTEXT - Starting call to ViaCep", ['cep' => $cep]);

        return retry(3, function () use ($cep) {
            $response = Http::get("https://viacep.com.br/ws/{$cep}/json/");

            if ($response->failed()) {
                Log::error("$this->LOG_CONTEXT -  call failure", ['cep' => $cep, 'status' => $response->status()]);
                throw new \Exception('Error searching for zip code');
            }

            Log::info("$this->LOG_CONTEXT - ViaCep call completed successfully", ['response' => $response->json()]);

            return $response->json();
        }, 1000);
    }
}
