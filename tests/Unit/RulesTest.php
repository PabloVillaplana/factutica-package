<?php

use Illuminate\Support\Facades\Validator;
use FactuTica\FactuticaCR\Rules\DecimalDinero;
use FactuTica\FactuticaCR\Rules\ServiceDetailRequired;
use FactuTica\FactuticaCR\Rules\ValidateIdentification;
// ── DecimalDinero ──────────────────────────────────────────────────

describe('DecimalDinero', function () {

    it('accepts valid values', function (mixed $value) {
        $validator = Validator::make(['field' => $value], ['field' => [new DecimalDinero]]);

        expect($validator->passes())->toBeTrue();
    })->with([
        '0'                  => [0],
        '100'                => [100],
        '13 digits'          => [1234567890123],
        '5 decimals'         => ['100.12345'],
        'small decimal'      => ['0.00001'],
        'negative'           => ['-100.50'],
    ]);

    it('rejects invalid values', function (mixed $value) {
        $validator = Validator::make(['field' => $value], ['field' => [new DecimalDinero]]);

        expect($validator->fails())->toBeTrue();
    })->with([
        '14 digits'     => ['12345678901234'],
        '6 decimals'    => ['100.123456'],
        'alpha string'  => ['abc'],
    ]);

    it('skips null and empty string', function (mixed $value) {
        $validator = Validator::make(['field' => $value], ['field' => [new DecimalDinero]]);

        expect($validator->passes())->toBeTrue();
    })->with([
        'null'         => [null],
        'empty string' => [''],
    ]);
});

// ── ValidateIdentification ─────────────────────────────────────────

