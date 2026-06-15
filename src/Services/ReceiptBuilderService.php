<?php

namespace FactuTica\FactuticaCR\Services;

use Illuminate\Support\Facades\DB;
use FactuTica\FactuticaCR\Enums\HaciendaStatus;
use FactuTica\FactuticaCR\Events\ReceiptCreated;
use FactuTica\FactuticaCR\Enums\IdentificationType;
use FactuTica\FactuticaCR\Enums\ReceiptStatus;
use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Models\Receipt;
use FactuTica\FactuticaCR\Models\ReceiptConsecutive;
use FactuTica\FactuticaCR\Models\ReceiptPayload;

/**
 * Construye y persiste un Receipt con su payload.
 *
 * Responsabilidades:
 * - Generar consecutivo (con lock para concurrencia)
 * - Generar clave de 50 dígitos y consecutivo de 20 dígitos
 * - Inyectar datos auto-generados (Emisor, FechaEmision, etc.)
 * - Persistir Receipt + ReceiptPayload
 */
class ReceiptBuilderService
{
    public function __construct(
        private readonly KeyGeneratorService $keyGenerator,
    ) {}

    /**
     * Construye el Receipt, inyecta datos, y persiste en DB.
     *
     * @return array{receipt: Receipt, data: array, uniqueKey: string}
     */
    public function build(ReceiptType $receiptType, array $data): array
    {
        $emissionDate = now('America/Costa_Rica');
        $establishment = (int) ($data['establishment'] ?? config('invoicing-cr.invoicing.defaults.establishment', 1));
        $terminal = (int) ($data['terminal'] ?? config('invoicing-cr.invoicing.defaults.terminal', 1));
        $consecutive = $this->getNextConsecutive($receiptType, $establishment, $terminal);

        $emisorId = $data['issuer_number'] ?? config('invoicing-cr.invoicing.emisor.cedula');
        $emisorTipo = $data['issuer_identification_type'] ?? config('invoicing-cr.invoicing.emisor.tipo_cedula', '01');
        $idType = IdentificationType::tryFrom($emisorTipo) ?? IdentificationType::Physical;

        $consecutiveKey = $this->keyGenerator->generateConsecutiveKey($receiptType, $consecutive, $establishment, $terminal);
        $uniqueKey = $this->keyGenerator->generateUniqueKey(
            type: $receiptType,
            emisorId: $emisorId,
            idType: $idType,
            consecutive: $consecutive,
            emissionDate: $emissionDate,
            establishment: $establishment,
            terminal: $terminal,
        );

        $data = $this->injectGeneratedData($data, $uniqueKey, $consecutiveKey, $emissionDate);

        $receipt = Receipt::create([
            'receipt_type'                 => $receiptType,
            'ui_key'                       => $uniqueKey,
            'external_reference'           => $data['external_reference'] ?? null,
            'establishment'                => $establishment,
            'terminal'                     => $terminal,
            'consecutive_number'           => $consecutiveKey,
            'emission_date'                => $emissionDate,
            'receipt_status'               => ReceiptStatus::Pending,
            'hacienda_status'              => HaciendaStatus::Pending,
            'sell_condition'               => $data['CondicionVenta'] ?? null,
            'total_amount'                 => $data['ResumenFactura']['TotalVenta'] ?? 0,
            'tax_amount'                   => $data['ResumenFactura']['TotalImpuesto'] ?? 0,
            'total_discount'               => $data['ResumenFactura']['TotalDescuentos'] ?? 0,
            'total_voucher'                => $data['ResumenFactura']['TotalComprobante'] ?? 0,
            'currency'                     => $data['ResumenFactura']['CodigoTipoMoneda']['CodigoMoneda'] ?? 'CRC',
            'exchange_rate'                => $data['ResumenFactura']['CodigoTipoMoneda']['TipoCambio'] ?? null,
            'issuer_name'                  => $data['Emisor']['Nombre'] ?? config('invoicing-cr.invoicing.emisor.nombre'),
            'issuer_number'                => $emisorId,
            'issuer_identification_type'   => $emisorTipo,
            'receiver_name'                => $data['Receptor']['Nombre'] ?? null,
            'receiver_number'              => $data['Receptor']['Identificacion']['Numero'] ?? null,
            'receiver_identification_type' => $data['Receptor']['Identificacion']['Tipo'] ?? null,
        ]);

        ReceiptPayload::create([
            'receipt_id'   => $receipt->id,
            'receipt_type' => $receiptType,
            'payload'      => $data,
        ]);

        ReceiptCreated::dispatch($receipt);

        return [
            'receipt'   => $receipt,
            'data'      => $data,
            'uniqueKey' => $uniqueKey,
        ];
    }

