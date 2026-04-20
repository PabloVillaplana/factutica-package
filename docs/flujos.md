# Flujos End-to-End — laravel-paquete-facturacion

---

## Flujo 1: Crear y Enviar Comprobante

`POST /invoicing-cr/receipts`

> **Arquitectura:** `InvoicingService` es un orquestador delgado que delega a `ReceiptBuilderService` (consecutivo, clave, persistencia, evento `ReceiptCreated`) y `XmlPipelineService` (XML, firma, envio, evento `ReceiptSent`). En modo async, despacha `SendReceiptToProviderJob` en vez de llamar al pipeline.

```mermaid
sequenceDiagram
    participant C as Cliente
    participant RC as ReceiptController
    participant VL as StoreReceiptRequest
    participant IS as InvoicingService
    participant CV as CalculationValidatorService
    participant RB as ReceiptBuilderService
    participant KG as KeyGeneratorService
    participant DB as Database
    participant XP as XmlPipelineService
    participant XG as XmlGeneratorService
    participant XS as XmlSignerService
    participant CL as CertificateLoaderService
    participant PF as ProviderFactoryService
    participant HP as HaciendaProvider
    participant IDP as HaciendaIdpService
    participant H as API Hacienda

    C->>RC: POST /invoicing-cr/receipts (snake_case)
    RC->>VL: validate(request)
    Note over VL: prepareForValidation() transforma<br/>snake_case → PascalCase via<br/>PayloadTransformerService.<br/>Luego: Base rules + ReceiptTypeRules<br/>por tipo (FE, TE, NC, ND, etc.)
    VL-->>RC: validated data (PascalCase)

    RC->>IS: createAndSend(validated('receipt_type'), validated())

    Note over IS: 1. Validar tipo
    IS->>IS: ReceiptType::tryFrom(type)
    alt Tipo invalido
        IS-->>RC: throw InvalidReceiptException
        RC-->>C: 422 Unprocessable Entity
    end

    Note over IS: 2. Validar calculos matematicos
    IS->>CV: validate(data)
    Note over CV: Pipeline de 5 sub-validadores:<br/>1. DetailLineValidator<br/>2. TaxCalculationValidator<br/>3. TaxBreakdownValidator<br/>4. AssortmentValidator<br/>5. InvoiceSummaryValidator
    Note over CV: Se ejecuta ANTES de consumir<br/>consecutivo o tocar la DB
    alt Calculos no cuadran
        CV-->>IS: throw InvalidReceiptException
        IS-->>RC: throw InvalidReceiptException
        RC-->>C: 422 Unprocessable Entity
    end
    CV-->>IS: OK

    Note over IS: 3. Construir receipt (delega a ReceiptBuilderService)
    IS->>RB: build(receiptType, data)

    Note over RB: 3a. Consecutivo (con lockForUpdate)
    RB->>DB: ReceiptConsecutive::lockForUpdate()->firstOrCreate(<br/>type, establishment, terminal)
    DB-->>RB: record
    RB->>DB: increment('last_number')
    DB-->>RB: consecutive (ej: 42)

    Note over RB: 3b. Claves
    RB->>KG: generateConsecutiveKey(type, 42, establishment, terminal)
    KG-->>RB: "00100001010000000042" (20 digitos)
    RB->>KG: generateUniqueKey(type, emisorId, idType,<br/>consecutive, emissionDate, establishment, terminal)
    Note over KG: 506 + dd/mm/yy + emisorId(12)<br/>+ consecutiveKey(20) + situacion(1)<br/>+ seguridad(8) = 50 digitos
    KG-->>RB: clave 50 digitos

    Note over RB: 3c. Inyectar datos
    RB->>RB: injectGeneratedData()
    Note over RB: Clave, NumeroConsecutivo,<br/>FechaEmision, ProveedorSistema,<br/>CondicionVenta, CodigoActividadEmisor,<br/>Emisor (desde config si vacio),<br/>TotalDesgloseImpuesto (auto-calculado)

    Note over RB: 3d. Persistir
    RB->>DB: Receipt::create(status=pending)
    DB-->>RB: receipt
    RB->>DB: ReceiptPayload::create(payload JSON)

    Note over RB: 3e. Evento
    RB->>RB: ReceiptCreated::dispatch(receipt)

    RB-->>IS: {receipt, data, uniqueKey}

    Note over IS: 4. Enviar a Hacienda via XmlPipelineService
    IS->>XP: generateSignAndSend(receipt, receiptType, data)

    Note over XP: 4a. Generar XML v4.4
    XP->>XG: generate(ReceiptType, data)
    Note over XG: Root element segun tipo:<br/>FE->FacturaElectronica<br/>TE->TiqueteElectronico<br/>NC->NotaCreditoElectronica<br/>etc.
    XG-->>XP: XML string

    Note over XP: 4b. Firmar XAdES-EPES
    XP->>XS: sign(xml)
    XS->>CL: load()
    Note over CL: Leer .p12 -> openssl_pkcs12_read<br/>Extraer: private key, cert, chain<br/>Calcular: base64, SHA-1, SHA-256<br/>Validar expiracion
    CL-->>XS: cert loaded
    Note over XS: Canonicalizar documento (exc-c14n)<br/>Calcular DigestValue (SHA-256)<br/>Construir SignedProperties (XAdES)<br/>Construir SignedInfo (2 references)<br/>Firmar con RSA-SHA256<br/>Ensamblar ds:Signature<br/>Insertar antes del tag de cierre
    XS-->>XP: signed XML

    Note over XP: 4c. Construir payload y enviar
    XP->>XP: buildHaciendaPayload(data, signedXml)
    Note over XP: {clave, fecha, emisor,<br/>receptor?, comprobanteXml(base64),<br/>callbackUrl}
    XP->>PF: make()
    PF-->>XP: provider
    XP->>HP: send(receipt, payload)
    HP->>IDP: getAccessToken()
    Note over IDP: Cache -> memory -> Laravel Cache<br/>Si expirado: refresh o authenticate<br/>OAuth2 password grant
    IDP-->>HP: Bearer token
    HP->>H: POST /recepcion (token + payload)
    H-->>HP: {clave, fecha, httpStatus}
    HP-->>XP: ProviderResponse

    Note over XP: 4d. Actualizar estado
    XP->>DB: receipt.markAsSent(uiKey, signedXml)
    Note over DB: receipt_status = sent<br/>sent_to_hacienda_at = now()

    Note over XP: 4e. Evento
    XP->>XP: ReceiptSent::dispatch(receipt, response)

    XP-->>IS: ProviderResponse

    IS-->>RC: {receipt, response}
    RC-->>C: 201 {mensaje, data: ReceiptResource}
```

