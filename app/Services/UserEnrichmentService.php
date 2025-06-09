<?php

namespace App\Services;

use App\DTOs\UserProcessDTO;
use App\Integrations\CpfIntegration;
use App\Integrations\NationalizeIntegration;
use App\Integrations\ViaCepIntegration;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Cache;
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
        Log::info("[USER_ERNICHMENT_SERVICE] - Starting user enrichment", ['$dto' => $dto]);

        $cacheKey = "process-user-{$dto->cpf}";

        if (Cache::tags(['users', "cpf:{$cacheKey}"])->has($cacheKey)) {
            Log::info("CPF in cache:");
            return 'cached';
        }

        try {
            $addressIntegration = $this->viaCepClient->checkAddress($dto->cep);
            $nationalityIntegration = $this->nationalizeClient->checkName($dto->email);
            $cpfStatusIntegration = $this->cpfStatusClient->checkCpf($dto->cpf);

            if (!$addressIntegration || !$nationalityIntegration) {
                throw new \Exception('Some external API failed');
            }

            $data = [
                'cpf' => $dto->cpf,
                'email' => $dto->email,
                'cep' => $dto->cep,
                'address' => $addressIntegration,
                'nationality' => $nationalityIntegration,
                'cpf_status' => "aproved",
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