    private function getNextConsecutive(ReceiptType $type, int $establishment, int $terminal): int
    {
        return DB::transaction(function () use ($type, $establishment, $terminal) {
            $filters = [
                'receipt_type'  => $type,
                'establishment' => $establishment,
                'terminal'      => $terminal,
            ];

            if ($companyId = config('invoicing-cr.invoicing.active_company_id')) {
                $filters['company_id'] = $companyId;
            }

            $record = ReceiptConsecutive::lockForUpdate()->firstOrCreate($filters, ['last_number' => 0]);

            $record->increment('last_number');
            $record->refresh();

            return $record->last_number;
        });
    }

    private function injectGeneratedData(array $data, string $clave, string $consecutiveKey, \DateTimeInterface $emissionDate): array
    {
        $data['Clave'] = $clave;
        $data['NumeroConsecutivo'] = $consecutiveKey;
        $data['FechaEmision'] = $data['FechaEmision'] ?? $emissionDate->format('Y-m-d\TH:i:sP');
        $data['ProveedorSistema'] = $data['ProveedorSistema'] ?? config('invoicing-cr.invoicing.proveedor_sistemas');
        $data['CondicionVenta'] = $data['CondicionVenta'] ?? $data['sell_condition'] ?? '01';
        $data['CodigoActividadEmisor'] = $data['CodigoActividadEmisor'] ?? explode(',', config('invoicing-cr.invoicing.emisor.actividad_economica', ''))[0];

        if (empty($data['Emisor'])) {
            $data['Emisor'] = [
                'Nombre'         => config('invoicing-cr.invoicing.emisor.nombre'),
                'Identificacion' => [
                    'Tipo'   => config('invoicing-cr.invoicing.emisor.tipo_cedula', '01'),
                    'Numero' => config('invoicing-cr.invoicing.emisor.cedula'),
                ],
                'NombreComercial' => config('invoicing-cr.invoicing.emisor.nombre_comercial'),
                'CorreoElectronico' => array_filter([config('invoicing-cr.invoicing.emisor.email')]),
            ];

            $ubicacion = config('invoicing-cr.invoicing.emisor.ubicacion');
            if (! empty($ubicacion['provincia'])) {
                $data['Emisor']['Ubicacion'] = [
                    'Provincia'  => $ubicacion['provincia'],
                    'Canton'     => $ubicacion['canton'],
                    'Distrito'   => $ubicacion['distrito'],
                    'OtrasSenas' => $ubicacion['otras_senas'],
                ];
            }

            if (config('invoicing-cr.invoicing.emisor.telefono')) {
                $data['Emisor']['Telefono'] = [
                    'CodigoPais'  => '506',
                    'NumTelefono' => config('invoicing-cr.invoicing.emisor.telefono'),
                ];
            }
        }

        if (empty($data['ResumenFactura']['TotalDesgloseImpuesto']) && ! empty($data['DetalleServicio']['LineaDetalle'])) {
            $data['ResumenFactura']['TotalDesgloseImpuesto'] = $this->buildTotalDesgloseImpuesto($data['DetalleServicio']['LineaDetalle']);
        }

        return $data;
    }

    private function buildTotalDesgloseImpuesto(array $lineas): array
    {
        $desglose = [];

        foreach ($lineas as $linea) {
            foreach ($linea['Impuesto'] ?? [] as $impuesto) {
                $codigo = $impuesto['Codigo'] ?? '';
                $tarifaIVA = $impuesto['CodigoTarifaIVA'] ?? '';
                $key = $codigo.'-'.$tarifaIVA;

                if (! isset($desglose[$key])) {
                    $desglose[$key] = [
                        'Codigo' => $codigo,
                        'CodigoTarifaIVA' => $tarifaIVA ?: null,
                        'TotalMontoImpuesto' => '0',
                    ];
                }

                $neto = bcsub(
                    (string) ($impuesto['Monto'] ?? '0'),
                    (string) ($impuesto['Exoneracion']['MontoExoneracion'] ?? '0'),
                    5
                );

                $desglose[$key]['TotalMontoImpuesto'] = bcadd(
                    $desglose[$key]['TotalMontoImpuesto'],
                    $neto,
                    5
                );
            }
        }

        foreach ($desglose as &$item) {
            if ($item['CodigoTarifaIVA'] === null) {
                unset($item['CodigoTarifaIVA']);
            }
        }

        return array_values($desglose);
    }
}
