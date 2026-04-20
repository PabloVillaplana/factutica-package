<?php

namespace FactuTica\FactuticaCR\Console;

use Illuminate\Console\Command;
use FactuTica\FactuticaCR\Exceptions\CertificateException;

class CheckCertificateCommand extends Command
{
    protected $signature = 'invoicing:check-certificate';

    protected $description = 'Verifica el estado y expiración del certificado .p12 de firma digital';

    public function handle(): int
    {
        $path = config('invoicing-cr.hacienda.certificado.path');
        $pin = config('invoicing-cr.hacienda.certificado.pin');

        if (! $path || ! $pin) {
            $this->error('La ruta del certificado y el PIN no están configurados.');
            $this->line('Variables: INVOICING_CR_CERTIFICADO_PATH, INVOICING_CR_CERTIFICADO_PIN');

            return self::FAILURE;
        }

        $fullPath = str_starts_with($path, '/') ? $path : storage_path($path);

        if (! file_exists($fullPath)) {
            $this->error("El certificado no existe en: {$fullPath}");

            return self::FAILURE;
        }

        $p12Content = file_get_contents($fullPath);
        $certs = [];

        if (! openssl_pkcs12_read($p12Content, $certs, $pin)) {
            $this->error('No se pudo abrir el certificado. Verifique el PIN.');

            return self::FAILURE;
        }

        $certInfo = openssl_x509_parse($certs['cert']);

        if (! $certInfo) {
            $this->error('No se pudo parsear el certificado X509.');

            return self::FAILURE;
        }

        $subject = $certInfo['subject'];
        $nombre = $subject['CN'] ?? $subject['O'] ?? 'Desconocido';
        $serialNumber = $subject['serialNumber'] ?? $subject['2.5.4.5'] ?? 'N/A';
        $validFrom = date('Y-m-d', $certInfo['validFrom_time_t']);
        $validTo = date('Y-m-d', $certInfo['validTo_time_t']);
        $daysLeft = (int) ceil(($certInfo['validTo_time_t'] - time()) / 86400);

        $this->line('');
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Ruta', $fullPath],
                ['Titular', $nombre],
                ['Identificación', $serialNumber],
                ['Válido desde', $validFrom],
                ['Vence', $validTo],
                ['Días restantes', $daysLeft],
            ]
        );

        if ($daysLeft <= 0) {
            $this->error("EXPIRADO — el certificado venció hace ".abs($daysLeft)." días.");

            return self::FAILURE;
        }

        if ($daysLeft <= \FactuTica\FactuticaCR\Constants::CERTIFICATE_WARNING_DAYS) {
            $this->warn("ALERTA — el certificado vence en {$daysLeft} días. Renuévelo pronto.");

            return self::SUCCESS;
        }

        $this->info("OK — el certificado es válido por {$daysLeft} días más.");

        return self::SUCCESS;
    }
}