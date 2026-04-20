<?php

namespace FactuTica\FactuticaCR\Services\Hacienda\Idp;

class TokenData
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $accessExpiresAt,
        public int $refreshExpiresAt,
    ) {}

    /**
     * Verifica si el access token expiró (con margen de seguridad).
     */
    public function isAccessExpired(int $margin = \FactuTica\FactuticaCR\Constants::TOKEN_MARGIN_SECONDS): bool
    {
        return time() >= ($this->accessExpiresAt - $margin);
    }

    /**
     * Verifica si el refresh token expiró (con margen de seguridad).
     */
    public function isRefreshExpired(int $margin = \FactuTica\FactuticaCR\Constants::TOKEN_MARGIN_SECONDS): bool
    {
        return time() >= ($this->refreshExpiresAt - $margin);
    }

    /**
     * Crea una instancia desde la respuesta del IdP de Hacienda.
     */
    public static function fromResponse(array $data): self
    {
        $required = ['access_token', 'refresh_token', 'expires_in', 'refresh_expires_in'];
        $missing = array_diff($required, array_keys($data));

        if ($missing) {
            throw new \FactuTica\FactuticaCR\Exceptions\HaciendaException(
                'Respuesta del IdP incompleta, faltan: '.implode(', ', $missing)
            );
        }

        $now = time();

        return new self(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'],
            accessExpiresAt: $now + $data['expires_in'],
            refreshExpiresAt: $now + $data['refresh_expires_in'],
        );
    }
}
