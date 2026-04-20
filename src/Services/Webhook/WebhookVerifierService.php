<?php

namespace FactuTica\FactuticaCR\Services\Webhook;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use FactuTica\FactuticaCR\Models\Receipt;

/**
 * Verifica la autenticidad del webhook de Hacienda.
 *
 * Dos capas de verificación:
 *  1. Estructura de la clave — el ID del emisor embebido en los 50 dígitos debe coincidir.
 *  2. Firma digital del XML — el respuesta-xml debe estar firmado por el certificado de Hacienda.
 */
class WebhookVerifierService
{
    private const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';

    /**
     * Identificadores conocidos del certificado de sello electrónico de Hacienda.
     */
    private const HACIENDA_CERT_IDENTIFIERS = [
        'CPJ-2-100-042005',
        'MINISTERIO DE HACIENDA',
    ];

    /**
     * @return array{valid: bool, reason: ?string}
     */
    public function verify(array $webhookData, Receipt $receipt): array
    {
        // Capa 1: Validar estructura de la clave
        $claveCheck = $this->verifyClave($webhookData['clave'] ?? '', $receipt);

        if (! $claveCheck['valid']) {
            return $claveCheck;
        }

        // Capa 2: Verificar firma digital del XML de respuesta
        if (! empty($webhookData['respuesta-xml'])) {
            $signatureCheck = $this->verifyXmlSignature($webhookData['respuesta-xml']);

            if (! $signatureCheck['valid']) {
                return $signatureCheck;
            }
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * Verifica que el ID del emisor en la clave coincida con el del receipt.
     *
     * Estructura de la clave (50 dígitos):
     *   Pos 0-2:   Código de país (506)
     *   Pos 3-4:   Día
     *   Pos 5-6:   Mes
     *   Pos 7-8:   Año
     *   Pos 9-20:  ID del emisor (12 dígitos, zero-padded)
     *   Pos 21-40: Consecutivo (20 dígitos)
     *   Pos 41:    Situación
     *   Pos 42-49: Código de seguridad
     */
    private function verifyClave(string $clave, Receipt $receipt): array
    {
        if (strlen($clave) !== \FactuTica\FactuticaCR\Constants::CLAVE_LENGTH) {
            return ['valid' => false, 'reason' => 'Longitud de clave inválida: se esperan 50 dígitos.'];
        }

        if ($receipt->ui_key !== $clave) {
            return ['valid' => false, 'reason' => 'La clave no coincide con el ui_key del comprobante.'];
        }

        $claveEmisorId = substr($clave, 9, 12);
        $storedEmisorId = str_pad(
            preg_replace('/[^0-9]/', '', $receipt->issuer_number),
            12, '0', STR_PAD_LEFT
        );

        if ($claveEmisorId !== $storedEmisorId) {
            return [
                'valid' => false,
                'reason' => "ID del emisor no coincide: clave={$claveEmisorId}, receipt={$storedEmisorId}.",
            ];
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * Verifica la firma digital XAdES del XML de respuesta de Hacienda.
     *
     * 1. Decodifica base64 → carga XML en DOMDocument
     * 2. Extrae ds:SignedInfo y canonicaliza con xml-exc-c14n
     * 3. Extrae ds:SignatureValue (bytes raw)
     * 4. Extrae X509Certificate de ds:KeyInfo
     * 5. openssl_verify(canonicalSignedInfo, signatureRaw, cert, SHA256)
     * 6. Valida que el certificado pertenece a Hacienda
     */
    private function verifyXmlSignature(string $base64Xml): array
    {
        try {
            $xmlString = base64_decode($base64Xml);

            if ($xmlString === false) {
                return ['valid' => false, 'reason' => 'No se pudo decodificar el XML base64 de respuesta.'];
            }

            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = true;
            $dom->formatOutput = false;

            if (! $dom->loadXML($xmlString)) {
                return ['valid' => false, 'reason' => 'No se pudo parsear el XML de respuesta.'];
            }

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('ds', self::NS_DS);

            // Extraer SignedInfo
            $signedInfoNode = $xpath->query('//ds:Signature/ds:SignedInfo')->item(0);

            if (! $signedInfoNode) {
                return ['valid' => false, 'reason' => 'No se encontró ds:SignedInfo en la firma XML.'];
            }

            $canonicalSignedInfo = $signedInfoNode->C14N(true, false);

            // Extraer SignatureValue
            $signatureValueNode = $xpath->query('//ds:Signature/ds:SignatureValue')->item(0);

            if (! $signatureValueNode) {
                return ['valid' => false, 'reason' => 'No se encontró ds:SignatureValue en la firma XML.'];
            }

            $signatureRaw = base64_decode(trim($signatureValueNode->textContent));

            if ($signatureRaw === false) {
                return ['valid' => false, 'reason' => 'No se pudo decodificar el SignatureValue.'];
            }

            // Extraer X509Certificate
            $certNode = $xpath->query('//ds:Signature/ds:KeyInfo/ds:X509Data/ds:X509Certificate')->item(0);

            if (! $certNode) {
                return ['valid' => false, 'reason' => 'No se encontró X509Certificate en la firma XML.'];
            }

            $pemCert = "-----BEGIN CERTIFICATE-----\n"
                .chunk_split(trim($certNode->textContent), 64)
                ."-----END CERTIFICATE-----";

            // Verificar firma
            $publicKey = openssl_pkey_get_public($pemCert);

            if (! $publicKey) {
                return ['valid' => false, 'reason' => 'No se pudo cargar la llave pública del certificado.'];
            }

            $verifyResult = openssl_verify($canonicalSignedInfo, $signatureRaw, $publicKey, OPENSSL_ALGO_SHA256);

            if ($verifyResult !== 1) {
                $error = openssl_error_string() ?: 'error desconocido';

                return ['valid' => false, 'reason' => "Verificación de firma XML falló: {$error}."];
            }

            // Verificar que el certificado pertenece a Hacienda
            return $this->verifyHaciendaCertificate($pemCert);
        } catch (\Throwable $e) {
            Log::error('WebhookVerifier: error verificando firma', ['error' => $e->getMessage()]);

            return ['valid' => false, 'reason' => 'Error al verificar firma: '.$e->getMessage()];
        }
    }

    /**
     * Verifica que el certificado X509 pertenece a Hacienda
     * buscando identificadores conocidos en el subject.
     */
    private function verifyHaciendaCertificate(string $pemCert): array
    {
        $certData = openssl_x509_parse($pemCert);

        if ($certData === false) {
            return ['valid' => false, 'reason' => 'No se pudo parsear el certificado X509.'];
        }

        $subject = $certData['subject'] ?? [];
        $subjectParts = [];

        foreach ($subject as $value) {
            $subjectParts[] = is_array($value) ? implode(',', $value) : (string) $value;
        }

        $subjectString = strtoupper(implode(' ', $subjectParts));

        foreach (self::HACIENDA_CERT_IDENTIFIERS as $identifier) {
            if (str_contains($subjectString, strtoupper($identifier))) {
                return ['valid' => true, 'reason' => null];
            }
        }

        Log::warning('WebhookVerifier: certificado desconocido', [
            'subject' => $certData['subject'],
            'issuer'  => $certData['issuer'],
        ]);

        return ['valid' => false, 'reason' => 'El certificado no pertenece a Hacienda.'];
    }
}
