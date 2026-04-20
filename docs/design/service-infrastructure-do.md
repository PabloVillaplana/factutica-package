# Invoicing Service — Infraestructura DigitalOcean

> Plan de infraestructura en DigitalOcean con crecimiento progresivo. Una sola plataforma, sin migraciones.

---

## Por que DigitalOcean desde el dia uno

- **Simplicidad** — interfaz clara, documentacion excelente, menos servicios pero bien hechos
- **Costo predecible** — precios fijos, sin sorpresas de NAT Gateway, data transfer, o hidden fees
- **App Platform** — PaaS nativo con deploy via `git push`, zero-downtime, auto-scaling
- **Managed services** — MySQL, Redis, y object storage sin administrar servidores
- **Soporte Laravel** — App Platform tiene buildpacks para PHP/Laravel out of the box
- **Escala suficiente** — DigitalOcean soporta empresas con miles de usuarios. No necesitas AWS para un servicio de facturacion

---

## Arquitectura

```
                         ┌───────────────────────┐
                         │   Cloudflare (DNS)     │
                         │   SSL + DDoS + WAF     │
                         │   Rate limiting         │
                         └───────────┬───────────┘
                                     │
                         ┌───────────▼───────────┐
                         │   DO Load Balancer     │
                         │   (o App Platform)     │
                         └───────────┬───────────┘
                                     │
                    ┌────────────────┼────────────────┐
                    │                                 │
           ┌────────▼────────┐              ┌────────▼────────┐
           │  App Server(s)  │              │  Worker          │
           │  Laravel API    │              │  Horizon         │
           └────────┬────────┘              └────────┬────────┘
                    │                                 │
                    └────────────────┬────────────────┘
                                     │
            ┌────────────────────────┼────────────────────────┐
            │                        │                        │
   ┌────────▼────────┐    ┌─────────▼────────┐    ┌─────────▼────────┐
   │  Managed MySQL  │    │  Managed Redis   │    │  Spaces (S3)     │
   │  (DB Cluster)   │    │  (Cache/Queue)   │    │  Backups, certs  │
   └─────────────────┘    └──────────────────┘    └──────────────────┘
```

**Cloudflare es clave.** DigitalOcean no tiene WAF ni secrets manager nativos. Cloudflare cubre WAF, DDoS, rate limiting, y SSL edge — gratis o muy barato.

---

## Dos caminos dentro de DigitalOcean

### Camino A: App Platform (PaaS)

Deploy con `git push`. DigitalOcean maneja containers, scaling, SSL, deploys.

**Ventajas:** Cero ops, zero-downtime deploys automaticos, logs integrados.
**Desventajas:** Menos control, no se puede SSH a los containers, pricing por container.

### Camino B: Droplets (IaaS)

Servidores virtuales que vos administras. Mas control, mas trabajo.

**Ventajas:** Control total, SSH, cron jobs nativos, mas barato a escala.
**Desventajas:** Vos manejas deploys, updates, seguridad del OS.

**Recomendacion:** Empezar con **App Platform** (rapido, sin ops) y migrar a **Droplets** solo si necesitas control que App Platform no da. La migracion dentro de DO es simple — misma DB, mismo Redis, mismo Spaces.

---

## Crecimiento progresivo

### Etapa 1 — MVP (App Platform)

helixERP + primeros clientes sandbox.

```
App Platform
├── API container (Basic, 1 vCPU, 512MB)  x2    ~$10/mes
├── Worker container (Basic, 1 vCPU, 512MB) x1   ~$5/mes
│
Managed MySQL (db-s-1vcpu-1gb, 10GB)              ~$15/mes
Managed Redis (db-s-1vcpu-1gb)                     ~$15/mes
Spaces (250GB incluidos)                           ~$5/mes
Cloudflare (free plan)                             $0
──────────────────────────────────────────────────
Total:                                            ~$50/mes
```

**Lo que NO necesitas todavia:** Load balancer propio (App Platform lo incluye), Droplets, backups extra, monitoreo avanzado.

### Etapa 2 — Produccion (App Platform Pro)

Clientes reales facturando. Mas recursos, alta disponibilidad en DB.

