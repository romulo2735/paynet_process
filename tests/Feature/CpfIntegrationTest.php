<?php

namespace Tests\Feature;

use App\Integrations\CpfIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CpfIntegrationTest extends TestCase
{
    private CpfIntegration $cpfIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cpfIntegration = new CpfIntegration();
    }

    /** @test */
    public function check_cpf_should_return_data_on_successful_request(): void
    {
        // Arrange
        $cpf = '12345678900';
        $expectedData = [
            'cpf' => $cpf,
            'status' => 'valid',
            'name' => 'João Silva'
        ];

        Http::fake([
            "http://localhost:8080/api/mock/cpf/status/{$cpf}" => Http::response($expectedData, 200)
        ]);

        Log::shouldReceive('info')
            ->with('[CPF INTEGRATION] - CpfStatusIntegration - Starting call', ['cpf' => $cpf])
            ->once();

        Log::shouldReceive('info')
            ->with('[CPF INTEGRATION] - CpfStatusIntegration - Attempting HTTP request', ['cpf' => $cpf])
            ->once();

        Log::shouldReceive('info')
            ->with('[CPF INTEGRATION] - CpfStatusIntegration - HTTP request succeeded')
            ->once();

        Log::shouldReceive('info')
            ->with('[CPF INTEGRATION] - CpfStatusIntegration - Completed call successfully')
            ->once();

        $result = $this->cpfIntegration->checkCpf($cpf);

        $this->assertEquals($expectedData, $result);
        Http::assertSent(function ($request) use ($cpf) {
            return $request->url() === "http://localhost:8080/api/mock/cpf/status/{$cpf}";
        });
    }

    /** @test */
    public function check_cpf_should_return_null_when_http_request_fails(): void
    {
        $cpf = '98765432100';

        Http::fake([
            "http://localhost:8080/api/mock/cpf/status/{$cpf}" => Http::response(['error' => 'Invalid CPF'], 400)
        ]);

        Log::shouldReceive('info')
            ->with('[CPF INTEGRATION] - CpfStatusIntegration - Starting call', ['cpf' => $cpf])
            ->once();

        Log::shouldReceive('info')
            ->with('[CPF INTEGRATION] - CpfStatusIntegration - Attempting HTTP request', ['cpf' => $cpf])
            ->times(3); // retry 3 times

        Log::shouldReceive('error')
            ->with('[CPF INTEGRATION] - CpfStatusIntegration - HTTP request failed', [
                'cpf' => $cpf,
                'status' => 400,
                'body' => '{"error":"Invalid CPF"}'
            ])
            ->times(3); // retry 3 times

        Log::shouldReceive('error')
            ->with('[CPF INTEGRATION] - CpfStatusIntegration - Exception caught after retries', [
                'cpf' => $cpf,
                'error' => 'Error validating CPF'
            ])
            ->once();

        $result = $this->cpfIntegration->checkCpf($cpf);

        $this->assertNull($result);
        Http::assertSentCount(3); // Should retry 3 times
    }

    /** @test */