> **Nota:** El `ReceiptController::store` captura `InvalidReceiptException` (lanzada tanto por tipo invalido como por validaciones de calculo) y retorna `422 Unprocessable Entity` con los detalles del error.

> **Modo async:** Si `config('invoicing-cr.invoicing.send_mode')` es `async`, el paso 4 (XmlPipelineService) no se ejecuta en el request. En su lugar, se despacha `SendReceiptToProviderJob` y el controller retorna `202 Accepted` inmediato con el receipt en estado `pending`. El Job ejecuta el paso 4 en background con 3 reintentos (30s, 60s, 90s). Ideal para POS/ERP donde no se puede bloquear el request.

---

## Flujo 2: Webhook de Hacienda (respuesta async)

`POST /invoicing-cr/webhook`

```mermaid
sequenceDiagram
    participant H as Hacienda
    participant WC as WebhookController
    participant WS as WebhookService
    participant WV as WebhookVerifierService
    participant DB as Database

    H->>WC: POST /invoicing-cr/webhook
    Note over WC: Body: {clave, ind-estado,<br/>respuesta-xml, fecha}

    WC->>WC: Validar clave (50 digitos, Constants::CLAVE_LENGTH)
    alt Clave invalida
        WC-->>H: 422 "Clave invalida"
    end

    WC->>WS: process(clave, indEstado, respuestaXml, fecha)

    Note over WS: 1. Buscar comprobante
    WS->>DB: Receipt::where('ui_key', clave)->first()
    alt No encontrado
        WS-->>WC: throw HaciendaException
        WC-->>H: 404
    end
    DB-->>WS: receipt

    Note over WS: 2. Verificar autenticidad
    WS->>WV: verify({clave, respuesta-xml}, receipt)
    Note over WV: Capa 1: Validar que clave == ui_key<br/>y emisorId embebido coincide<br/>Capa 2: Verificar firma XML<br/>del certificado de Hacienda<br/>(openssl_verify + cert subject)
    WV-->>WS: {valid: true/false, reason}
    alt Verificacion fallida
        WS-->>WC: throw HaciendaException
        WC-->>H: 404
    end

    Note over WS: 3. Idempotencia
    WS->>WS: receipt.hacienda_status != Pending?
    alt Ya procesado
        WS-->>WC: {receipt_id, ui_key, status} (duplicado ignorado)
        WC-->>H: 200 OK
    end

    Note over WS: 4. Procesar respuesta
    WS->>WS: mapStatus(indEstado)
    Note over WS: "aceptado" -> Accepted<br/>"rechazado" -> Rejected<br/>otro -> Pending
    WS->>WS: extractMessage(respuestaXml)
    Note over WS: base64_decode -> loadXML<br/>-> getElementsByTagName('DetalleMensaje')

    Note over WS: 5. Persistir (transaccion DB)
    WS->>DB: HaciendaResponse::updateOrCreate
    Note over DB: receipt_id, receipt_key,<br/>hacienda_status,<br/>response_xml, response_message,<br/>responded_at
    WS->>DB: receipt.update()
    Note over DB: hacienda_status = accepted/rejected<br/>receipt_status = accepted/rejected

    Note over WS: 6. Eventos (despues del commit)
    WS->>WS: receipt.refresh()
    alt Aceptado
        WS->>WS: ReceiptAccepted::dispatch(receipt, message)
    else Rechazado
        WS->>WS: ReceiptRejected::dispatch(receipt, message)
    end

    WS-->>WC: {receipt_id, ui_key, status}
    WC-->>H: 200 OK
```

