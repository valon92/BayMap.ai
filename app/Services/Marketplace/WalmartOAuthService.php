<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Walmart Marketplace OAuth2 tokens (client_credentials or refresh_token).
 *
 * @see https://developer.walmart.com/us-marketplace/reference/tokenapi
 */
class WalmartOAuthService
{
    private const CACHE_ACCESS = 'walmart_oauth_token';

    private const CACHE_REFRESH = 'walmart_oauth_refresh_token';

    public function getAccessToken(): string
    {
        $clientId = config('walmart.client_id');
        $clientSecret = config('walmart.client_secret');

        if (! $clientId || ! $clientSecret) {
            throw new \RuntimeException('WALMART_CLIENT_ID and WALMART_CLIENT_SECRET are required.');
        }

        $cached = Cache::get(self::CACHE_ACCESS);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $grantType = $this->grantType();

        $body = match ($grantType) {
            'refresh_token' => $this->refreshTokenBody(),
            'authorization_code' => $this->authorizationCodeBody(),
            default => ['grant_type' => 'client_credentials'],
        };

        $payload = $this->requestToken($body);
        $this->storeTokenResponse($payload);

        $token = $payload['access_token'] ?? '';
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('Walmart OAuth returned empty token.');
        }

        return $token;
    }

    public function isConfigured(): bool
    {
        if (! config('walmart.enabled') || ! config('walmart.client_id') || ! config('walmart.client_secret')) {
            return false;
        }

        return match ($this->grantType()) {
            'refresh_token' => $this->refreshToken() !== '',
            'authorization_code' => config('walmart.auth_code') && config('walmart.redirect_uri'),
            default => true,
        };
    }

    /**
     * @return array<string, string>
     */
    public function requestHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'WM_SEC.ACCESS_TOKEN' => $this->getAccessToken(),
            'WM_QOS.CORRELATION_ID' => (string) Str::uuid(),
            'WM_SVC.NAME' => (string) config('walmart.svc_name', 'Walmart Marketplace'),
            'WM_MARKET' => strtoupper((string) config('walmart.market', 'US')),
            'WM_GLOBAL_VERSION' => '3.1',
        ];

        $channel = config('walmart.consumer_channel_type');
        if (is_string($channel) && $channel !== '') {
            $headers['WM_CONSUMER.CHANNEL.TYPE'] = $channel;
        }

        return $headers;
    }

    private function grantType(): string
    {
        $grant = strtolower(trim((string) config('walmart.grant_type', 'client_credentials')));

        return in_array($grant, ['client_credentials', 'refresh_token', 'authorization_code'], true)
            ? $grant
            : 'client_credentials';
    }

    /**
     * @return array<string, string>
     */
    private function refreshTokenBody(): array
    {
        $refreshToken = $this->refreshToken();
        if ($refreshToken === '') {
            throw new \RuntimeException('WALMART_REFRESH_TOKEN is required for refresh_token grant.');
        }

        return [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function authorizationCodeBody(): array
    {
        $code = trim((string) config('walmart.auth_code', ''));
        $redirectUri = trim((string) config('walmart.redirect_uri', ''));

        if ($code === '' || $redirectUri === '') {
            throw new \RuntimeException('WALMART_AUTH_CODE and WALMART_REDIRECT_URI are required for authorization_code grant.');
        }

        return [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];
    }

    private function refreshToken(): string
    {
        $cached = Cache::get(self::CACHE_REFRESH);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return trim((string) config('walmart.refresh_token', ''));
    }

    /**
     * @param  array<string, string>  $body
     * @return array<string, mixed>
     */
    private function requestToken(array $body): array
    {
        $clientId = config('walmart.client_id');
        $clientSecret = config('walmart.client_secret');
        $base = rtrim((string) config('walmart.base_url'), '/');

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->timeout((int) config('walmart.timeout', 20))
            ->withHeaders([
                'Accept' => 'application/json',
                'WM_QOS.CORRELATION_ID' => (string) Str::uuid(),
                'WM_SVC.NAME' => (string) config('walmart.svc_name', 'Walmart Marketplace'),
            ])
            ->post("{$base}/v3/token", $body);

        if (! $response->successful()) {
            Log::warning('Walmart OAuth failed', [
                'grant_type' => $body['grant_type'] ?? '',
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 400),
            ]);
            throw new \RuntimeException('Walmart OAuth failed: '.$response->status());
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new \RuntimeException('Walmart OAuth returned invalid JSON.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeTokenResponse(array $payload): void
    {
        $token = $payload['access_token'] ?? '';
        if (! is_string($token) || $token === '') {
            return;
        }

        $expiresIn = (int) ($payload['expires_in'] ?? 900);
        Cache::put(self::CACHE_ACCESS, $token, max(60, $expiresIn - 60));

        $newRefresh = $payload['refresh_token'] ?? '';
        if (is_string($newRefresh) && $newRefresh !== '') {
            Cache::forever(self::CACHE_REFRESH, $newRefresh);
        }
    }
}
