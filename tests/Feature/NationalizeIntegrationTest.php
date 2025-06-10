<?php

namespace Tests\Feature;

use App\Integrations\NationalizeIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class NationalizeIntegrationTest extends TestCase
{
    private NationalizeIntegration $nationalizeIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nationalizeIntegration = new NationalizeIntegration();
    }

    /** @test */
    public function check_name_should_return_data_on_successful_request(): void
    {
        $email = 'joao.silva@example.com';
        $expectedFirstName = 'joao';
        $expectedData = [
            'name' => 'joao',
            'country' => [
                [
                    'country_id' => 'BR',
                    'probability' => 0.8
                ],
                [
                    'country_id' => 'PT',
                    'probability' => 0.2
                ]
            ]
        ];

        Http::fake([
            "https://api.nationalize.io/?name={$expectedFirstName}" => Http::response($expectedData, 200)
        ]);

        Log::shouldReceive('info')
            ->with('[NATIONALIZE INTEGRATION] - NationalizeIntegration - Starting call', ['email' => $email])
            ->once();

        Log::shouldReceive('info')
            ->with('[NATIONALIZE INTEGRATION] - NationalizeIntegration - Attempting HTTP request', ['firstName' => $expectedFirstName])
            ->once();

        Log::shouldReceive('info')
            ->with('[NATIONALIZE INTEGRATION] - NationalizeIntegration - HTTP request succeeded')
            ->once();

        Log::shouldReceive('info')
            ->with('[NATIONALIZE INTEGRATION] - NationalizeIntegration - Completed call successfully')
            ->once();

        $result = $this->nationalizeIntegration->checkName($email);

        $this->assertEquals($expectedData, $result);
        Http::assertSent(function ($request) use ($expectedFirstName) {
            return $request->url() === "https://api.nationalize.io/?name={$expectedFirstName}";
        });
    }

    /** @test */
    public function check_name_should_extract_first_name_correctly_from_simple_email(): void
    {
        $email = 'maria@gmail.com';
        $expectedFirstName = 'maria';

        Http::fake([
            "https://api.nationalize.io/?name={$expectedFirstName}" => Http::response(['name' => 'maria'], 200)
        ]);

        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->nationalizeIntegration->checkName($email);

        Http::assertSent(function ($request) use ($expectedFirstName) {
            return str_contains($request->url(), "name={$expectedFirstName}");
        });
    }

    /** @test */
    public function check_name_should_extract_first_name_from_email_with_dots(): void
    {
        $email = 'joao.carlos.silva@company.com';
        $expectedFirstName = 'joao';

        Http::fake([
            "https://api.nationalize.io/?name={$expectedFirstName}" => Http::response(['name' => 'joao'], 200)
        ]);

        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->nationalizeIntegration->checkName($email);

        Http::assertSent(function ($request) use ($expectedFirstName) {
            return str_contains($request->url(), "name={$expectedFirstName}");
        });
    }

    /** @test */
    public function check_name_should_clean_special_characters_from_name(): void
    {
        $email = 'jo@o123.silva456@example.com';
        $expectedFirstName = 'joo'; // Should remove @ and numbers

        Http::fake([
            "https://api.nationalize.io/?name={$expectedFirstName}" => Http::response(['name' => 'joo'], 200)
        ]);

        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->nationalizeIntegration->checkName($email);

        Http::assertSent(function ($request) use ($expectedFirstName) {
            return str_contains($request->url(), "name={$expectedFirstName}");
        });
    }

    /** @test */
    public function check_name_should_return_null_when_http_request_fails(): void
    {
        $email = 'test@example.com';
        $firstName = 'test';

        Http::fake([
            "https://api.nationalize.io/?name={$firstName}" => Http::response(['error' => 'API Error'], 400)
        ]);

        Log::shouldReceive('info')
            ->with('[NATIONALIZE INTEGRATION] - NationalizeIntegration - Starting call', ['email' => $email])
            ->once();

        Log::shouldReceive('info')
            ->with('[NATIONALIZE INTEGRATION] - NationalizeIntegration - Attempting HTTP request', ['firstName' => $firstName])
            ->times(3); // retry 3 times

        Log::shouldReceive('error')
            ->with('[NATIONALIZE INTEGRATION] - NationalizeIntegration - HTTP request failed', [
                'firstName' => $firstName,
                'status' => 400,
                'body' => '{"error":"API Error"}'
            ])
            ->times(3); // retry 3 times

        Log::shouldReceive('error')
            ->with('[NATIONALIZE INTEGRATION] - NationalizeIntegration - Exception caught after retries', [
                'firstName' => $firstName,
                'error' => 'Error searching for nationality'
            ])
            ->once();

        $result = $this->nationalizeIntegration->checkName($email);

        $this->assertNull($result);
        Http::assertSentCount(3); // Should retry 3 times
    }

    /** @test */
    public function check_name_should_return_null_when_connection_exception_occurs(): void
    {
        $email = 'connection@test.com';
        $firstName = 'connection';

        Http::fake([
            "https://api.nationalize.io/?name={$firstName}" => function () {
                throw new ConnectionException('Connection timeout');
            }
        ]);

        Log::shouldReceive('info')
            ->with('[NATIONALIZE INTEGRATION] - NationalizeIntegration - Starting call', ['email' => $email])
            ->once();

        Log::shouldReceive('info')
            ->with('[NATIONALIZE INTEGRATION] - NationalizeIntegration - Attempting HTTP request', ['firstName' => $firstName])
            ->times(3);

        Log::shouldReceive('error')
            ->with('[NATIONALIZE INTEGRATION] - NationalizeIntegration - Exception caught after retries', [
                'firstName' => $firstName,
                'error' => 'Connection timeout'
            ])
            ->once();

        $result = $this->nationalizeIntegration->checkName($email);

        $this->assertNull($result);
        Http::assertSentCount(3);
    }

    /** @test */
    public function check_name_should_succeed_after_initial_failures(): void
    {
        $email = 'retry@example.com';
        $firstName = 'retry';
        $expectedData = [
            'name' => 'retry',
            'country' => [
                ['country_id' => 'US', 'probability' => 0.9]
            ]
        ];

        Http::fake([
            "https://api.nationalize.io/?name={$firstName}" => Http::sequence()
                ->push(['error' => 'Server error'], 500)
                ->push(['error' => 'Server error'], 500)
                ->push($expectedData, 200)
        ]);

        Log::shouldReceive('info')
            ->with('[NATIONALIZE INTEGRATION] - NationalizeIntegration - Starting call', ['email' => $email])
            ->once();

        Log::shouldReceive('info')
            ->with('[NATIONALIZE INTEGRATION] - NationalizeIntegration - Attempting HTTP request', ['firstName' => $firstName])
            ->times(3);

        Log::shouldReceive('error')
            ->with('[NATIONALIZE INTEGRATION] - NationalizeIntegration - HTTP request failed', [
                'firstName' => $firstName,
                'status' => 500,
                'body' => '{"error":"Server error"}'
            ])
            ->times(2);

        Log::shouldReceive('info')
            ->with('[NATIONALIZE INTEGRATION] - NationalizeIntegration - HTTP request succeeded')
            ->once();

        Log::shouldReceive('info')
            ->with('[NATIONALIZE INTEGRATION] - NationalizeIntegration - Completed call successfully')
            ->once();

        $result = $this->nationalizeIntegration->checkName($email);

        $this->assertEquals($expectedData, $result);
        Http::assertSentCount(3);
    }

    /** @test */
    public function check_name_should_use_correct_api_endpoint(): void
    {
        $email = 'endpoint@test.com';
        $firstName = 'endpoint';
        $expectedUrl = "https://api.nationalize.io/?name={$firstName}";

        Http::fake([
            $expectedUrl => Http::response(['name' => $firstName], 200)
        ]);

        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->nationalizeIntegration->checkName($email);

        Http::assertSent(function ($request) use ($expectedUrl) {
            return $request->url() === $expectedUrl && $request->method() === 'GET';
        });
    }

    /** @test */
    public function check_name_should_log_all_steps_correctly_on_success(): void
    {
        $email = 'logging@test.com';
        $firstName = 'logging';
        $context = '[NATIONALIZE INTEGRATION] - NationalizeIntegration';

        Http::fake([
            "https://api.nationalize.io/?name={$firstName}" => Http::response(['name' => $firstName], 200)
        ]);

        Log::shouldReceive('info')
            ->with("$context - Starting call", ['email' => $email])
            ->once()
            ->ordered();

        Log::shouldReceive('info')
            ->with("$context - Attempting HTTP request", ['firstName' => $firstName])
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

        $this->nationalizeIntegration->checkName($email);
    }

    /** @test */
    public function check_name_should_handle_empty_response_body(): void
    {
        $email = 'empty@response.com';
        $firstName = 'empty';

        Http::fake([
            "https://api.nationalize.io/?name={$firstName}" => Http::response('', 200)
        ]);

        Log::shouldReceive('info')->zeroOrMoreTimes();

        $result = $this->nationalizeIntegration->checkName($email);

        $this->assertEmpty($result);
        Http::assertSentCount(1);
    }

    /** @test */
    public function check_name_should_handle_email_edge_cases(): void
    {
        $testCases = [
            'single@domain.com' => 'single',
            'a@domain.com' => 'a',
            'name-with-dash@domain.com' => 'namewithdash',
            'name_with_underscore@domain.com' => 'namewithunderscore',
            'name123@domain.com' => 'name',
            'UPPERCASE@domain.com' => 'UPPERCASE',
        ];

        foreach ($testCases as $email => $expectedFirstName) {
            Http::fake([
                "https://api.nationalize.io/?name={$expectedFirstName}" => Http::response(['name' => $expectedFirstName], 200)
            ]);

            Log::shouldReceive('info')->zeroOrMoreTimes();

            $result = $this->nationalizeIntegration->checkName($email);

            Http::assertSent(function ($request) use ($expectedFirstName) {
                return str_contains($request->url(), "name={$expectedFirstName}");
            });

            Http::fake();
        }
    }

    /** @test */
    public function check_name_should_handle_malformed_json_response(): void
    {
        $email = 'malformed@json.com';
        $firstName = 'malformed';

        Http::fake([
            "https://api.nationalize.io/?name={$firstName}" => Http::response('invalid json{', 200)
        ]);

        Log::shouldReceive('info')->zeroOrMoreTimes();

        $result = $this->nationalizeIntegration->checkName($email);

        $this->assertNull($result);
    }

    /** @test */
    public function check_name_should_handle_very_long_email_names(): void
    {
        $longName = str_repeat('a', 100);
        $email = "{$longName}@domain.com";

        Http::fake([
            "https://api.nationalize.io/?name={$longName}" => Http::response(['name' => $longName], 200)
        ]);

        Log::shouldReceive('info')->zeroOrMoreTimes();

        $result = $this->nationalizeIntegration->checkName($email);

        Http::assertSent(function ($request) use ($longName) {
            return str_contains($request->url(), "name={$longName}");
        });
        $this->assertNotNull($result);
    }
}
