<?php
namespace BazaarNexus;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;
use Throwable;

abstract class UnifiedAPI
{
    protected string $apiKey;
    protected string $privateKey;   // base64-encoded Ed25519 secret key after normalization
    protected string $authorize;
    protected ?string $endPoint = null;
    protected array $httpConfig;
    protected Client $httpClient;
    protected ?array $lastResponse = null;
    protected string $rawBody = '';

    public function __construct(string $apiKey, string $privateKey, string $authorize, array $httpConfig = [])
    {
        if (empty($apiKey) || empty($privateKey)) {
            throw new Exception("API key and Private Key are required");
        }

        $this->apiKey     = $apiKey;
        $this->privateKey = $this->normalizePrivateKey($privateKey);
        $this->authorize  = strtolower($authorize);

        $this->httpConfig = array_merge([
            'verify' => false,
            'headers' => [
                'User-Agent' => 'BazaarNexus PHP SDK',
            ],
            'timeout' => 20,
        ], $httpConfig);

        $this->httpClient = new Client($this->httpConfig);
    }

    /**
     * Normalize Private Key into a base64-encoded Ed25519 secret key.
     */
    protected function normalizePrivateKey(string $key): string
    {
        $key = trim($key);
        if(!$key) throw new Exception("Invalid Private Key");

        if(preg_match('/\.json$/i', $key) && file_exists($key)) {
            $key = file_get_contents($key);
            if(!$key) throw new Exception("Invalid Private Key");
            $key = trim($key);
        }

        // JSON {"private_key": "..."}
        $decoded = json_decode($key, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['private_key'])) {
            $key = trim($decoded['private_key']);
        }

        // Base64 decode
        $bin = base64_decode($key, true);
        if ($bin === false) {
            throw new Exception("Invalid Private Key format: not base64 or JSON");
        }

        // Full secret key (64 bytes)
        if (strlen($bin) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            return base64_encode($bin);
        }

        // Seed (32 bytes) → expand into full secret key
        if (strlen($bin) === SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            $kp = sodium_crypto_sign_seed_keypair($bin);
            $sk = sodium_crypto_sign_secretkey($kp);
            return base64_encode($sk);
        }

        throw new Exception("Invalid Ed25519 Private Key length");
    }

    /**
     * Sign a message using Ed25519 and return base64 signature.
     */
    private function signMessage(string $stringToSign): string
    {
        $rawKey = base64_decode($this->privateKey, true);
        if ($rawKey === false || strlen($rawKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new Exception("Invalid Ed25519 Private Key (must be 64-byte secret)");
        }

        $signature = sodium_crypto_sign_detached($stringToSign, $rawKey);
        return base64_encode($signature);
    }

    public function setEndPoint(string $endPoint): bool
    {
        $parsed = parse_url($endPoint);
        if ($parsed === false || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            $this->endPoint = rtrim($endPoint, '/') . '/';
            return true;
        }

        unset($parsed['query'], $parsed['fragment']);

        if (!empty($parsed['path']) && preg_match('/\.[a-z0-9]+$/i', basename($parsed['path']))) {
            $parsed['path'] = dirname($parsed['path']);
            if ($parsed['path'] === '.' || $parsed['path'] === '\\') {
                $parsed['path'] = '';
            }
        }

        $url = $parsed['scheme'] . '://' . $parsed['host'];
        if (!empty($parsed['port'])) {
            $url .= ':' . $parsed['port'];
        }
        $url .= !empty($parsed['path']) ? rtrim($parsed['path'], '/') . '/' : '/';

        $this->endPoint = $url;
        return true;
    }

    public function sendRequest(string $route = '', array $payload = []): array
    {
        if (empty($this->endPoint)) throw new Exception("Endpoint not set. Call setEndPoint() before sendRequest().");

        $route = str_replace(['\\','/','>'], '|', $route);
        $url   = $this->endPoint . '?route=' . $route;
        $nonce = (string) round(microtime(true) * 1000);

        ksort($payload);
        $bodyToSign   = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $stringToSign = $this->authorize . "\n" . $route . "\n" . $bodyToSign . "\n" . $nonce;
        $signature    = $this->signMessage($stringToSign);

        try {

            $response = $this->httpClient->post($url, [
                'json' => $payload,
                'headers' => [
                    'bazaarnexus-authorize' => $this->authorize,
                    'bazaarnexus-apikey'   => $this->apiKey,
                    'bazaarnexus-nonce'     => $nonce,
                    'bazaarnexus-signature' => $signature,
                    'bazaarnexus-route'     => $route, // redundancy for backend
                ],
            ]);

            $statusCode   = $response->getStatusCode();
            $this->rawBody = $response->getBody()->getContents();
            $data         = json_decode($this->rawBody, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($data['status'], $data['message'])) {
                $this->lastResponse = [
                    'status'  => $data['status'],
                    'message' => $data['message'],
                    'data'    => $data['data'] ?? [],
                    'code'    => $statusCode,
                ];
            } else {
                $this->lastResponse = [
                    'status'  => 'failed',
                    'message' => 'Invalid JSON response',
                    'data'    => [],
                    'code'    => $statusCode,
                ];
            }

        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 500;
            $this->rawBody       = $e->getResponse() ? (string)$e->getResponse()->getBody() : $e->getMessage();
            $json       = json_decode($this->rawBody, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($json['status'], $json['message'])) {
                $this->lastResponse = [
                    'status'  => $json['status'],
                    'message' => $json['message'],
                    'data'    => $json['data'] ?? [],
                    'code'    => $statusCode,
                ];
            } else {
                $this->lastResponse = [
                    'status'  => 'failed',
                    'message' => $e->getMessage(),
                    'data'    => [],
                    'code'    => $statusCode,
                ];
            }

        } catch (Throwable $e) {
            $this->lastResponse = [
                'status'  => 'failed',
                'message' => $e->getMessage(),
                'data'    => [],
                'code'    => 500, // internal SDK error
            ];
        }

        return $this->lastResponse;
    }

    public function getLastResponse(): ?array
    {
        return $this->lastResponse;
    }

    public function validate(): bool
    {
        return isset($this->lastResponse['status'], $this->lastResponse['message'], $this->lastResponse['code']);
    }

    public function isSuccess(): bool
    {
        return $this->validate()
            && $this->lastResponse['status'] === 'success'
            && $this->lastResponse['code'] >= 200
            && $this->lastResponse['code'] < 300; // accept any 2xx
    }

    public function getContents(): string
    {
        return $this->rawBody;
    }

    public function getData(): array
    {
        return $this->lastResponse['data'] ?? [];
    }

    public function getError(): ?string
    {
        return $this->isSuccess()? null : ($this->lastResponse['message'] ?? 'Unknown error');
    }
}