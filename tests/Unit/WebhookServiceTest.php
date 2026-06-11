<?php

use FactuTica\FactuticaCR\Services\Webhook\WebhookService;
use FactuTica\FactuticaCR\Services\Webhook\WebhookVerifierService;

function callExtractMessage(?string $base64Xml): ?string
{
    $service = new WebhookService(new WebhookVerifierService);
    $reflection = new ReflectionMethod($service, 'extractMessage');
    $reflection->setAccessible(true);

    return $reflection->invoke($service, $base64Xml);
}

it('returns null when respuesta-xml is null', function () {
    expect(callExtractMessage(null))->toBeNull();
});

it('returns null when xml has no DetalleMensaje', function () {
    $xml = '<MensajeHacienda><Clave>123</Clave></MensajeHacienda>';

    expect(callExtractMessage(base64_encode($xml)))->toBeNull();
});

it('cleans a DetalleMensaje with bracketed CSV block', function () {
    $detalle = "Comprobante rechazado por los siguientes motivos:\n[\ncodigo,mensaje\n\"\"04\"\",\"\"La identificación del receptor no es válida\"\"\n\"\"05\"\",\"\"El emisor no se encuentra registrado\"\"\n]";

    $xml = '<MensajeHacienda><DetalleMensaje><![CDATA['.$detalle.']]></DetalleMensaje></MensajeHacienda>';

    $result = callExtractMessage(base64_encode($xml));

    expect($result)->toBe(
        "Comprobante rechazado por los siguientes motivos:\n\n"
        ."Mensajes:\n"
        ."- La identificación del receptor no es válida\n"
        .'- El emisor no se encuentra registrado'
    );
});

it('returns trimmed raw message when DetalleMensaje has no CSV block', function () {
    $detalle = '  Comprobante recibido y procesado correctamente.  ';

    $xml = '<MensajeHacienda><DetalleMensaje>'.$detalle.'</DetalleMensaje></MensajeHacienda>';

    $result = callExtractMessage(base64_encode($xml));

    expect($result)->toBe('Comprobante recibido y procesado correctamente.');
});
