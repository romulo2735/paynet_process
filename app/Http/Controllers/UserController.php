<?php

namespace App\Http\Controllers;

use App\DTOs\UserProcessDTO;
use App\Http\Requests\ProcessRequest;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Paynet Process API",
 *     description="API para processamento, validação e enriquecimento de dados cadastrais",
 *     @OA\Contact(
 *         name="Suporte",
 *         email="suporte@paynet.com"
 *     )
 * )
 */
class UserController extends Controller
{
    public function __construct(private readonly UserService $userService)
    {
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users/process",
     *     summary="Processa e enfileira os dados do usuário",
     *     description="Valida os dados e inicia o processamento assíncrono via fila. Dados são enriquecidos via múltiplas APIs externas.",
     *     operationId="processUser",
     *     tags={"Users"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"cpf", "cep", "email"},
     *             @OA\Property(property="cpf", type="string", example="12345678900"),
     *             @OA\Property(property="cep", type="string", example="06454000"),
     *             @OA\Property(property="email", type="string", example="usuario@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=202,
     *         description="Usuário enviado para processamento",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User in processing"),
     *             @OA\Property(property="status", type="string", example="queue")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erro de validação",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function process(ProcessRequest $processRequest): JsonResponse
    {
        $dto = new UserProcessDTO($processRequest->validated());
        $this->userService->handle($dto);

        return response()->json([
            'message' => 'User in processing',
            'status' => 'queue'
        ], 202);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/users/{cpf}",
     *     summary="Busca dados processados de um usuário",
     *     description="Retorna os dados do usuário a partir do cache (Redis) ou do banco de dados, se necessário.",
     *     operationId="getUserByCpf",
     *     tags={"Users"},
     *     @OA\Parameter(
     *         name="cpf",
     *         in="path",
     *         description="CPF do usuário a ser consultado",
     *         required=true,
     *         @OA\Schema(type="string", example="12345678900")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dados do usuário",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="status", type="string", example="ok")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuário não encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User not found")
     *         )
     *     )
     * )
     */
    public function show(string $cpf): UserResource|JsonResponse
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

        return UserResource::make($user)->additional(['status' => 'ok']);
    }
}
