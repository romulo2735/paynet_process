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
        Log::info("$this->LOG_CONTEXT - Starting call to nationalize", ['email' => $email]);

        $name = explode('@', $email)[0];
        $firstName = preg_replace('/[^a-zA-Z]/', '', explode('.', $name)[0]);

        return retry(3, function () use ($firstName) {
            $response = Http::get("https://api.nationalize.io/?name={$firstName}");

            if ($response->failed()) {
                Log::error("$this->LOG_CONTEXT - error call to nationalize", ['status' => $response->status()]);
                throw new \Exception('Error searching for nationality');
            }

            Log::info("$this->LOG_CONTEXT - completed successfully");

            return $response->json();
        }, 1000);
    }
}
