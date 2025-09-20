<?php
require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: text/plain; charset=utf-8');

use BazaarNexus\ClientAPI;

$apiKey     = "BZRX9Q2L7APF5HY3DMKC8TJN"; // REPLACE WITH YOUR API KEY
$privateKey = 'WkYixGmj0abYNKZy/U/5VyiwHKc7srVxavMhbN/dzaHZ6h8hzrGP8yikG1ARCuheVsn6OfWPbEvHehv/4racVA=='; // REPLACE WITH YOUR PRIVATE KEY or JSON FILE PATH

$client = new ClientAPI($apiKey, $privateKey, 'customer');
$client->setEndPoint('https://example.com/api/public/v1/');

$response = $client->sendRequest('bs/test');

if ($client->isSuccess()) {
    print_r($response);
} else {
    echo "Error: \r\n" . $client->getError();
    echo "\r\n\r\n";
    echo "Raw Response: \r\n" . $client->getContents();
}