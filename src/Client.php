<?php
namespace Vegas0250\Ozoner;

use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class Client
{
    const BASE_AUTH_URL = 'https://xapi.ozon.ru/principal-auth-api';
    const BASE_INTEGRATION_URL = 'https://xapi.ozon.ru/principal-integration-api';

    private $httpClient;
    private $clientId;
    private $clientSecret;

    public function __construct($clientId, $clientSecret) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;

        $this->httpClient = new \GuzzleHttp\Client([
            // 'base_uri' => self::BASE_URL,
            'defaults' => [
                'headers' => [
                    'content-type' => 'application/json'
                ]
            ]
        ]);
    }

    public function getToken() {
        $fileCache = new FilesystemAdapter('ozoner');

        $tokenKey = 'token';

        $token = $fileCache->get($tokenKey, function(ItemInterface $item) {
            $rawResponse = $this->httpClient->request('post',self::BASE_AUTH_URL.'/connect/token', [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ]);


            $response = \json_decode($rawResponse->getBody()->getContents(), true);

            $item->expiresAfter($response['expires_in']);
            $item->set($response['access_token']);

            return $response['access_token'];
        });

        if (!$token) $fileCache->deleteItem($tokenKey);

        return $token;
    }

    public function request($method, $url, $params = []) {
        $token = $this->getToken();

        $rowResponse = $this->httpClient->request($method, self::BASE_INTEGRATION_URL.$url, [
            'headers' => [
                'Authorization' => 'Bearer '.$token
            ],
            $method == 'get' ? 'query' : 'json' => $params
        ]);

        return \json_decode($rowResponse->getBody()->getContents(), true);
    }


}