<?php

namespace FactuTica\FactuticaCR\Facades;

use Illuminate\Support\Facades\Facade;
use FactuTica\FactuticaCR\Services\InvoicingService;

/**
 * @method static array createAndSend(string $type, array $data)
 * @method static mixed getDocumentById(int|string $id)
 * @method static mixed getDocumentByUiKey(string $uiKey)
 * @method static mixed getDocumentStatus(string $uiKey)
 *
 * @see \FactuTica\FactuticaCR\Services\InvoicingService
 */
class Facturacion extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'invoicing';
    }
}