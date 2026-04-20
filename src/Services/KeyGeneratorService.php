<?php

namespace FactuTica\FactuticaCR\Services;

use FactuTica\FactuticaCR\Enums\IdentificationType;
use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;

/**
 * Genera la clave única de 50 dígitos y el número consecutivo de 20 dígitos
 * según las especificaciones del Ministerio de Hacienda de Costa Rica.
 */
class KeyGeneratorService
{
    private const COUNTRY_CODE = \FactuTica\FactuticaCR\Constants::COUNTRY_CODE;

    private const CLAVE_LENGTH = \FactuTica\FactuticaCR\Constants::CLAVE_LENGTH;

    /**
     * Mapa de ReceiptType a código numérico de Hacienda para el consecutivo.
     */
    private const TYPE_CODES = [
        'FE'  => '01',
        'ND'  => '02',
        'NC'  => '03',
        'TE'  => '04',
        'FEC' => '08',
        'FEE' => '09',
        'REP' => '07',
    ];

    /**
     * Genera la clave única de 50 dígitos.
     *
     * Estructura:
     *   Pos 0-2:   Código de país (506)
     *   Pos 3-4:   Día
     *   Pos 5-6:   Mes
     *   Pos 7-8:   Año (2 dígitos)
     *   Pos 9-20:  ID del emisor (12 dígitos, zero-padded según tipo)
     *   Pos 21-40: Número consecutivo (20 dígitos)
     *   Pos 41:    Situación del comprobante (1=normal, 2=contingencia, 3=sin internet)
     *   Pos 42-49: Código de seguridad (8 dígitos)
     *
     * @throws InvalidReceiptException
     */
    public function generateUniqueKey(
        ReceiptType $type,
        string $emisorId,
        IdentificationType $idType,
        int $consecutive,
        \DateTimeInterface $emissionDate,
        string $situation = '1',
        int $establishment = 1,
        int $terminal = 1,
    ): string {
        $day   = str_pad($emissionDate->format('d'), 2, '0', STR_PAD_LEFT);
        $month = str_pad($emissionDate->format('m'), 2, '0', STR_PAD_LEFT);
        $year  = substr($emissionDate->format('Y'), -2);

        $formattedId     = $this->formatIdentificationNumber($emisorId, $idType);
        $consecutiveKey  = $this->generateConsecutiveKey($type, $consecutive, $establishment, $terminal);
        $securityCode    = $this->generateSecurityCode();

        $key = self::COUNTRY_CODE
            .$day.$month.$year
            .$formattedId
            .$consecutiveKey
            .$situation
            .$securityCode;

        if (strlen($key) !== self::CLAVE_LENGTH) {
            throw new InvalidReceiptException("La clave generada tiene ".strlen($key)." dígitos, se esperan ".self::CLAVE_LENGTH.".");
        }

        return $key;
    }

    /**
     * Genera el número consecutivo de 20 dígitos.
     *
     * Formato: AAABBBBBCCDDDDDDDDDD
     *   AAA (1-3):     Sucursal (001 = principal)
     *   BBBBB (4-8):   Terminal/punto de venta
     *   CC (9-10):     Código de tipo de comprobante
     *   DDDDDDDDDD (11-20): Número consecutivo
     *
     * @throws InvalidReceiptException
     */
    public function generateConsecutiveKey(
        ReceiptType $type,
        int $consecutive,
        int $establishment = 1,
        int $terminal = 1,
    ): string {
        if ($establishment < 1 || $establishment > 999) {
            throw new InvalidReceiptException("Sucursal debe estar entre 1 y 999, recibido: {$establishment}");
        }

        if ($terminal < 1 || $terminal > 99999) {
            throw new InvalidReceiptException("Terminal debe estar entre 1 y 99999, recibido: {$terminal}");
        }

        if ($consecutive < 1 || $consecutive > 9999999999) {
            throw new InvalidReceiptException("Consecutivo debe estar entre 1 y 9999999999, recibido: {$consecutive}");
        }

        $typeCode = self::TYPE_CODES[$type->value]
            ?? throw new InvalidReceiptException("Tipo de comprobante sin código: {$type->value}");

        return str_pad((string) $establishment, 3, '0', STR_PAD_LEFT)
            .str_pad((string) $terminal, 5, '0', STR_PAD_LEFT)
            .$typeCode
            .str_pad((string) $consecutive, 10, '0', STR_PAD_LEFT);
    }

    /**
     * Formatea el número de identificación a 12 dígitos según el tipo.
     *
     * - Física (01):   hasta 9 dígitos → pad a 12
     * - Jurídica (02): hasta 10 dígitos → pad a 12
     * - DIMEX (03):    11 o 12 dígitos
     * - NITE (04):     hasta 10 dígitos → pad a 12
     *
     * @throws InvalidReceiptException
     */
    public function formatIdentificationNumber(string $number, IdentificationType $type): string
    {
        $clean = preg_replace('/[^0-9]/', '', $number);

        if (empty($clean)) {
            throw new InvalidReceiptException('El número de identificación debe contener solo dígitos.');
        }

        $len = strlen($clean);

        return match ($type) {
            IdentificationType::Physical => $len <= 9
                ? str_pad($clean, 12, '0', STR_PAD_LEFT)
                : throw new InvalidReceiptException("Cédula física debe tener máximo 9 dígitos, recibido: {$len}"),

            IdentificationType::Legal => $len <= 10
                ? str_pad($clean, 12, '0', STR_PAD_LEFT)
                : throw new InvalidReceiptException("Cédula jurídica debe tener máximo 10 dígitos, recibido: {$len}"),

            IdentificationType::Dimex => match (true) {
                $len === 11 => '0'.$clean,
                $len === 12 => $clean,
                default => throw new InvalidReceiptException("DIMEX debe tener 11 o 12 dígitos, recibido: {$len}"),
            },

            IdentificationType::Nite => $len <= 10
                ? str_pad($clean, 12, '0', STR_PAD_LEFT)
                : throw new InvalidReceiptException("NITE debe tener máximo 10 dígitos, recibido: {$len}"),

            default => str_pad($clean, 12, '0', STR_PAD_LEFT),
        };
    }

    /**
     * Genera un código de seguridad de 8 dígitos.
     */
    public function generateSecurityCode(): string
    {
        $hash = hash('sha256', uniqid(more_entropy: true).microtime().random_bytes(16));
        $numbers = preg_replace('/[^0-9]/', '', $hash);
        $code = substr($numbers, 0, 8);

        if (strlen($code) < 8) {
            $code = str_pad($code, 8, (string) random_int(0, 9), STR_PAD_RIGHT);
        }

        return $code;
    }
}
