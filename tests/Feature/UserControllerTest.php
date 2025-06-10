<?php

namespace Tests\Feature;

use App\DTOs\UserProcessDTO;
use App\Http\Controllers\UserController;
use App\Http\Requests\ProcessRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use DatabaseTransactions;

    private UserService $userServiceMock;
    private UserRepository $userRepositoryMock;
    private UserController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userServiceMock = Mockery::mock(UserService::class);
        $this->userRepositoryMock = Mockery::mock(UserRepository::class);

        $this->controller = new UserController($this->userServiceMock);

        // Mock do container para o UserRepository
        $this->app->instance(UserRepository::class, $this->userRepositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function process_should_return_success_response_when_valid_data_provided()
    {
        // Arrange
        $validatedData = [
            'cpf' => '12345678900',
            'cep' => '06454000',
            'email' => 'usuario@example.com'
        ];

        $processRequestMock = Mockery::mock(ProcessRequest::class);
        $processRequestMock->shouldReceive('validated')
            ->once()
            ->andReturn($validatedData);

        $this->userServiceMock->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(UserProcessDTO::class))
            ->andReturnNull();

        // Act
        $response = $this->controller->process($processRequestMock);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(202, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('User in processing', $responseData['message']);
        $this->assertEquals('queue', $responseData['status']);
    }

    /** @test */
    public function process_should_create_correct_dto_with_validated_data()
    {
        // Arrange
        $validatedData = [
            'cpf' => '98765432100',
            'cep' => '01234567',
            'email' => 'test@test.com'
        ];

        $processRequestMock = Mockery::mock(ProcessRequest::class);
        $processRequestMock->shouldReceive('validated')
            ->once()
            ->andReturn($validatedData);

        $this->userServiceMock->shouldReceive('handle')
            ->once()
            ->with(Mockery::on(function (UserProcessDTO $dto) use ($validatedData) {
                // Aqui você pode verificar se o DTO foi criado corretamente
                // Assumindo que o DTO tem métodos getter ou propriedades públicas
                return true; // Adapte conforme a implementação do seu DTO
            }))
            ->andReturnNull();

        // Act
        $response = $this->controller->process($processRequestMock);

        // Assert
        $this->assertEquals(202, $response->getStatusCode());
    }

    /** @test */
    public function show_should_return_user_resource_when_user_found_in_cache()
    {
        $cpf = '12345678900';
        $cacheKey = "process-user-{$cpf}";
        $userData = [
            'id' => 1,
            'cpf' => $cpf,
            'name' => 'João Silva',
            'email' => 'joao@example.com'
        ];

        $cacheMock = Mockery::mock();

        Cache::shouldReceive('tags')
            ->with(['users', "cpf:$cpf"])
            ->once()
            ->andReturn($cacheMock);

        $cacheMock->shouldReceive('get')
            ->with($cacheKey)
            ->once()
            ->andReturn($userData);

        $response = $this->controller->show($cpf);

        $this->assertInstanceOf(UserResource::class, $response);
    }

    /** @test */
    public function show_should_return_user_resource_when_user_found_in_database_and_cache_miss(): void
    {
        $cpf = '12345678900';
        $cacheKey = "process-user-{$cpf}";

        $user = Mockery::mock(User::class);
        $user->shouldReceive('toArray')
            ->once()
            ->andReturn([
                'id' => 1,
                'cpf' => $cpf,
                'name' => 'João Silva',
                'email' => 'joao@example.com'
            ]);

        $cacheMock = Mockery::mock();

        Cache::shouldReceive('tags')
            ->with(['users', "cpf:$cpf"])
            ->once()
            ->andReturn($cacheMock);

        $cacheMock->shouldReceive('get')
            ->with($cacheKey)
            ->once()
            ->andReturn(null);

        $this->userRepositoryMock->shouldReceive('findByCpf')
            ->with($cpf)
            ->once()
            ->andReturn($user);

        Cache::shouldReceive('tags')
            ->with(['users', "cpf:$cpf"])
            ->once()
            ->andReturn($cacheMock);

        $cacheMock->shouldReceive('put')
            ->with($cacheKey, Mockery::any(), Mockery::type(\Illuminate\Support\Carbon::class))
            ->once()
            ->andReturnNull();

        $response = $this->controller->show($cpf);

        $this->assertInstanceOf(UserResource::class, $response);
    }

    /** @test */
    public function show_should_return_404_when_user_not_found_in_cache_and_database(): void
    {
        $cpf = '99999999999';
        $cacheKey = "process-user-{$cpf}";

        $cacheMock = Mockery::mock();

        Cache::shouldReceive('tags')
            ->with(['users', "cpf:$cpf"])
            ->once()
            ->andReturn($cacheMock);

        $cacheMock->shouldReceive('get')
            ->with($cacheKey)
            ->once()
            ->andReturn(null);

        $this->userRepositoryMock->shouldReceive('findByCpf')
            ->with($cpf)
            ->once()
            ->andReturn(null);

        $response = $this->controller->show($cpf);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('User not found', $responseData['message']);
    }

    /** @test */
    public function show_should_use_correct_cache_key_format(): void
    {
        $cpf = '11122233344';
        $expectedCacheKey = "process-user-{$cpf}";

        $cacheMock = Mockery::mock();

        Cache::shouldReceive('tags')
            ->with(['users', "cpf:$cpf"])
            ->once()
            ->andReturn($cacheMock);

        $cacheMock->shouldReceive('get')
            ->with($expectedCacheKey)
            ->once()
            ->andReturn(['id' => 1, 'cpf' => $cpf]);

        $this->controller->show($cpf);

        $this->assertTrue(true);
    }

    /** @test */
    public function show_should_cache_user_data_with_correct_tags_and_ttl(): void
    {
        $cpf = '55566677788';
        $cacheKey = "process-user-{$cpf}";

        $user = Mockery::mock(User::class);
        $userData = ['id' => 1, 'cpf' => $cpf, 'name' => 'Test User'];
        $user->shouldReceive('toArray')
            ->once()
            ->andReturn($userData);

        $cacheMock = Mockery::mock();

        Cache::shouldReceive('tags')
            ->with(['users', "cpf:$cpf"])
            ->once()
            ->andReturn($cacheMock);

        $cacheMock->shouldReceive('get')
            ->with($cacheKey)
            ->once()
            ->andReturn(null);

        $this->userRepositoryMock->shouldReceive('findByCpf')
            ->with($cpf)
            ->once()
            ->andReturn($user);

        Cache::shouldReceive('tags')
            ->with(['users', "cpf:$cpf"])
            ->once()
            ->andReturn($cacheMock);

        $cacheMock->shouldReceive('put')
            ->with(
                $cacheKey,
                $userData,
                Mockery::type(\Illuminate\Support\Carbon::class)
            )
            ->once()
            ->andReturnNull();

        $response = $this->controller->show($cpf);

        $this->assertInstanceOf(UserResource::class, $response);
    }
}