```
App Platform
├── API container (Pro, 1 vCPU, 1GB) x2           ~$24/mes
├── Worker container (Pro, 1 vCPU, 1GB) x1        ~$12/mes
│
Managed MySQL (db-s-1vcpu-2gb) + Standby node      ~$30/mes
Managed Redis (db-s-1vcpu-1gb)                      ~$15/mes
Spaces (250GB)                                      ~$5/mes
Cloudflare Pro (WAF avanzado)                       ~$20/mes
DO Uptime Monitoring                                $0
──────────────────────────────────────────────────
Total:                                             ~$106/mes
```

**Que cambio:** Containers Pro (mas CPU/RAM, zero-downtime deploy garantizado), standby node en MySQL (failover automatico), Cloudflare Pro (WAF rules, analytics avanzados).

### Etapa 3 — Escala (App Platform + Droplets hibrido)

Muchos clientes, portal web, billing.

```
App Platform
├── API container (Pro, 2 vCPU, 2GB) x3           ~$63/mes
├── Worker container (Pro, 2 vCPU, 2GB) x2        ~$42/mes
├── Portal container (Pro, 1 vCPU, 1GB) x2        ~$24/mes
│
Managed MySQL (db-s-2vcpu-4gb) + Standby            ~$60/mes
Managed Redis (db-s-2vcpu-4gb)                       ~$40/mes
Spaces (1TB)                                         ~$5/mes
Cloudflare Pro                                       ~$20/mes
DO Uptime Monitoring                                 $0
──────────────────────────────────────────────────
Total:                                              ~$254/mes
```

**Que cambio:** Mas replicas de API y workers, DB mas grande, Redis mas grande, portal como servicio separado.

### Resumen

```
Etapa 1 (MVP)        ~$50/mes    ← empezas aqui
     │                              solo cambias plan de containers
     ▼
Etapa 2 (Prod)       ~$106/mes   ← Pro containers, standby DB, Cloudflare Pro
     │                              misma arquitectura
     ▼
Etapa 3 (Escala)     ~$254/mes   ← mas replicas, portal, DB grande
                                    misma arquitectura
```

**Cero migraciones.** Mismo App Platform, misma DB, mismo Redis. Solo crece.

---

## Detalle de servicios

### App Platform (Compute)

App Platform detecta Laravel automaticamente y configura PHP-FPM + Nginx.

**app.yaml:**

```yaml
name: invoicing-service
region: nyc
services:
  - name: api
    github:
      repo: PabloVillaplana/invoicing-service
      branch: main
      deploy_on_push: true
    build_command: |
      composer install --no-dev --optimize-autoloader
      php artisan config:cache
      php artisan route:cache
    run_command: heroku-php-nginx -C docker/nginx.conf public/
    instance_count: 2
    instance_size_slug: professional-xs
    http_port: 8080
    health_check:
      http_path: /health
    envs:
      - key: APP_ENV
        value: production
      - key: DB_CONNECTION
        value: mysql
      - key: DATABASE_URL
        scope: RUN_TIME
        value: ${db.DATABASE_URL}
      - key: REDIS_URL
        scope: RUN_TIME
        value: ${redis.REDIS_URL}

workers:
  - name: horizon
    github:
      repo: PabloVillaplana/invoicing-service
      branch: main
      deploy_on_push: true
    build_command: composer install --no-dev --optimize-autoloader
    run_command: php artisan horizon
    instance_count: 1
    instance_size_slug: professional-xs
    envs:
      - key: APP_ENV
        value: production
      - key: DATABASE_URL
        scope: RUN_TIME
        value: ${db.DATABASE_URL}
      - key: REDIS_URL
        scope: RUN_TIME
        value: ${redis.REDIS_URL}

databases:
  - name: db
    engine: MYSQL
    version: "8"
    size: db-s-1vcpu-2gb
    num_nodes: 2

  - name: redis
    engine: REDIS
    version: "7"
    size: db-s-1vcpu-1gb
    num_nodes: 1
```

**Deploy:** `git push origin main` → App Platform detecta cambio → build → deploy con zero-downtime.

**Escalar:** Cambiar `instance_count` o `instance_size_slug` en el dashboard o CLI.

