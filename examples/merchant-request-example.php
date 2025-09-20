<?php
require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: text/plain; charset=utf-8');

use BazaarNexus\ClientAPI;

$apiKey     = "AUB0EGIHDPL61RLIU2TE5K83"; // REPLACE WITH YOUR API KEY
$privateKey = 'hNJVfuo258J03WVYnhHYRLORAZJINM/aIge9PgdmDcKcsVIfMBFDEcNdhS+uHCEvbUFE3mlKU76zr229ZeJPFw=='; // REPLACE WITH YOUR PRIVATE KEY or JSON FILE PATH

$client = new ClientAPI($apiKey, $privateKey, 'account');
$client->setEndPoint('https://bazaarnexus.com/api/public/v1/');

$response = $client->sendRequest('bs/test');

if ($client->isSuccess()) {
    print_r($response);
} else {
    echo "Error: \r\n" . $client->getError();
    echo "\r\n\r\n";
    echo "Raw Response: \r\n" . $client->getContents();
}