//    public function check_cpf_should_return_null_when_connection_exception_occurs(): void
//    {
//        $cpf = '11111111111';
//
//        Http::fake([
//            "http://localhost:8080/api/mock/cpf/status/{$cpf}" => function () {
//                throw new ConnectionException('Connection timeout');
//            }
//        ]);
//
//        Log::shouldReceive('info')
//            ->with('[CPF INTEGRATION] - CpfStatusIntegration - Starting call', ['cpf' => $cpf])
//            ->once();
//
//        Log::shouldReceive('info')
//            ->with('[CPF INTEGRATION] - CpfStatusIntegration - Attempting HTTP request', ['cpf' => $cpf])
//            ->times(3);
//
//        Log::shouldReceive('error')
//            ->with('[CPF INTEGRATION] - CpfStatusIntegration - Exception caught after retries', [
//                'cpf' => $cpf,
//                'error' => 'Connection timeout'
//            ])
//            ->once();
//
//        $result = $this->cpfIntegration->checkCpf($cpf);
//
//        $this->assertNull($result);
//        Http::assertSentCount(3);
//    }

    /** @test */
    public function check_cpf_should_succeed_after_initial_failures(): void
    {
        $cpf = '55555555555';
        $expectedData = [
            'cpf' => $cpf,
            'status' => 'valid',
            'name' => 'Maria Santos'
        ];

        // Simulate: 2 failures, then success
        Http::fake([
            "http://localhost:8080/api/mock/cpf/status/{$cpf}" => Http::sequence()
                ->push(['error' => 'Server error'], 500)
                ->push(['error' => 'Server error'], 500)
                ->push($expectedData, 200)
        ]);

        Log::shouldReceive('info')
            ->with('[CPF INTEGRATION] - CpfStatusIntegration - Starting call', ['cpf' => $cpf])
            ->once();

        Log::shouldReceive('info')
            ->with('[CPF INTEGRATION] - CpfStatusIntegration - Attempting HTTP request', ['cpf' => $cpf])
            ->times(3);

        Log::shouldReceive('error')
            ->with('[CPF INTEGRATION] - CpfStatusIntegration - HTTP request failed', [
                'cpf' => $cpf,
                'status' => 500,
                'body' => '{"error":"Server error"}'
            ])
            ->times(2);

        Log::shouldReceive('info')
            ->with('[CPF INTEGRATION] - CpfStatusIntegration - HTTP request succeeded')
            ->once();

        Log::shouldReceive('info')
            ->with('[CPF INTEGRATION] - CpfStatusIntegration - Completed call successfully')
            ->once();

        $result = $this->cpfIntegration->checkCpf($cpf);

        $this->assertEquals($expectedData, $result);
        Http::assertSentCount(3);
    }

    /** @test */
    public function check_cpf_should_use_correct_url_format(): void
    {
        $cpf = '99988877766';
        $expectedUrl = "http://localhost:8080/api/mock/cpf/status/{$cpf}";

        Http::fake([
            $expectedUrl => Http::response(['cpf' => $cpf, 'status' => 'valid'], 200)
        ]);

        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->cpfIntegration->checkCpf($cpf);

        Http::assertSent(function ($request) use ($expectedUrl) {
            return $request->url() === $expectedUrl && $request->method() === 'GET';
        });
    }

    /** @test */
    public function check_cpf_should_log_all_steps_correctly_on_success(): void
    {
        $cpf = '12312312312';
        $context = '[CPF INTEGRATION] - CpfStatusIntegration';

        Http::fake([
            "http://localhost:8080/api/mock/cpf/status/{$cpf}" => Http::response(['status' => 'valid'], 200)
        ]);

        Log::shouldReceive('info')
            ->with("$context - Starting call", ['cpf' => $cpf])
            ->once()
            ->ordered();

        Log::shouldReceive('info')
            ->with("$context - Attempting HTTP request", ['cpf' => $cpf])
            ->once()
            ->ordered();

        Log::shouldReceive('info')
            ->with("$context - HTTP request succeeded")
            ->once()
            ->ordered();

        Log::shouldReceive('info')
            ->with("$context - Completed call successfully")
            ->once()
            ->ordered();

        $this->cpfIntegration->checkCpf($cpf);
    }

    /** @test */
    public function check_cpf_should_handle_empty_response_body(): void
    {
        $cpf = '00000000000';

        Http::fake([
            "http://localhost:8080/api/mock/cpf/status/{$cpf}" => Http::response('', 200)
        ]);

        Log::shouldReceive('info')->zeroOrMoreTimes();

        $result = $this->cpfIntegration->checkCpf($cpf);

        $this->assertEmpty($result);
        Http::assertSentCount(1);
    }

    /** @test */
    public function check_cpf_should_handle_malformed_json_response(): void
    {
        $cpf = '77777777777';

        Http::fake([
            "http://localhost:8080/api/mock/cpf/status/{$cpf}" => Http::response('invalid json{', 200)
        ]);

        Log::shouldReceive('info')->zeroOrMoreTimes();

        $result = $this->cpfIntegration->checkCpf($cpf);

        $this->assertNull($result);
    }

    /** @test */
//    public function check_cpf_should_pass_cpf_parameter_correctly(): void
//    {
//        $testCases = ['12345678900', '98765432100', '11111111111'];
//
//        foreach ($testCases as $cpf) {
//            Http::fake([
//                "http://localhost:8080/api/mock/cpf/status/{$cpf}" => Http::response(['cpf' => $cpf], 200)
//            ]);
//
//            Log::shouldReceive('info')->zeroOrMoreTimes();
//
//            $result = $this->cpfIntegration->checkCpf($cpf);
//
//            $this->assertEquals(['cpf' => $cpf], $result);
//            Http::assertSent(function ($request) use ($cpf) {
//                return str_contains($request->url(), $cpf);
//            });
//
//            Http::fake();
//        }
//    }
}
