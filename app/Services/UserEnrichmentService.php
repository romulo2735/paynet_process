<?php

namespace App\Services;

use App\DTOs\UserProcessDTO;
use App\Integrations\CpfIntegration;
use App\Integrations\NationalizeIntegration;
use App\Integrations\ViaCepIntegration;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserEnrichmentService
{
    public function __construct(
        protected ViaCepIntegration      $viaCepClient,
        protected NationalizeIntegration $nationalizeClient,
        protected CpfIntegration         $cpfStatusClient,
        protected UserRepository         $userRepository
    )
    {
    }

    public function process(UserProcessDTO $dto): string
    {
        $cacheKey = "process-user-{$dto->cpf}";

        if (Cache::tags(['users', "cpf:{$cacheKey}"])->has($cacheKey)) {
            Log::info("CPF in cache:");
            return 'cached';
        }

        try {
            $promises = Http::pool(fn($pool) => [
                'cep' => $pool->get($this->viaCepClient->checkAddress($dto->cep)),
                'name' => $pool->get($this->nationalizeClient->checkName($dto->email)),
                'status' => $pool->get($this->cpfStatusClient->checkCpf($dto->cpf)),
            ]);

            if (
                $promises['cep']->failed() ||
                $promises['name']->failed() ||
                $promises['status']->failed()
            ) {
                throw new \Exception('Some external API failed');
            }


            $data = [
                'cpf' => $dto->cpf,
                'email' => $dto->email,
                'cep' => $dto->cep,
                'address' => $promises['cep']->json(),
                'nationality' => $promises['name']->json(),
                'cpf_status' => $promises['status']->json(),
            ];

            $user = $this->userRepository->storeOrUpdate($data);

            Cache::tags(['users', "cpf:{$dto->cpf}"])
                ->put($dto->cpf, $user->toArray(), now()->addDay());

            logger()->info("Processing completed successfully. Status: processed");

            return 'processed';
        } catch (\Throwable $e) {
            Log::error("Error during processing:" . $e->getMessage());
            return 'external_api_error';
        }
    }
}
