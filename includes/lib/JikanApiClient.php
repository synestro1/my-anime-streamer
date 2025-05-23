<?php

namespace App\JikanApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class JikanApiClient {
    private Client $client;
    private string $baseUri = 'https://api.jikan.moe/v4/';

    public function __construct() {
        $this->client = new Client([
            'base_uri' => $this->baseUri,
            'timeout'  => 5.0, // seconds
        ]);
    }

    private function handleResponse(ResponseInterface $response) {
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Handle JSON decode error
            error_log('Jikan API: JSON decode error: ' . json_last_error_msg());
            return null;
        }
        return $data;
    }

    public function getAnimeById(int $id) {
        try {
            $response = $this->client->request('GET', "anime/{$id}");
            return $this->handleResponse($response);
        } catch (RequestException $e) {
            error_log("Jikan API Error (getAnimeById: {$id}): " . $e->getMessage());
            if ($e->hasResponse()) {
                error_log("Response Body: " . $e->getResponse()->getBody()->getContents());
            }
            return null;
        }
    }

    public function searchAnime(string $query, int $limit = 10) {
        try {
            $response = $this->client->request('GET', 'anime', [
                'query' => [
                    'q' => $query,
                    'limit' => $limit
                ]
            ]);
            return $this->handleResponse($response);
        } catch (RequestException $e) {
            error_log("Jikan API Error (searchAnime: {$query}): " . $e->getMessage());
            if ($e->hasResponse()) {
                error_log("Response Body: " . $e->getResponse()->getBody()->getContents());
            }
            return null;
        }
    }
}
