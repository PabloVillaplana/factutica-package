<?php

namespace FactuTica\FactuticaCR\Services\Hacienda;

use FactuTica\FactuticaCR\Exceptions\CertificateException;

/**
 * Carga un certificado .p12 (PKCS#12) de la Firma Digital SINPE
 * y expone la llave privada, certificado X509 y valores derivados.
 */
class CertificateLoaderService
{
    private string $privateKey;

    private string $certificate;

    private array $chain = [];

    private string $certificateBase64;

    private string $certificateDigestSha1;

    private string $certificateDigestSha256;

    private array $issuerSerial;

    private bool $loaded = false;

    /**
     * Resetea el estado en memoria para forzar la recarga del certificado.
     * Usar cuando se cambia de empresa activa en el mismo proceso (ej. queue workers).
     */
    public function reset(): void
    {
        $this->loaded = false;
    }

    /**
     * Carga el certificado .p12 desde la configuración.
     *
     * @throws CertificateException
     */
    public function load(): self
    {
        if ($this->loaded) {
            return $this;
        }

        $path = config('invoicing-cr.hacienda.certificado.path');
        $pin = config('invoicing-cr.hacienda.certificado.pin');

        if (! $path || ! $pin) {
            throw new CertificateException('La ruta del certificado y el PIN son requeridos.');
        }

        $fullPath = $this->resolvePath($path);

        if (! file_exists($fullPath)) {
            throw new CertificateException("El certificado no existe en la ruta: {$fullPath}");
        }

        $p12Content = file_get_contents($fullPath);

        if ($p12Content === false) {
            throw new CertificateException("No se pudo leer el certificado: {$fullPath}");
        }

        $certs = [];

        if (! openssl_pkcs12_read($p12Content, $certs, $pin)) {
            throw new CertificateException(
                'No se pudo abrir el certificado .p12. Verifique el PIN. OpenSSL: '.openssl_error_string()
            );
        }

        $this->privateKey = $certs['pkey'];
        $this->certificate = $certs['cert'];
        $this->chain = $certs['extracerts'] ?? [];

        // Pre-computar valores derivados inmutables
        $this->certificateBase64 = preg_replace(
            '/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s/',
            '',
            $this->certificate
        );

        $derCert = base64_decode($this->certificateBase64);
        $this->certificateDigestSha1 = base64_encode(hash('sha1', $derCert, true));
        $this->certificateDigestSha256 = base64_encode(hash('sha256', $derCert, true));

        $this->issuerSerial = $this->parseIssuerSerial();
        $this->validateExpiration();

        $this->loaded = true;

        return $this;
    }

    /**
     * Retorna la llave privada en formato PEM.
     *
     * @throws CertificateException
     */
    public function getPrivateKey(): string
    {
        $this->ensureLoaded();

        return $this->privateKey;
    }

    /**
     * Retorna el certificado público en formato PEM.
     *
     * @throws CertificateException
     */
    public function getCertificate(): string
    {
        $this->ensureLoaded();

        return $this->certificate;
    }

    /**
     * Retorna el certificado X509 como string base64 limpio (sin headers PEM).
     * Va dentro de <ds:X509Certificate> en el XML.
     *
     * @throws CertificateException
     */
    public function getCertificateBase64(): string
    {
        $this->ensureLoaded();

        return $this->certificateBase64;
    }

    /**
     * Retorna el digest SHA-1 del certificado en base64.
     * Va dentro de <xades:CertDigest> como requiere XAdES.
     *
     * @throws CertificateException
     */
    public function getCertificateDigestSha1(): string
    {
        $this->ensureLoaded();

        return $this->certificateDigestSha1;
    }

    /**
     * Retorna el digest SHA-256 del certificado en base64.
     *
     * @throws CertificateException
     */
    public function getCertificateDigestSha256(): string
    {
        $this->ensureLoaded();

        return $this->certificateDigestSha256;
    }

    /**
     * Retorna el Issuer Name y Serial Number del certificado.
     * Van dentro de <xades:IssuerSerial>.
     *
     * @return array{issuer: string, serial: string}
     *
     * @throws CertificateException
     */
    public function getIssuerSerial(): array
    {
        $this->ensureLoaded();

        return $this->issuerSerial;
    }

    /**
     * Retorna la cadena de certificados intermedios.
     *
     * @throws CertificateException
     */
    public function getChain(): array
    {
        $this->ensureLoaded();

        return $this->chain;
    }

    /**
     * Valida que el certificado no esté expirado.
     *
     * @throws CertificateException
     */
    private function validateExpiration(): void
    {
        $certResource = openssl_x509_read($this->certificate);
        $info = openssl_x509_parse($certResource);

        if (! $info || ! isset($info['validTo_time_t'])) {
            throw new CertificateException('No se pudo leer la información de expiración del certificado.');
        }

        if ($info['validTo_time_t'] < time()) {
            throw new CertificateException(
                'El certificado está expirado. Venció el: '.date('Y-m-d H:i:s', $info['validTo_time_t'])
            );
        }
    }

    /**
     * Extrae Issuer y Serial Number del certificado.
     *
     * @throws CertificateException
     */
    private function parseIssuerSerial(): array
    {
        $certResource = openssl_x509_read($this->certificate);
        $certDetails = openssl_x509_parse($certResource);

        if ($certDetails === false) {
            throw new CertificateException('No se pudo parsear el certificado X509.');
        }

        $issuer = $certDetails['issuer'];

        if (! is_array($issuer)) {
            throw new CertificateException('El certificado no contiene un Issuer válido.');
        }

        return [
            'issuer' => $this->buildDn($issuer),
            'serial' => (string) $certDetails['serialNumber'],
        ];
    }

    /**
     * Convierte el array de issuer de OpenSSL a formato DN string.
     * Orden específico para certificados SINPE de Costa Rica.
     */
    private function buildDn(array $issuer): string
    {
        $parts = [];
        $order = ['CN', 'OU', 'O', 'C', 'serialNumber'];

        foreach ($order as $key) {
            if (isset($issuer[$key])) {
                $value = is_array($issuer[$key]) ? implode(', ', $issuer[$key]) : $issuer[$key];
                $parts[] = "{$key}={$value}";
            }
        }

        return implode(',', $parts);
    }

    /**
     * @throws CertificateException
     */
    private function ensureLoaded(): void
    {
        if (! $this->loaded) {
            throw new CertificateException('El certificado no ha sido cargado. Llame a load() primero.');
        }
    }

    /**
     * Resuelve la ruta del certificado (absoluta o relativa a storage_path).
     */
    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return storage_path($path);
    }
}