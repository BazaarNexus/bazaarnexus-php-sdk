# BazaarNexus PHP SDK

A lightweight PHP SDK for accessing the BazaarNexus API using **Ed25519 key signatures**.  
This SDK unifies both **merchant (account)** and **customer** authentication flows.

## Requirements

- PHP 8.1+
- [libsodium extension](https://www.php.net/manual/en/book.sodium.php) (built-in since PHP 7.2)
- [guzzlehttp/guzzle](https://github.com/guzzle/guzzle) ^7.0
- Composer for dependency management

## Installation

composer require bazaarnexus/php-sdk

## Authentication Flow

Each API request is signed with:

- bazaarnexus-apikey – your API key (identifier)  
- bazaarnexus-authorize – the auth mode (account or customer)  
- bazaarnexus-nonce – millisecond timestamp (unique per request)  
- bazaarnexus-signature – Ed25519 signature over:

authorize + "\n" + route + "\n" + payload + "\n" + nonce

The backend verifies the signature using the public key tied to your API key.

## Usage

### Merchant (Account) Request

use BazaarNexus\ClientAPI;

$apiKey     = "MERCHANT_API_KEY";
$privateKey = "MERCHANT_PRIVATE_KEY";

$client = new ClientAPI($apiKey, $privateKey, 'account');
$client->setEndPoint('https://bazaarnexus.com/api/public/v1/');

try {
    $response = $client->sendRequest('orders/list', [
        'status' => 'pending',
        'limit'  => 10,
    ]);

    if ($client->isSuccess()) {
        print_r($client->getData());
    } else {
        echo "Error: " . $client->getError();
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}

### Customer Request

use BazaarNexus\ClientAPI;

$apiKey     = "CUSTOMER_API_KEY";
$privateKey = "CUSTOMER_PRIVATE_KEY";

$client = new ClientAPI($apiKey, $privateKey, 'customer');
$client->setEndPoint('https://example.com/api/public/v1/');

try {
    $response = $client->sendRequest('cart/view');
    if ($client->isSuccess()) {
        print_r($client->getData());
    } else {
        echo "Error: " . $client->getError();
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}

## Error Handling

$response = $client->getLastResponse();
if (!$client->isSuccess()) {
    echo "Error: " . $client->getError();
}

## Development Notes

- `UnifiedAPI` handles signing, HTTP client, response parsing.
- `ClientAPI` is a thin wrapper (for both merchants & customers).
- Extendable for other API roles in the future.

## Running Tests

The SDK includes PHPUnit tests under the `tests/` directory.

### Install PHPUnit

If not already installed:

    composer require --dev phpunit/phpunit ^10

### Run Tests

From the project root:

    ./vendor/bin/phpunit --bootstrap vendor/autoload.php tests

### Test Coverage

- **testSetEndPointNormalization**  
  Ensures `setEndPoint()` strips query strings, fragments, and file extensions correctly.

- **testInvalidPrivateKeyThrowsException**  
  Verifies that an invalid key format raises an `Exception`.

- **testSignAndRequestSimulation**  
  Uses reflection to test the private `signMessage()` method to confirm signatures are generated correctly with Ed25519.

- **testSendRequestWithoutEndpointThrowsException**  
  Confirms that `sendRequest()` cannot run unless `setEndPoint()` has been called.

### Notes

- The tests **mock key generation** using libsodium’s `sodium_crypto_sign_keypair()` so no real API keys are needed.  
- The actual HTTP request is **not executed**; instead, we test signing and endpoint validation logic.  
- Extend these tests with integration tests once a live BazaarNexus API endpoint is available.

## License

MIT License
