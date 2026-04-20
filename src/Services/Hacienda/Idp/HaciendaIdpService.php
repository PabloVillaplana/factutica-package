<?php

namespace FactuTica\FactuticaCR\Services\Hacienda\Idp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use FactuTica\FactuticaCR\Exceptions\HaciendaException;

class HaciendaIdpService
{
    private const CLIENT_IDS = [
        'sandbox'    => 'api-stag',
        'production' => 'api-prod',
    ];

    private ?TokenData $tokenData = null;

    /**
     * Obtiene un access token válido, usando cache y refresh cuando es posible.
     *
     * @throws HaciendaException
     */
    public function getAccessToken(): string
    {
        if ($this->tokenData === null) {
            $cached = Cache::get($this->getCacheKey());
            $this->tokenData = ($cached instanceof TokenData) ? $cached : null;
        }

        if ($this->tokenData === null) {
            $this->authenticate();
        } elseif ($this->tokenData->isAccessExpired()) {
            if (! $this->tokenData->isRefreshExpired()) {
                $this->refresh();
            } else {
                $this->authenticate();
            }
        }

        return $this->tokenData->accessToken;
    }

    /**
     * Autentica con el IdP de Hacienda vía OAuth2 (grant_type=password).
     *
     * @throws HaciendaException
     */
    private function authenticate(): void
    {
        $response = Http::timeout(10)->asForm()->post($this->getEndpoint(), [
            'grant_type' => 'password',
            'client_id'  => $this->getClientId(),
            'username'   => config('invoicing-cr.hacienda.idp.username'),
            'password'   => config('invoicing-cr.hacienda.idp.password'),
        ]);

        if (! $response->successful()) {
            throw new HaciendaException(
                'Error de autenticación con el IdP de Hacienda: '.($response->json('error_description') ?? $response->body()),
                $response->status()
            );
        }

        $this->tokenData = TokenData::fromResponse($response->json());
        $this->storeInCache();
    }

    /**
     * Renueva el token usando el refresh_token.
     * Si falla, re-autentica desde cero.
     *
     * @throws HaciendaException
     */
    private function refresh(): void
    {
        try {
            $response = Http::timeout(10)->asForm()->post($this->getEndpoint(), [
                'grant_type'    => 'refresh_token',
                'client_id'     => $this->getClientId(),
                'refresh_token' => $this->tokenData->refreshToken,
            ]);

            if (! $response->successful()) {
                $this->authenticate();

                return;
            }

            $this->tokenData = TokenData::fromResponse($response->json());
            $this->storeInCache();
        } catch (\Throwable $e) {
            Log::warning('HaciendaIdpService: refresh falló, re-autenticando', [
                'error' => $e->getMessage(),
            ]);
            $this->authenticate();
        }
    }

    /**
     * Cierra la sesión con el IdP (best-effort).
     */
    public function logout(): void
    {
        if ($this->tokenData === null) {
            return;
        }

        $ambiente = config('invoicing-cr.invoicing.ambiente', 'sandbox');
        $logoutUrls = [
            'sandbox'    => 'https://idp.comprobanteselectronicos.go.cr/auth/realms/rut-stag/protocol/openid-connect/logout',
            'production' => 'https://idp.comprobanteselectronicos.go.cr/auth/realms/rut/protocol/openid-connect/logout',
        ];

        try {
            Http::asForm()->post($logoutUrls[$ambiente] ?? $logoutUrls['sandbox'], [
                'client_id'     => $this->getClientId(),
                'refresh_token' => $this->tokenData->refreshToken,
            ]);
        } catch (\Throwable) {
            // Best-effort logout
        }

        $this->tokenData = null;
        Cache::forget($this->getCacheKey());
    }

    /**
     * Fuerza la renovación del token en el próximo llamado.
     */
    public function invalidateToken(): void
    {
        $this->tokenData = null;
        Cache::forget($this->getCacheKey());
    }

    private function getEndpoint(): string
    {
        $ambiente = config('invoicing-cr.invoicing.ambiente', 'sandbox');

        return config("invoicing-cr.hacienda.endpoints.{$ambiente}.idp");
    }

    private function getClientId(): string
    {
        $ambiente = config('invoicing-cr.invoicing.ambiente', 'sandbox');

        return self::CLIENT_IDS[$ambiente] ?? 'api-stag';
    }

    private function getCacheKey(): string
    {
        $ambiente = config('invoicing-cr.invoicing.ambiente', 'sandbox');
        $username = config('invoicing-cr.hacienda.idp.username', '');

        return 'invoicing_cr_idp_token_'.$ambiente.'_'.md5($username);
    }

    private function storeInCache(): void
    {
        $ttl = max(0, $this->tokenData->refreshExpiresAt - time() - \FactuTica\FactuticaCR\Constants::TOKEN_MARGIN_SECONDS);

        if ($ttl > 0) {
            Cache::put($this->getCacheKey(), $this->tokenData, $ttl);
        }
    }
}
