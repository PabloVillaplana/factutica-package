# Investigacion: ComprobantesElectronicosCR.com (Cyberfuel S.A.)

> Analisis del servicio de facturacion electronica de Cyberfuel para identificar features adoptables.

**Fecha:** 5 de abril de 2026

### URLs de referencia

| Pagina | URL |
|---|---|
| Sitio principal | https://www.comprobanteselectronicoscr.com |
| Documentacion API | https://www.comprobanteselectronicoscr.com/doc-api.html |
| Planes | https://www.comprobanteselectronicoscr.com/planes.html |
| Portal admin | https://www.comprobanteselectronicoscr.com/api/admin.php |
| AutoFactura / Consulta publica | https://www.comprobanteselectronicoscr.com/consultar-documentos-autoFactura.html |
| FacturaProfesional (producto GUI) | https://www.facturaprofesional.com |

---

## Sobre Cyberfuel

Empresa costarricense con 29+ anos de experiencia. Ubicada en Forum 1, Santa Ana. 4.8/5 en Google (33 reviews). Miembro certificado de GS1 Costa Rica. Data center propio con certificacion ISO ANSI/TIA-942-B Rated 3.

Operan dos productos:
- **ComprobantesElectronicosCR** — API para desarrolladores/integradores
- **FacturaProfesional.com** — portal web para pymes y profesionales

---

## API

### Autenticacion

API key en el body JSON (`api_key`). No usa OAuth2 ni Bearer token.

### URL pattern

```
/api/{metodo}.{ambiente}.{version}
```

Ambientes: `stag` (sandbox), `prod` (produccion). Version: `.42` (estructura DGT v4.2, soporta v4.4).

### Endpoints principales

| Endpoint | Proposito |
|---|---|
| `makeXML` | Recibe JSON → genera XML → firma → envia a Hacienda |
| `sendXML` | Recibe XML pre-construido (base64) → firma → envia |
| `consultahacienda` | Consultar estado de documento en Hacienda |
| `acceptbounce` | Aceptar/rechazar factura recibida (CA/CAP/CR) |
| `consultadocumento` | Obtener XML firmado por clave numerica |

### Endpoints de integrador (multi-tenant)

| Endpoint | Proposito |
|---|---|
| `client.php?action=add_client` | Registrar nuevo contribuyente |
| `client.php?action=update_crt` | Subir/actualizar llave criptografica (.p12) |
| `client.php?action=update_user` | Configurar credenciales ATV + callback URL |
| `client.php?action=update_data` | Actualizar nombre/email del cliente |
| `client.php?action=inactive_client` | Desactivar cliente |

Cada cliente bajo un integrador recibe su propio `api_key`. La callback URL soporta un placeholder `@@api_key@@` para rutear respuestas al cliente correcto.

### Tipos de documento soportados

- Factura Electronica (FE)
- Factura de Compra (FEC)
- Factura de Exportacion (FEE)
- Tiquete Electronico (TE)
- Nota de Credito (NC)
- Nota de Debito (ND)
- Mensajes de aceptacion/rechazo (CA/CAP/CR)

### Estructura del JSON (makeXML)

```json
{
    "api_key": "...",
    "referencia_externa": "GVT255915",
    "clave": { "sucursal", "terminal", "tipo", "comprobante", "codigo_seguridad" },
    "encabezado": { "fecha", "condicion_venta", "plazo_credito" },
    "emisor": { "nombre", "identificacion", "ubicacion", "telefono", "correo" },
    "receptor": { "nombre", "identificacion", "correo" },
    "detalle": [{ "codigo", "cantidad", "precio_unitario", "impuestos", ... }],
    "resumen": { "moneda", "totales", "desglose_impuesto", "medio_pago" },
    "walmart": { "vendedor", "orden", "gln", "fechaorden", "recepcion" },
    "otros": [{ "codigo", "texto", "contenido" }],
    "envio": { "aplica", "logo", "texto", "emisor.correo", "receptor.correo" }
}
```

---

## Modelo de negocio

### Planes

- **Cliente Final** — una empresa que necesita facturar via API
- **Integrador** — multiples contribuyentes bajo una cuenta (reseller/multi-tenant)

Precios no publicos. Requiere contacto con ventas.

### Onboarding

No hay self-service. Flujo:
1. Formulario de contacto en `/planes.html`
2. Ventas contacta con pricing
3. Cuenta creada en portal admin
4. Cliente sube llave criptografica y credenciales
5. Testing en `stag` → produccion en `prod`

Para integradores: pueden crear sub-clientes programaticamente via `add_client`.

---

## Features destacables

### PDF integrado

Genera PDF automaticamente al emitir el comprobante. El PDF incluye logo de la empresa. Se puede descargar via consulta de documento o se envia por correo.

### Envio por correo

Integrado en el request via bloque `envio`:

```json
"envio": {
    "aplica": "1",
    "logo": "data:image/jpg;base64,...",
    "texto": "UGFxdWV0ZSAtIEdWVDI1NTkxNQ==",
    "emisor": { "correo": "facturacion@empresa.cr" },
    "receptor": { "correo": "cliente@email.com" }
}
```

Cuando `aplica` es `"1"`, envia XML + PDF a emisor y receptor.