describe('ValidateIdentification', function () {

    // Tipo 01 - Física
    it('accepts valid cedula fisica', function () {
        $validator = Validator::make(
            ['numero' => '123456789', 'tipo' => '01'],
            ['numero' => [new ValidateIdentification('tipo')]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('rejects cedula fisica with leading zero', function () {
        $validator = Validator::make(
            ['numero' => '023456789', 'tipo' => '01'],
            ['numero' => [new ValidateIdentification('tipo')]]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('rejects cedula fisica with 8 digits', function () {
        $validator = Validator::make(
            ['numero' => '12345678', 'tipo' => '01'],
            ['numero' => [new ValidateIdentification('tipo')]]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('rejects cedula fisica with 10 digits', function () {
        $validator = Validator::make(
            ['numero' => '1234567890', 'tipo' => '01'],
            ['numero' => [new ValidateIdentification('tipo')]]
        );

        expect($validator->fails())->toBeTrue();
    });

    // Tipo 02 - Jurídica
    it('accepts valid cedula juridica', function () {
        $validator = Validator::make(
            ['numero' => '3101234567', 'tipo' => '02'],
            ['numero' => [new ValidateIdentification('tipo')]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('rejects cedula juridica with 9 digits', function () {
        $validator = Validator::make(
            ['numero' => '123456789', 'tipo' => '02'],
            ['numero' => [new ValidateIdentification('tipo')]]
        );

        expect($validator->fails())->toBeTrue();
    });

    // Tipo 03 - DIMEX
    it('accepts valid DIMEX with 11 digits', function () {
        $validator = Validator::make(
            ['numero' => '12345678901', 'tipo' => '03'],
            ['numero' => [new ValidateIdentification('tipo')]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('accepts valid DIMEX with 12 digits', function () {
        $validator = Validator::make(
            ['numero' => '123456789012', 'tipo' => '03'],
            ['numero' => [new ValidateIdentification('tipo')]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('rejects DIMEX with leading zero', function () {
        $validator = Validator::make(
            ['numero' => '0123456789012', 'tipo' => '03'],
            ['numero' => [new ValidateIdentification('tipo')]]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('rejects DIMEX with 10 digits', function () {
        $validator = Validator::make(
            ['numero' => '1234567890', 'tipo' => '03'],
            ['numero' => [new ValidateIdentification('tipo')]]
        );

        expect($validator->fails())->toBeTrue();
    });

    // Tipo 04 - NITE
    it('accepts valid NITE', function () {
        $validator = Validator::make(
            ['numero' => '1234567890', 'tipo' => '04'],
            ['numero' => [new ValidateIdentification('tipo')]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('rejects NITE with 9 digits', function () {
        $validator = Validator::make(
            ['numero' => '123456789', 'tipo' => '04'],
            ['numero' => [new ValidateIdentification('tipo')]]
        );

        expect($validator->fails())->toBeTrue();
    });

    // Tipo 05/06 - Extranjero / No contribuyente
    it('accepts valid extranjero identification', function (string $tipo) {
        $validator = Validator::make(
            ['numero' => 'ABC-123', 'tipo' => $tipo],
            ['numero' => [new ValidateIdentification('tipo')]]
        );

        expect($validator->passes())->toBeTrue();
    })->with(['05', '06']);

    it('rejects extranjero identification over 20 chars', function (string $tipo) {
        $validator = Validator::make(
            ['numero' => 'This string is way too long for val', 'tipo' => $tipo],
            ['numero' => [new ValidateIdentification('tipo')]]
        );

        expect($validator->fails())->toBeTrue();
    })->with(['05', '06']);

    // Missing type skips validation
    it('skips validation when type is missing', function () {
        $validator = Validator::make(
            ['numero' => '123'],
            ['numero' => [new ValidateIdentification('tipo')]]
        );

        expect($validator->passes())->toBeTrue();
    });
});

// ── ServiceDetailRequired ──────────────────────────────────────────

describe('ServiceDetailRequired', function () {

    it('fails when DetalleServicio is empty and no OtrosCargos', function () {
        $data = [
            'DetalleServicio' => ['LineaDetalle' => []],
            'OtrosCargos' => [],
        ];

        $validator = Validator::make($data, ['DetalleServicio' => [new ServiceDetailRequired]]);

        expect($validator->fails())->toBeTrue();
    });

    it('passes when LineaDetalle has items', function () {
        $data = [
            'DetalleServicio' => [
                'LineaDetalle' => [
                    ['NumeroLinea' => 1, 'Cantidad' => 1, 'UnidadMedida' => 'Unid'],
                ],
            ],
            'OtrosCargos' => [],
        ];

        $validator = Validator::make($data, ['DetalleServicio' => [new ServiceDetailRequired]]);

        expect($validator->passes())->toBeTrue();
    });

    it('passes when empty DetalleServicio but OtrosCargos type 04', function () {
        $data = [
            'DetalleServicio' => ['LineaDetalle' => []],
            'OtrosCargos' => [
                ['TipoDocumentoOC' => '04', 'Detalle' => 'Cargo'],
            ],
        ];

        $validator = Validator::make($data, ['DetalleServicio' => [new ServiceDetailRequired]]);

        expect($validator->passes())->toBeTrue();
    });

    it('passes for exempt OtrosCargos types', function (string $type) {
        $data = [
            'DetalleServicio' => ['LineaDetalle' => []],
            'OtrosCargos' => [
                ['TipoDocumentoOC' => $type, 'Detalle' => 'Cargo'],
            ],
        ];

        $validator = Validator::make($data, ['DetalleServicio' => [new ServiceDetailRequired]]);

        expect($validator->passes())->toBeTrue();
    })->with(['08', '09', '10']);

    it('fails when OtrosCargos type is not exempt', function () {
        $data = [
            'DetalleServicio' => ['LineaDetalle' => []],
            'OtrosCargos' => [
                ['TipoDocumentoOC' => '01', 'Detalle' => 'Cargo'],
            ],
        ];

        $validator = Validator::make($data, ['DetalleServicio' => [new ServiceDetailRequired]]);

        expect($validator->fails())->toBeTrue();
    });
});