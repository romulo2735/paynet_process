<?php

namespace App\Services;

use App\DTOs\UserProcessDTO;
use App\Jobs\ProcessUserJob;
use Illuminate\Support\Facades\Redis;

class UserService
{

    public function handle(UserProcessDTO $dto): void
    {
        $cacheKey = "process-user-{$dto->cpf}";
        if (!Redis::exists($cacheKey)) {
            Redis::setex($cacheKey, 300, json_encode($dto));

            ProcessUserJob::dispatch($dto);
        }
    }
}