### AutoFactura

Pagina publica donde un consumidor final puede convertir un tiquete electronico en factura electronica. Solo funciona si la empresa emisora lo tiene habilitado y dentro del plazo establecido.

URL: `/consultar-documentos-autoFactura.html`

El consumidor ingresa la clave numerica (50 digitos) y puede:
- Ver el documento
- Descargar XML
- Descargar PDF
- Solicitar conversion a factura (AutoFactura)

### sendXML (XML pre-construido)

Para clientes que ya generan su propio XML, Cyberfuel ofrece un endpoint que solo firma y envia:

```
POST /api/sendXML.prod.42
{
    "api_key": "...",
    "xml": "base64_encoded_xml"
}
```

Util para migracion desde otros sistemas o clientes con generadores XML propios.

### Integracion Walmart CR

Campos dedicados para proveedores de Walmart Costa Rica:

```json
"walmart": {
    "vendedor": "...",
    "orden": "...",
    "gln": "...",
    "fechaorden": "...",
    "recepcion": "..."
}
```

Indica que Walmart CR es un cliente importante o que muchos de sus clientes son proveedores de Walmart.

### Callback con placeholder

La callback URL soporta `@@api_key@@` como token, permitiendo al integrador rutear respuestas de Hacienda al sub-cliente correcto:

```
https://mi-erp.com/webhook?client=@@api_key@@
```

---

## Stack tecnologico

| Componente | Tecnologia |
|---|---|
| Backend | PHP (endpoints .php) |
| Firma digital | XAdES (versiones en `/xades/` y `/xades_v16-0-306/`) |
| Frontend | jQuery 2.0/3.1, jQuery Mobile, Bootstrap, Font Awesome |
| Seguridad | reCAPTCHA, CAPTCHA custom en login, SSL |
| Infra | Data center propio (no cloud) |
| Email | Sistema de correo propio (`/mail/`) |
| CDN | jsdelivr para assets frontend |

---

## Que podemos adoptar

### Para el paquete MVP (corto plazo)

| Feature | Prioridad | Como implementar |
|---|---|---|
| **PDF** | Alta | Evento `ReceiptAccepted` → la app host genera PDF con Blade/DOMPDF. O agregar al paquete como feature opcional. |
| **Envio por correo** | Media | Evento `ReceiptAccepted` → la app host envia Mailable. O agregar al paquete como feature opcional. |

El paquete ya tiene los eventos (`ReceiptAccepted`, `ReceiptSent`). La app host puede implementar PDF y correo escuchando estos eventos sin que el paquete cambie.

### Para el paquete multi-tenant (mediano plazo)

| Feature | Prioridad | Como implementar |
|---|---|---|
| **Multi-tenant API** | Alta | Ya planeado en `docs/design/service-architecture.md` |
| **Callback con placeholder** | Media | Soportar variables en callback URL del tenant |
| **Gestion de certificados** | Alta | Ya planeado: `tenant_certificates` table |

### Para el servicio SaaS (largo plazo)

| Feature | Prioridad | Como implementar |
|---|---|---|
| **AutoFactura** | Media | Pagina publica: clave → ver/descargar PDF/XML |
| **sendXML** | Baja | Endpoint que acepta XML pre-construido, solo firma y envia |
| **Portal admin** | Alta | Ya planeado en Fase 3 del roadmap |
| **Consulta publica** | Media | Pagina para buscar documento por clave |

---

## Que hacemos mejor que Cyberfuel

| Aspecto | Nuestro paquete | Cyberfuel |
|---|---|---|
| Validacion pre-envio | 5 validators, ~1400 lineas de logica matematica | No tiene — errores se descubren cuando Hacienda rechaza |
| Onboarding | `composer require` + `.env` | Formulario → vendedor → setup manual |
| Costo | Sin costo (self-hosted) | Pricing no publico, sales-driven |
| Independencia | Directo a Hacienda, sin intermediario | Dependencia total de Cyberfuel |
| Fidelidad XSD | 1:1 con campos del XSD v4.4 | Traduce nombres internamente |
| Testing | 242 tests, 506 assertions | Desconocido |
| API naming | snake_case consistente | Mezcla snake_case con PascalCase |
| Firma digital | En el paquete (XAdES-EPES) | En su servidor |
| Tech stack | Laravel moderno, PHP 8.2 | PHP legacy, jQuery 2.0 |
| Documentacion API | Postman collection + markdown + ejemplos | HTML estatico con ejemplos en VB.NET y Java |

---

## Conclusion

Cyberfuel es un servicio maduro con buena cobertura funcional (PDF, correo, multi-tenant, AutoFactura). Su principal ventaja es que resuelve todo el flujo de negocio (facturar + PDF + enviar correo) en un solo request.

Nuestro paquete es tecnicamente superior en validacion, seguridad, y arquitectura. Las features de negocio que nos faltan (PDF, correo) se pueden implementar por la app host usando los eventos del paquete, o agregarse como features opcionales en futuras versiones.

El modelo de Cyberfuel como intermediario (dependencia total, costo por factura, pricing opaco) es exactamente lo que nuestro paquete evita al ir directo a Hacienda.