---

## Flujo 3: Recepcion de Documento (MensajeReceptor)

`POST /invoicing-cr/reception` -> Job async

```mermaid
sequenceDiagram
    participant C as Cliente
    participant RC as ReceptionController
    participant VL as StoreReceptionRequest
    participant DB as Database
    participant Q as Queue
    participant JOB as SendSentReceiptToProviderJob
    participant MXG as MensajeReceptorXmlGeneratorService
    participant XS as XmlSignerService
    participant PF as ProviderFactoryService
    participant HP as HaciendaProvider
    participant H as API Hacienda

    C->>RC: POST /invoicing-cr/reception
    RC->>VL: validate(request)
    Note over VL: clave (50 chars), receipt_type,<br/>consecutive_number, emission_date,<br/>reception_status (accepted/partial/rejected),<br/>issuer_name, issuer_number,<br/>issuer_identification_type, etc.
    VL-->>RC: validated data

    Note over RC: 1. Resolver receptor (request o config)
    RC->>RC: receiver_name/number/type<br/>= data ?? config(emisor)
    alt Config emisor incompleta
        RC-->>C: throw InvalidReceiptException
    end

    Note over RC: 2. Crear registro
    RC->>DB: SentReceipt::create()
    Note over DB: receipt_status = pending<br/>hacienda_status = pending<br/>reception_status = (del request)<br/>issuer_* = del request<br/>receiver_* = resueltos arriba
    DB-->>RC: sentReceipt

    Note over RC: 3. Encolar job con data
    RC->>Q: dispatch(SendSentReceiptToProviderJob(sentReceipt, data))
    RC-->>C: 202 Accepted {id, tipo_comprobante, estado_comprobante}

    Note over Q: --- Procesamiento async ---

    Q->>JOB: handle(factory, xmlGenerator, signer)

    Note over JOB: 1. Mapear status
    JOB->>JOB: match reception_status
    Note over JOB: accepted -> Mensaje=1, tipo=05<br/>partially_accepted -> Mensaje=2, tipo=06<br/>rejected -> Mensaje=3, tipo=07

    Note over JOB: 2. Consecutivo receptor (con lockForUpdate)
    JOB->>DB: ReceiptConsecutive::lockForUpdate()->firstOrCreate(<br/>'MSG-05', establishment, terminal)
    JOB->>DB: increment('last_number')
    DB-->>JOB: consecutive (ej: 7)
    Note over JOB: establishment y terminal vienen<br/>de data o config(defaults)<br/>consecutiveKey =<br/>001 + 00001 + 05 + 0000000007

    Note over JOB: 3. Construir MensajeReceptor
    JOB->>JOB: construir mensajeData
    Note over JOB: Clave (del doc original),<br/>NumeroCedulaEmisor,<br/>FechaEmisionDoc,<br/>Mensaje (1/2/3),<br/>DetalleMensaje?, MontoTotalImpuesto?,<br/>CodigoActividad?, CondicionImpuesto?,<br/>MontoTotalImpuestoAcreditar?,<br/>MontoTotalDeGastoAplicable?,<br/>TotalFactura,<br/>NumeroCedulaReceptor (config),<br/>NumeroConsecutivoReceptor

    Note over JOB: 4. Generar XML
    JOB->>MXG: generate(mensajeData)
    Note over MXG: Root: MensajeReceptor<br/>NS: v4.4/mensajeReceptor
    MXG-->>JOB: XML string

    Note over JOB: 5. Firmar
    JOB->>XS: sign(xml)
    XS-->>JOB: signed XML

    Note over JOB: 6. Construir payload y enviar
    JOB->>JOB: buildHaciendaPayload
    Note over JOB: {clave, fecha, emisor,<br/>receptor (config),<br/>comprobanteXml(base64),<br/>callbackUrl, consecutivoReceptor}
    JOB->>PF: make()
    PF-->>JOB: provider
    JOB->>HP: send(sentReceipt, haciendaPayload)
    HP->>H: POST /recepcion
    H-->>HP: response
    HP-->>JOB: ProviderResponse

    Note over JOB: 7. Actualizar
    JOB->>DB: sentReceipt.markAsSent(uiKey, signedXml)
    JOB->>DB: sentReceipt.update(consecutive_number)

    alt Job falla (3 reintentos: 30s, 60s, 90s)
        Note over JOB: SendsToProvider trait
        JOB->>DB: sentReceipt.markAsFailed()
        Note over DB: receipt_status = failed
    end
```