### Managed MySQL

| Config | Etapa 1 | Etapa 2 | Etapa 3 |
|---|---|---|---|
| Plan | db-s-1vcpu-1gb | db-s-1vcpu-2gb | db-s-2vcpu-4gb |
| RAM | 1 GB | 2 GB | 4 GB |
| Storage | 10 GB | 25 GB | 50 GB |
| Standby | No | Si (+1 nodo) | Si (+1 nodo) |
| Backups | Diario, 7 dias | Diario, 7 dias | Diario, 7 dias |
| Encryption | At rest (incluido) | At rest (incluido) | At rest (incluido) |

Conexion solo desde la VPC de DO (trusted sources). Sin acceso publico.

### Managed Redis

Uso:
- **Cache:** Tokens OAuth2 de Hacienda por tenant (`invoicing_token_{tenant_id}_{ambiente}`)
- **Queue:** Laravel Horizon para jobs (envio a Hacienda, webhooks salientes)
- **Rate limiting:** Counters por API key (complementa Cloudflare)

| Config | Etapa 1-2 | Etapa 3 |
|---|---|---|
| Plan | db-s-1vcpu-1gb | db-s-2vcpu-4gb |
| RAM | 1 GB | 4 GB |
| Eviction | noeviction | noeviction |

### Spaces (Object Storage, S3-compatible)

| Contenido | Proposito |
|---|---|
| DB dumps semanales | Backup extra (ademas del automatico de Managed MySQL) |
| Access logs | Retencion 90 dias |
| Assets del portal | Estaticos (Fase 3) |

Los certificados .p12 **no van en Spaces** — van encriptados en MySQL.

Spaces es compatible con S3 API — si algun dia migramos, las herramientas funcionan igual.

---

## Cloudflare (capa de seguridad)

Cloudflare es **esencial** en la arquitectura DO porque cubre lo que DO no tiene nativo.

### Free plan (Etapa 1)

| Feature | Uso |
|---|---|
| DNS | Nameservers autoritativos |
| SSL | Edge SSL (Full Strict mode) |
| DDoS | Proteccion L3/L4 automatica |
| Rate limiting | 1 regla gratis (ej: 100 req/min por IP en `/receipts`) |
| Page rules | Cache de assets estaticos |
| Analytics | Trafico basico |

### Pro plan — $20/mes (Etapa 2+)

| Feature | Uso |
|---|---|
| WAF | Reglas managed (SQLi, XSS, etc.) |
| Rate limiting | Reglas avanzadas (por path, por header, por API key) |
| Bot protection | Bloquear scrapers y bots |
| Analytics avanzado | Requests por pais, status codes, cache hit rate |
| 5 Page rules | Cache granular |

### Configuracion recomendada

```
Reglas de Rate Limiting:
  POST /receipts    → 60 req/min por API key (X-Api-Key header)
  POST /register    → 5 req/hora por IP
  GET  /receipts    → 120 req/min por API key
  POST /certificates → 10 req/hora por API key

Reglas de WAF:
  Bloquear SQL injection patterns
  Bloquear XSS patterns
  Bloquear body > 2MB (excepto /certificates que acepta .p12)

Reglas de Cache:
  /health           → bypass (siempre al origin)
  /webhook          → bypass (siempre al origin)
  Todo lo demas     → no cache (API dinamica)
```

---

## Gestion de secrets

DigitalOcean no tiene un Secrets Manager nativo como AWS. Alternativas:

### Opcion 1: App Platform Environment Variables (Etapa 1)

App Platform tiene variables de entorno encriptadas at rest. Suficiente para empezar.

```
APP_KEY=base64:...
DB_PASSWORD=...
MASTER_ENCRYPTION_KEY=...    ← para encriptar certs y creds en DB
```

**Limitacion:** No hay rotacion automatica, no hay versionado, no hay audit log de acceso.

### Opcion 2: HashiCorp Vault en Droplet (Etapa 2+)

Si la gestion de secrets se vuelve critica:

```
Droplet pequeno ($6/mes) con Vault
  → Almacena master encryption key
  → Almacena DB credentials
  → Rotacion automatica
  → Audit log de acceso
```

