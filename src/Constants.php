<?php

namespace FactuTica\FactuticaCR;

/**
 * Constantes compartidas del paquete.
 */
final class Constants
{
    /** Longitud de la clave única de Hacienda (50 dígitos). */
    public const CLAVE_LENGTH = 50;

    /** Segundos de margen antes de considerar un token expirado. */
    public const TOKEN_MARGIN_SECONDS = 30;

    /** Días de anticipación para alertar sobre certificado próximo a vencer. */
    public const CERTIFICATE_WARNING_DAYS = 30;

    /** Código de país Costa Rica. */
    public const COUNTRY_CODE = '506';
}