---

## Flujo 4: Consultas (GET)

```mermaid
flowchart TD
    A["GET /receipts?type=FE&status=sent&per_page=25"] --> B[ReceiptController::index]
    B --> C["Receipt::with(payload, haciendaResponse)<br/>.filter(type, status)<br/>.latest().paginate(per_page)"]
    C --> D["ReceiptResource::collection<br/>+ meta (page, total)"]

    E["GET /receipts/{receipt}"] --> F[ReceiptController::show]
    F --> G["InvoicingService::getDocumentById<br/>Receipt::with(payload, haciendaResponse)<br/>.findOrFail(id)"]
    G --> H[ReceiptResource]

    I["GET /receipts/key/{uiKey}"] --> J[ReceiptController::showByKey]
    J --> K["InvoicingService::getDocumentByUiKey<br/>Receipt::with(payload, haciendaResponse)<br/>.where('ui_key').firstOrFail()"]
    K --> L[ReceiptResource]

    M["GET /receipts/key/{uiKey}/status"] --> N[ReceiptController::status]
    N --> O["getDocumentByUiKey(uiKey)"]
    N --> P["getDocumentStatus -> ProviderFactoryService<br/>.make() -> provider.getStatus(uiKey)"]
    P --> Q["GET /recepcion/{uiKey}<br/>(con Bearer token)"]
    Q -->|OK| R["200 {data: ReceiptStatusResource,<br/>hacienda: providerResponse}"]
    Q -->|Error| S["502 {data: ReceiptStatusResource,<br/>hacienda: null, error: message}"]
```

