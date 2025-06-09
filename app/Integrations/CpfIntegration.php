<?php

namespace App\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CpfIntegration
{
    protected string $LOG_CONTEXT = '[CPF INTEGRATION]';

    /**
     * @param string $cpf
     * @return array|null
     * @throws Throwable
     */
    public function checkCpf(string $cpf): ?array
    {
        Log::info("$this->LOG_CONTEXT - Starting call to cpf", ['$cpf' => $cpf]);

        return retry(3, function () use ($cpf) {
            $response = Http::get("http://localhost:8080/api/mock/cpf/status/{$cpf}");

            if ($response->failed()) {
                Log::error("$this->LOG_CONTEXT - error call to cpf", ['status' => $response->status()]);
                throw new \Exception("Error validating CPF");
            }

            Log::info("$this->LOG_CONTEXT - completed successfully");

            return $response->json();
        }, 1000);
    }
}