### Opcion 3: 1Password Connect (Etapa 2+)

Si ya usan 1Password:

```
1Password Connect server ($0 con plan Business)
  → API para leer secrets
  → Audit log incluido
  → Integracion con CI/CD
```

### Recomendacion

**Etapa 1:** App Platform env vars. Simple, funcional.
**Etapa 2+:** Evaluar Vault o 1Password segun necesidad. La master key para certificados es el secret mas critico.

---

## Monitoreo y alertas

### DO Uptime Monitoring (gratis)

| Check | URL | Intervalo | Alerta |
|---|---|---|---|
| API health | `https://api.facturacion.cr/health` | 1 min | Email + Slack |
| Webhook | `https://api.facturacion.cr/webhook` (HEAD) | 5 min | Email + Slack |

### App Platform Metrics (incluido)

- CPU y RAM por container
- Request count y latency
- Error rate (4xx, 5xx)
- Bandwidth

### Laravel Telescope (Etapa 1-2)

Instalado en el servicio para debug y monitoreo de la app:

- Requests entrantes con payload y response
- Jobs ejecutados (exitos y fallos)
- Queries SQL lentas
- Exceptions
- Logs

**Limitar en produccion:** Solo queries lentas, exceptions, y jobs fallidos. No guardar todo.

### Metricas custom via logs (Etapa 2+)

Enviar metricas a un servicio externo (Betterstack, Datadog free tier, o Grafana Cloud free):

```
invoicing.receipts.created      tenant_id=xxx
invoicing.receipts.sent         tenant_id=xxx
invoicing.receipts.accepted     tenant_id=xxx
invoicing.receipts.rejected     tenant_id=xxx
invoicing.webhook.delivered     tenant_id=xxx
invoicing.webhook.failed        tenant_id=xxx
invoicing.hacienda.latency_ms   value=1250
```

### Alertas

| Alerta | Condicion | Canal |
|---|---|---|
| Servicio caido | Uptime check falla 2 veces | Email + Slack |
| API errors | 5xx > 5% en 5 min | Slack |
| Queue saturada | Horizon pending > 200 | Slack |
| Hacienda lento | Latencia > 30s | Slack |
| Certificado expirando | < 30 dias | Email al tenant via SES o SMTP |
| DB storage > 80% | Monitoring alert | Email |

---

## CI/CD con GitHub Actions

```yaml
# .github/workflows/deploy.yml
name: Deploy to DigitalOcean

on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: testing
          MYSQL_ROOT_PASSWORD: password
        ports: ['3306:3306']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: dom, curl, openssl, mbstring, pdo_mysql, bcmath, xml
      - run: composer install
      - run: vendor/bin/pest --exclude-group=integration

  deploy:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - name: Trigger App Platform deploy
        uses: digitalocean/app_action/deploy@v2
        with:
          token: ${{ secrets.DIGITALOCEAN_ACCESS_TOKEN }}
          app_name: invoicing-service
```

**Alternativa:** App Platform detecta el push a `main` automaticamente si se conecta el repo. GitHub Actions solo agrega el paso de testing antes del deploy.

Costo: $0.

---

## Seguridad de red

```
Internet → Cloudflare (proxy, WAF, DDoS, SSL)
  → DO Load Balancer / App Platform (443 only)
    → App containers (VPC privada)
      → MySQL (VPC privada, trusted sources only)
      → Redis (VPC privada, trusted sources only)
```

### Configuracion

- **Cloudflare proxy:** Todo el trafico pasa por Cloudflare. El IP real de DO nunca se expone.
- **DO Firewall:** Solo permite trafico de IPs de Cloudflare al Load Balancer (si se usan Droplets).
- **MySQL:** Solo acepta conexiones de la VPC privada de DO. Sin acceso publico.
- **Redis:** Solo acepta conexiones de la VPC privada. Sin acceso publico.
- **Spaces:** Acceso via service credentials, no publico.
- **App Platform:** HTTPS forzado, HTTP redirige a HTTPS.

### Restriccion de IP de Cloudflare (Droplets)

Si se usan Droplets con Load Balancer, configurar DO Firewall para solo aceptar IPs de Cloudflare:

```
Inbound rules:
  443/tcp from 173.245.48.0/20    (Cloudflare)
  443/tcp from 103.21.244.0/22    (Cloudflare)
  443/tcp from 103.22.200.0/22    (Cloudflare)
  443/tcp from 103.31.4.0/22      (Cloudflare)
  443/tcp from 141.101.64.0/18    (Cloudflare)
  443/tcp from 108.162.192.0/18   (Cloudflare)
  443/tcp from 190.93.240.0/20    (Cloudflare)
  443/tcp from 188.114.96.0/20    (Cloudflare)
  443/tcp from 197.234.240.0/22   (Cloudflare)
  443/tcp from 198.41.128.0/17    (Cloudflare)
  443/tcp from 162.158.0.0/15     (Cloudflare)
  443/tcp from 104.16.0.0/13      (Cloudflare)
  443/tcp from 104.24.0.0/14      (Cloudflare)
  443/tcp from 172.64.0.0/13      (Cloudflare)
  443/tcp from 131.0.72.0/22      (Cloudflare)
```

---

## Backups

| Que | Frecuencia | Retencion | Donde |
|---|---|---|---|
| MySQL snapshots | Diario automatico | 7 dias | Managed MySQL (incluido) |
| DB dump completo | Semanal (cron job en worker) | 365 dias | Spaces |
| Codigo | Cada push | Indefinido | GitHub |
| Container images | Cada deploy | Automatico | DO Container Registry |

---

## Dominios y DNS

```
api.facturacion.cr          → Cloudflare → App Platform (API produccion)
sandbox.facturacion.cr      → Cloudflare → App Platform (API sandbox)
portal.facturacion.cr       → Cloudflare → App Platform (Dashboard, Fase 3)
docs.facturacion.cr         → Cloudflare → Spaces static site
status.facturacion.cr       → Betterstack/Instatus (status page)
```

Cloudflare como DNS autoritativo y proxy. App Platform como origin.

---

## Estimacion de capacidad

### Etapa 1 (~$50/mes)

| Metrica | Capacidad |
|---|---|
| Facturas por hora | ~100-300 |
| Tenants activos | ~5-15 |
| Requests por segundo (pico) | ~5-15 |
| Almacenamiento DB (1 ano) | ~1-3 GB |

### Etapa 2 (~$106/mes)

| Metrica | Capacidad |
|---|---|
| Facturas por hora | ~300-1,500 |
| Tenants activos | ~30-100 |
| Requests por segundo (pico) | ~20-50 |
| Almacenamiento DB (1 ano) | ~5-15 GB |

### Etapa 3 (~$254/mes)

| Metrica | Capacidad |
|---|---|
| Facturas por hora | ~1,500-5,000 |
| Tenants activos | ~100-500 |
| Requests por segundo (pico) | ~50-150 |
| Almacenamiento DB (1 ano) | ~15-50 GB |

El cuello de botella siempre es Hacienda (~1-3s por factura). El procesamiento async con colas absorbe picos.

---

## DigitalOcean vs AWS — por que DO puede ser suficiente

| Preocupacion | Realidad en DO |
|---|---|
| "No escala" | App Platform soporta hasta 100+ containers. Managed MySQL hasta 64GB RAM. |
| "No tiene HA" | MySQL standby node = failover automatico. App Platform distribuye containers. |
| "No tiene WAF" | Cloudflare Pro ($20/mes) = WAF completo, mejor que AWS WAF para la mayoria de casos. |
| "No tiene secrets manager" | App Platform env vars encriptadas + Vault si crece la necesidad. |
| "Region Latam" | NYC es ~80ms a Costa Rica. Aceptable para una API asincrona. |
| "No es enterprise" | Slack, GitLab, Hashicorp, y miles de SaaS corren en DO. |

### Cuando SI necesitarias AWS

- Regulacion que exige data residency en Latam (RDS en sa-east-1)
- Necesitas mas de 500 tenants concurrentes con auto-scaling agresivo
- Necesitas Secrets Manager nativo con rotacion y audit log por compliance
- Un cliente enterprise exige certificacion de infraestructura AWS

Si ninguno de esos aplica hoy, DO es la opcion pragmatica.