---

## Flujo 5: Autenticacion OAuth2 (IdP)

```mermaid
flowchart TD
    A[getAccessToken] --> B{tokenData<br/>en memoria?}
    B -->|No| C{tokenData<br/>en Cache?}
    B -->|Si| D{access token<br/>expirado?}

    C -->|No| E[authenticate]
    C -->|Si, valido| D

    D -->|No| Z[return token]
    D -->|Si| F{refresh token<br/>expirado?}

    F -->|No| G[refresh]
    F -->|Si| E

    E --> H["POST /token<br/>grant_type=password<br/>client_id, username, password"]
    H --> I[TokenData DTO]
    I --> J["Cache::put(key, tokenData,<br/>refreshExpiresAt - now - TOKEN_MARGIN_SECONDS)"]
    J --> Z

    G --> K["POST /token<br/>grant_type=refresh_token"]
    K -->|OK| I
    K -->|Error| E

    style Z fill:#2d8,stroke:#333
    style E fill:#f96,stroke:#333
    style G fill:#fc6,stroke:#333
```

**Cache key**: `invoicing_cr_idp_token_{ambiente}_{md5(username)}`

---

## Flujo 6: Firma XAdES-EPES

```mermaid
flowchart TD
    A[sign XML string] --> B[CertificateLoader::load]
    B --> B1["Leer .p12 -> openssl_pkcs12_read<br/>Extraer: privateKey, cert, chain<br/>Calcular: base64, SHA-1, SHA-256<br/>Validar expiracion"]

    B1 --> C["Cargar XML en DOMDocument"]
    C --> D["Generar IDs unicos:<br/>signatureId, xadesId,<br/>referenceId, valueId"]

    D --> E["Canonicalizar documento<br/>(exc-c14n, excluir ds:Signature)"]
    E --> F["DigestValue = SHA-256(canonical)"]

    F --> G["Construir SignedProperties"]
    G --> G1["SigningTime<br/>SigningCertificate (SHA-1 digest)<br/>IssuerSerial (DN + serial)<br/>SignaturePolicyIdentifier<br/>(URL + SHA-256 del PDF)<br/>DataObjectFormat"]

    G1 --> H["Canonicalizar SignedProperties<br/>DigestValue = SHA-256"]

    H --> I["Construir SignedInfo"]
    I --> I1["Reference 1: documento<br/>(XPath not ancestor ds:Signature)<br/>Reference 2: SignedProperties<br/>(URI=#xadesId)"]

    I1 --> J["Canonicalizar SignedInfo"]
    J --> K["openssl_sign(canonical, RSA-SHA256)"]
    K --> L["SignatureValue = base64(firma)"]

    L --> M["Ensamblar ds:Signature"]
    M --> M1["SignedInfo + SignatureValue<br/>+ KeyInfo (X509Certificate)<br/>+ Object (QualifyingProperties)"]

    M1 --> N["Insertar antes del<br/>tag de cierre del root"]
    N --> O[XML firmado]

    style O fill:#2d8,stroke:#333
```

---

## Flujo 7: Job Async — SendReceiptToProviderJob

> Activo cuando `INVOICING_CR_SEND_MODE=async`. El controller retorna 202 inmediato y el Job ejecuta XML, firma, envio en background con 3 reintentos (30s, 60s, 90s). Implementa `ShouldBeUnique` (uniqueId: `receipt-{id}`).

