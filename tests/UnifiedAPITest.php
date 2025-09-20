<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use BazaarNexus\ClientAPI;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client;

final class UnifiedAPITest extends TestCase
{
    private string $apiKey = 'TEST_API_KEY';
    private string $privateKey;

    protected function setUp(): void
    {
        $kp = sodium_crypto_sign_keypair();
        $sk = sodium_crypto_sign_secretkey($kp);
        $this->privateKey = base64_encode($sk);
    }

    public function testSetEndPointNormalization(): void
    {
        $client = new ClientAPI($this->apiKey, $this->privateKey, 'account');
        $client->setEndPoint('https://example.com/api/public/v1/index.php?debug=true#foo');

        $ref = new ReflectionClass($client);
        $prop = $ref->getProperty('endPoint');
        $prop->setAccessible(true);
        $endPoint = $prop->getValue($client);

        $this->assertSame('https://example.com/api/public/v1/', $endPoint);
    }

    public function testSendRequestWithMockHandler(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['status' => 'success', 'message' => 'ok', 'data' => ['test' => 1]]))
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new ClientAPI($this->apiKey, $this->privateKey, 'account', ['handler' => $handlerStack]);
        $client->setEndPoint('https://example.com/api/public/v1/');

        $response = $client->sendRequest('test/route', ['foo' => 'bar']);

        $this->assertEquals('success', $response['status']);
        $this->assertEquals('ok', $response['message']);
        $this->assertArrayHasKey('test', $response['data']);
    }

    public function testSendRequestWithoutEndpointThrowsException(): void
    {
        $client = new ClientAPI($this->apiKey, $this->privateKey, 'account');
        $this->expectException(Exception::class);
        $client->sendRequest('orders/list');
    }
}