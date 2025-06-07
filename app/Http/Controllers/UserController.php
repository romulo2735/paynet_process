<?php

namespace App\Http\Controllers;

use App\DTOs\UserProcessDTO;
use App\Http\Requests\ProcessRequest;
use App\Repositories\UserRepository;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function process(ProcessRequest $processRequest): JsonResponse
    {
        $dto = new UserProcessDTO($processRequest->validated());
        $this->userService->handle($dto);

        return response()->json([
            'message' => 'User in processing',
            'status' => 'queue'
        ], 202);
    }

    public function show(string $cpf): JsonResponse
    {
        $cacheKey = "process-user-{$cpf}";

        $user = Cache::tags(['users', "cpf:$cpf"])->get($cacheKey);

        if (!$user) {
            $user = app(UserRepository::class)->findByCpf($cpf);

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            Cache::tags(['users', "cpf:$cpf"])->put($cacheKey, $user->toArray(), now()->addDay());
        }

        return response()->json([
            'status' => 'ok',
            'data' => $user
        ]);
    }
}