```mermaid
flowchart TD
    A[Job dequeued] --> B["receipt.payload.payload<br/>(datos ya inyectados por ReceiptBuilderService)"]
    B --> C["XmlPipelineService::generateSignAndSend<br/>(receipt, receipt_type, data)"]
    C --> C1["XmlGeneratorService::generate(type, data)"]
    C1 --> C2["XmlSignerService::sign(xml)"]
    C2 --> C3["buildHaciendaPayload(data, signedXml)<br/>{clave, fecha, emisor,<br/>comprobanteXml(base64),<br/>callbackUrl, receptor?}"]
    C3 --> C4["ProviderFactoryService::make()<br/>-> provider.send(receipt, payload)"]
    C4 --> C5["receipt.markAsSent(uiKey, signedXml)"]
    C5 --> C6["ReceiptSent::dispatch(receipt, response)"]

    C4 -->|Error| H["Retry: 30s, 60s, 90s"]
    H -->|3 intentos fallidos| I["SendsToProvider::failed()<br/>-> markAsFailed()"]

    style C6 fill:#2d8,stroke:#333
    style I fill:#f44,stroke:#333
```

---

## Flujo 8: Pipeline de Validacion de Calculos

El `CalculationValidatorService` se ejecuta como paso 2 del Flujo 1, **antes** de consumir consecutivo o persistir en base de datos. Esto garantiza que no se desperdician consecutivos en comprobantes con errores matematicos.

```mermaid
flowchart TD
    A["createAndSend(type, data)"] --> B{Tipo valido?}
    B -->|No| B1["throw InvalidReceiptException"]
    B -->|Si| C["CalculationValidatorService::validate(data)"]

    C --> D{DetalleServicio<br/>LineaDetalle no vacio?}
    D -->|Si| E["1. DetailLineValidator<br/>Calculos por linea:<br/>MontoTotal, SubTotal,<br/>BaseImponible, ImpuestoNeto"]
    D -->|No| H

    E --> F["2. TaxCalculationValidator<br/>Calculos de impuestos por codigo:<br/>IVA, selectivo, combustible, etc."]
    F --> F1["3. TaxBreakdownValidator<br/>Desglose de impuestos<br/>vs totales del resumen"]
    F1 --> F2["4. AssortmentValidator<br/>Validacion de surtidos<br/>y mercancias"]
    F2 --> H

    H{ResumenFactura<br/>no vacio?}
    H -->|Si| I["5. InvoiceSummaryValidator<br/>TotalVenta, TotalDescuentos,<br/>TotalImpuesto, TotalComprobante"]
    H -->|No| J

    I --> J[OK - continuar con consecutivo]

    E -->|Error| K["throw InvalidReceiptException<br/>(422 en ReceiptController)"]
    F -->|Error| K
    F1 -->|Error| K
    F2 -->|Error| K
    I -->|Error| K

    style J fill:#2d8,stroke:#333
    style K fill:#f44,stroke:#333
    style B1 fill:#f44,stroke:#333
```

---

## Resumen de Tablas y Estado

```mermaid
stateDiagram-v2
    [*] --> pending: Receipt creado<br/>(ReceiptCreated event)
    pending --> sent: markAsSent()<br/>(ReceiptSent event)
    pending --> failed: markAsFailed()<br/>(error en envio)

    sent --> accepted: Webhook recibido<br/>(ReceiptAccepted event)
    sent --> rejected: Webhook recibido<br/>(ReceiptRejected event)

    failed --> [*]: Requiere intervencion

    state "Tablas involucradas" as tables {
        receipts: invoicing_cr_receipts<br/>(receipt_status, hacienda_status)
        payloads: invoicing_cr_receipt_payloads<br/>(payload JSON original)
        responses: invoicing_cr_hacienda_responses<br/>(response_xml, response_message)
        consecutives: invoicing_cr_receipt_consecutives<br/>(last_number por tipo)
        sent: invoicing_cr_sent_receipts<br/>(mensajes de recepcion)
    }

    state "Validacion pre-persistencia" as validation {
        StoreReceiptRequest: Validacion de estructura<br/>(reglas Laravel + ReceiptTypeRules)
        CalculationValidatorService: Validacion matematica<br/>(5 sub-validadores)
        note: Ambas se ejecutan ANTES<br/>de consumir consecutivo
    }
```