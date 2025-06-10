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
        $context = "$this->LOG_CONTEXT - CpfStatusIntegration";
        Log::info("$context - Starting call", ['cpf' => $cpf]);

        try {
            $result = retry(3, function () use ($cpf, $context) {
                Log::info("$context - Attempting HTTP request", ['cpf' => $cpf]);

                $response = Http::get("http://localhost:8080/api/mock/cpf/status/{$cpf}");

                if ($response->failed()) {
                    Log::error("$context - HTTP request failed", [
                        'cpf' => $cpf,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    throw new \Exception("Error validating CPF");
                }

                Log::info("$context - HTTP request succeeded");

                return $response->json();
            }, 1000);

            Log::info("$context - Completed call successfully");

            return $result;
        } catch (\Throwable $e) {
            Log::error("$context - Exception caught after retries", [
                'cpf' => $cpf,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

}
