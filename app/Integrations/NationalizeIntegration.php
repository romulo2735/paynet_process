<?php

namespace App\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NationalizeIntegration
{
    protected string $LOG_CONTEXT = '[NATIONALIZE INTEGRATION]';

    /**
     * @param string $email
     * @return array|null
     * @throws Throwable
     */
    public function checkName(string $email): ?array
    {
        $context = "$this->LOG_CONTEXT - NationalizeIntegration";
        Log::info("$context - Starting call", ['email' => $email]);

        $name = explode('@', $email)[0];
        $firstName = preg_replace('/[^a-zA-Z]/', '', explode('.', $name)[0]);

        try {
            $result = retry(3, function () use ($firstName, $context) {
                Log::info("$context - Attempting HTTP request", ['firstName' => $firstName]);

                $response = Http::get("https://api.nationalize.io/?name={$firstName}");

                if ($response->failed()) {
                    Log::error("$context - HTTP request failed", [
                        'firstName' => $firstName,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    throw new \Exception('Error searching for nationality');
                }

                Log::info("$context - HTTP request succeeded");

                return $response->json();
            }, 1000);

            Log::info("$context - Completed call successfully");

            return $result;
        } catch (\Throwable $e) {
            Log::error("$context - Exception caught after retries", [
                'firstName' => $firstName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

}
