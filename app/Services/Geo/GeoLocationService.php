<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves visitor country/region via free IP geolocation APIs.
 */
class GeoLocationService
{
    /** @var array<string, string> */
    private array $countryToLocale = [
        'XK' => 'sq',
        'AL' => 'sq',
        'MK' => 'sq',
        'DE' => 'de',
        'AT' => 'de',
        'CH' => 'de',
        'FR' => 'fr',
        'IT' => 'it',
        'ES' => 'es',
        'US' => 'en',
        'GB' => 'en',
        'IE' => 'en',
        'AU' => 'en',
        'CA' => 'en',
    ];

    /**
     * @return array<string, mixed>
     */
    public function resolve(?string $ip = null): array
    {
        $ip = $ip ?: request()->ip();

        if ($this->isLocalIp($ip)) {
            return $this->fallback();
        }

        $ipApi = $this->fetchFromIpApi($ip);
        if ($ipApi) {
            return $ipApi;
        }

        $ipApiCo = $this->fetchFromIpApiCo($ip);
        if ($ipApiCo) {
            return $ipApiCo;
        }

        return $this->fallback();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchFromIpApi(string $ip): ?array
    {
        try {
            $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,country,countryCode,regionName,city,lat,lon,query',
            ]);

            if (! $response->successful() || ($response->json('status') !== 'success')) {
                return null;
            }

            $code = $response->json('countryCode', 'US');

            return [
                'ip' => $response->json('query', $ip),
                'country' => $response->json('country', 'Unknown'),
                'country_code' => $code,
                'region' => $response->json('regionName'),
                'city' => $response->json('city'),
                'latitude' => $response->json('lat'),
                'longitude' => $response->json('lon'),
                'locale' => $this->localeForCountry($code),
                'provider' => 'ip-api.com',
            ];
        } catch (\Throwable $e) {
            Log::debug('ip-api.com geolocation failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchFromIpApiCo(string $ip): ?array
    {
        try {
            $response = Http::timeout(3)->get("https://ipapi.co/{$ip}/json/");

            if (! $response->successful() || $response->json('error')) {
                return null;
            }

            $code = $response->json('country_code', 'US');

            return [
                'ip' => $ip,
                'country' => $response->json('country_name', 'Unknown'),
                'country_code' => $code,
                'region' => $response->json('region'),
                'city' => $response->json('city'),
                'latitude' => $response->json('latitude'),
                'longitude' => $response->json('longitude'),
                'locale' => $this->localeForCountry($code),
                'provider' => 'ipapi.co',
            ];
        } catch (\Throwable $e) {
            Log::debug('ipapi.co geolocation failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function localeForCountry(string $countryCode): string
    {
        return $this->countryToLocale[strtoupper($countryCode)] ?? 'en';
    }

    /**
     * @return array<string, mixed>
     */
    private function fallback(): array
    {
        return [
            'ip' => request()->ip(),
            'country' => 'Kosovo',
            'country_code' => 'XK',
            'region' => 'Pristina',
            'city' => 'Pristina',
            'latitude' => 42.6629,
            'longitude' => 21.1655,
            'locale' => 'sq',
            'provider' => 'fallback',
        ];
    }

    private function isLocalIp(?string $ip): bool
    {
        return ! $ip || $ip === '127.0.0.1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.');
    }
}
