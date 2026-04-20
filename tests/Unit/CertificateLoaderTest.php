<?php

use FactuTica\FactuticaCR\Exceptions\CertificateException;
use FactuTica\FactuticaCR\Services\Hacienda\CertificateLoaderService;

it('throws exception when path is not configured', function () {
    config()->set('invoicing-cr.hacienda.certificado.path', null);
    config()->set('invoicing-cr.hacienda.certificado.pin', null);

    $loader = new CertificateLoaderService();
    $loader->load();
})->throws(CertificateException::class, 'La ruta del certificado y el PIN son requeridos');

it('throws exception when certificate file does not exist', function () {
    config()->set('invoicing-cr.hacienda.certificado.path', '/nonexistent/cert.p12');
    config()->set('invoicing-cr.hacienda.certificado.pin', '1234');

    $loader = new CertificateLoaderService();
    $loader->load();
})->throws(CertificateException::class, 'no existe en la ruta');

it('throws exception when accessing certificate before loading', function () {
    $loader = new CertificateLoaderService();
    $loader->getCertificate();
})->throws(CertificateException::class, 'no ha sido cargado');

it('throws exception when accessing private key before loading', function () {
    $loader = new CertificateLoaderService();
    $loader->getPrivateKey();
})->throws(CertificateException::class, 'no ha sido cargado');

it('throws exception when accessing issuer serial before loading', function () {
    $loader = new CertificateLoaderService();
    $loader->getIssuerSerial();
})->throws(CertificateException::class, 'no ha sido cargado');

it('throws exception when accessing digest before loading', function () {
    $loader = new CertificateLoaderService();
    $loader->getCertificateDigestSha1();
})->throws(CertificateException::class, 'no ha sido cargado');

it('resolves relative path to storage_path', function () {
    config()->set('invoicing-cr.hacienda.certificado.path', 'certs/test.p12');
    config()->set('invoicing-cr.hacienda.certificado.pin', '1234');

    $loader = new CertificateLoaderService();

    // Will fail because file doesn't exist, but we can verify the path resolution
    try {
        $loader->load();
    } catch (CertificateException $e) {
        expect($e->getMessage())->toContain(storage_path('certs/test.p12'));
    }
});