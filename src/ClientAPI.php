<?php
namespace BazaarNexus;

class ClientAPI extends UnifiedAPI
{
    public function __construct(string $apiKey, string $privateKey, string $authorize, array $httpConfig = [])
    {
        parent::__construct($apiKey, $privateKey, $authorize, $httpConfig);
    }
}