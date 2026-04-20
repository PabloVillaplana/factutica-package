# Invoicing Service — Infraestructura AWS

> Plan de infraestructura en AWS con crecimiento progresivo. Una sola plataforma, sin migraciones.

---

## Por que AWS desde el dia uno

- **Sin migraciones** — cada migracion de infra es riesgo, downtime, y semanas perdidas
- **Region Latam** — Sao Paulo (sa-east-1), ~30ms a Costa Rica vs ~80ms desde NYC
- **Secrets Manager** — los certificados .p12 y credenciales IDP son el activo mas sensible del servicio. Necesitan gestion nativa de secrets, no archivos .env
- **Auto-scaling real** — Fargate escala containers sin intervenir. Cuando lleguen mas clientes, la infra crece sola
- **Multi-AZ** — alta disponibilidad nativa. Un datacenter cae y el servicio sigue
- **El costo inicial es comparable** — la diferencia con DigitalOcean es ~$10-20/mes. No justifica una migracion futura

---

## Arquitectura

```
                         ┌───────────────────────┐
                         │     Route 53 (DNS)     │
                         └───────────┬───────────┘
                                     │
                         ┌───────────▼───────────┐
                         │   CloudFront + WAF     │
                         │   (SSL, DDoS, cache)   │
                         └───────────┬───────────┘
                                     │
                         ┌───────────▼───────────┐
                         │   ALB (Application     │
                         │   Load Balancer)       │
                         └───────────┬───────────┘
                                     │
                    ┌────────────────┼────────────────┐
                    │                                 │
           ┌────────▼────────┐              ┌────────▼────────┐
           │  ECS Fargate    │              │  ECS Fargate    │
           │  API Service    │              │  Worker Service │
           │  (2-6 tasks)    │              │  (Horizon)      │
           └────────┬────────┘              └────────┬────────┘
                    │                                 │
                    └────────────────┬────────────────┘
                                     │
            ┌────────────────────────┼────────────────────────┐
            │                        │                        │
   ┌────────▼────────┐    ┌─────────▼────────┐    ┌─────────▼────────┐
   │   RDS MySQL     │    │   ElastiCache    │    │   S3             │
   │   (Multi-AZ)    │    │   Redis          │    │   Backups, logs  │
   └─────────────────┘    └──────────────────┘    └──────────────────┘
            │
   ┌────────▼────────┐    ┌──────────────────┐    ┌──────────────────┐
   │  Secrets Manager│    │   CloudWatch     │    │   SES            │
   │  (master key,   │    │   (logs, metrics │    │   (emails,       │
   │   certs, creds) │    │    alertas)      │    │    notifs)       │
   └─────────────────┘    └──────────────────┘    └──────────────────┘
```

---

## Crecimiento progresivo

La clave es usar servicios que escalen **sin cambiar arquitectura**. Solo se ajustan specs.

### Etapa 1 — MVP (Fase 1 del roadmap)

helixERP + primeros clientes sandbox. Bajo trafico.

```
ECS Fargate API         2 tasks × (0.25 vCPU, 0.5GB)     ~$15/mes
ECS Fargate Worker      1 task  × (0.25 vCPU, 0.5GB)     ~$8/mes
ALB                     Application Load Balancer           ~$22/mes
RDS MySQL               db.t4g.micro (2 vCPU, 1GB, 20GB)  ~$15/mes
ElastiCache Redis       cache.t4g.micro (0.5GB)            ~$10/mes
NAT Gateway             1 AZ (trafico a Hacienda)          ~$32/mes
S3                      Backups + logs                      ~$2/mes
Secrets Manager         3-5 secrets                         ~$2/mes
Route 53                Hosted zone                         ~$1/mes
ACM                     SSL certificate                     $0
CloudWatch              Logs basicos                        ~$5/mes
──────────────────────────────────────────────────────────
Total:                                                     ~$112/mes
```

**Lo que NO necesitas todavia:** CloudFront, WAF, Multi-AZ en RDS, SES, auto-scaling.

### Etapa 2 — Produccion (Fase 2 del roadmap)

Clientes reales facturando. Necesitas HA y seguridad.

```
ECS Fargate API         2 tasks × (0.5 vCPU, 1GB)         ~$30/mes
ECS Fargate Worker      1 task  × (0.5 vCPU, 1GB)         ~$15/mes
ALB + WAF               WAF rules basicas                  ~$32/mes
RDS MySQL               db.t4g.small (2GB) + Multi-AZ      ~$30/mes
ElastiCache Redis       cache.t4g.micro (0.5GB)            ~$10/mes
NAT Gateway             2 AZ (alta disponibilidad)         ~$64/mes
S3                      Backups + logs                      ~$3/mes
Secrets Manager         10-20 secrets                       ~$5/mes
Route 53                                                    ~$1/mes
CloudFront              CDN + SSL edge                      ~$5/mes
CloudWatch              Logs + metricas + alertas           ~$10/mes
SES                     Notificaciones                      ~$1/mes
──────────────────────────────────────────────────────────
Total:                                                     ~$206/mes
```

**Que cambio:** Multi-AZ en RDS (failover automatico), WAF (proteccion API), CloudFront (SSL edge), SES (emails de certificado expirando), NAT redundante.

### Etapa 3 — Escala (Fase 3 del roadmap)

Muchos clientes, portal web, billing.

```
ECS Fargate API         Auto-scaling 2-8 tasks              ~$30-120/mes
ECS Fargate Worker      Auto-scaling 1-4 tasks              ~$15-60/mes
ECS Fargate Portal      2 tasks (portal web)                ~$30/mes
ALB + WAF               WAF completo                        ~$40/mes
RDS MySQL               db.t4g.medium (4GB) + Multi-AZ      ~$60/mes
ElastiCache Redis       cache.t4g.small (1.5GB)             ~$20/mes
NAT Gateway             2 AZ                                ~$64/mes
S3                      Backups + logs + assets portal      ~$5/mes
Secrets Manager         50+ secrets                         ~$15/mes
Route 53                                                     ~$1/mes
CloudFront              CDN completo                         ~$10/mes
CloudWatch              Full observability                   ~$20/mes
SES                     Emails transaccionales              ~$5/mes
──────────────────────────────────────────────────────────
Total:                                                      ~$315-450/mes
```

**Que cambio:** Auto-scaling (la infra crece y decrece con la demanda), portal como servicio separado, DB mas grande, WAF completo.

### Resumen

```
Etapa 1 (MVP)        ~$112/mes   ← empezas aqui
     │                              solo cambias specs
     ▼
Etapa 2 (Prod)       ~$206/mes   ← agregas Multi-AZ, WAF, CloudFront
     │                              misma arquitectura
     ▼
Etapa 3 (Escala)     ~$315+/mes  ← activas auto-scaling, agregas portal
                                    misma arquitectura
```

**Cero migraciones.** Misma VPC, mismos security groups, mismos deploys. Solo crece.

---

## Detalle de servicios

### ECS Fargate (Compute)

Containers serverless. No hay EC2 que administrar.

| Servicio | Container | Puerto | Health check |
|---|---|---|---|
| `invoicing-api` | PHP-FPM + Nginx | 8080 | `GET /health` |
| `invoicing-worker` | `php artisan horizon` | — | Horizon status |
| `invoicing-portal` | PHP-FPM + Nginx (Fase 3) | 8080 | `GET /` |

**Dockerfile:**

```dockerfile
FROM php:8.2-fpm-alpine

# Extensions necesarias para el core
RUN apk add --no-cache libxml2-dev openssl-dev \
    && docker-php-ext-install pdo_mysql bcmath xml

COPY . /var/www/html
RUN composer install --no-dev --optimize-autoloader

COPY docker/nginx.conf /etc/nginx/nginx.conf

EXPOSE 8080
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
```

**Auto-scaling (Etapa 3):**

```
CPU > 70% por 3 min  → agregar task
CPU < 30% por 10 min → remover task
Min: 2 tasks
Max: 8 tasks
```

### RDS MySQL

| Config | Etapa 1 | Etapa 2 | Etapa 3 |
|---|---|---|---|
| Instancia | db.t4g.micro | db.t4g.small | db.t4g.medium |
| RAM | 1 GB | 2 GB | 4 GB |
| Storage | 20 GB gp3 | 50 GB gp3 | 100 GB gp3 |
| Multi-AZ | No | Si | Si |
| Backups | 7 dias | 14 dias | 30 dias |
| Encryption at rest | Si | Si | Si |

Encryption at rest habilitado desde el dia uno (gratis en RDS). Los certificados .p12 en DB ya estan encriptados por la app — esto agrega una segunda capa.

### ElastiCache Redis

Uso:
- **Cache:** Tokens OAuth2 de Hacienda por tenant (`invoicing_token_{tenant_id}_{ambiente}`)
- **Queue:** Laravel Horizon para jobs (envio a Hacienda, webhooks salientes)
- **Rate limiting:** Counters por API key

### Secrets Manager

| Secret | Proposito |
|---|---|
| `invoicing/master-key` | Master key para encriptar certs y creds en DB |
| `invoicing/db-credentials` | Usuario y password de RDS |
| `invoicing/redis-auth` | Auth token de ElastiCache |
| `invoicing/app-key` | Laravel APP_KEY |

Rotacion automatica de DB password cada 30 dias.

### ALB + WAF

**ALB rules:**

| Regla | Accion |
|---|---|
| Path `/health` | Allow sin auth (health checks) |
| Path `/webhook` | Allow sin API key (verificado por clave + firma) |
| Path `/*` | Requiere header `X-Api-Key` |

**WAF rules (Etapa 2+):**

| Regla | Protege contra |
|---|---|
| Rate limit 1000 req/5min por IP | DDoS |
| SQL injection patterns | SQLi |
| Body > 1MB | Payload abuse |
| Geo restriction (opcional) | Abuse de regiones inesperadas |

### CloudWatch

**Metricas custom:**

```
invoicing/receipts_created      — facturas creadas por minuto
invoicing/receipts_sent         — facturas enviadas a Hacienda
invoicing/receipts_accepted     — facturas aceptadas
invoicing/receipts_rejected     — facturas rechazadas
invoicing/webhook_delivery      — webhooks entregados a clientes
invoicing/queue_depth           — jobs pendientes en cola
invoicing/hacienda_latency      — tiempo de respuesta de Hacienda
```

**Alarmas:**

| Alarma | Condicion | Accion |
|---|---|---|
| Hacienda down | latency > 30s por 5 min | SNS → email/Slack |
| Queue saturada | depth > 200 por 5 min | SNS → email/Slack |
| Alta tasa rechazo | rejected/total > 15% por 1h | SNS → email |
| Cert expirando | Cron diario revisa expires_at | SES → email al tenant |
| API errors | 5xx > 5% por 5 min | SNS → email/Slack |

### S3

| Bucket | Contenido | Lifecycle |
|---|---|---|
| `invoicing-backups` | DB dumps semanales | Glacier despues de 30 dias, borrar a 365 |
| `invoicing-logs` | Access logs de ALB | Borrar despues de 90 dias |

Los certificados .p12 **no van en S3** — van encriptados en RDS.

---

## VPC y seguridad de red

```
VPC: 10.0.0.0/16

├── Public Subnets (ALB + NAT)
│   ├── 10.0.1.0/24  (sa-east-1a)
│   └── 10.0.2.0/24  (sa-east-1b)
│
├── Private Subnets (App)
│   ├── 10.0.10.0/24 (sa-east-1a)  ← ECS tasks
│   └── 10.0.11.0/24 (sa-east-1b)  ← ECS tasks
│
└── Private Subnets (Data)
    ├── 10.0.20.0/24 (sa-east-1a)  ← RDS primary
    └── 10.0.21.0/24 (sa-east-1b)  ← RDS standby + Redis
```

**Security Groups:**

```
sg-alb:     Inbound 443 from 0.0.0.0/0
sg-app:     Inbound 8080 from sg-alb only
sg-rds:     Inbound 3306 from sg-app only
sg-redis:   Inbound 6379 from sg-app only
```

Las subnets privadas usan NAT Gateway para salida a internet (llamar a Hacienda API).

### VPC Endpoints (reducir trafico por NAT)

| Endpoint | Tipo | Costo | Elimina NAT para |
|---|---|---|---|
| S3 | Gateway | $0 | Backups, logs |
| Secrets Manager | Interface | ~$7/mes | Lectura de secrets |
| CloudWatch Logs | Interface | ~$7/mes | Envio de logs |
| ECR | Interface | ~$7/mes | Pull de imagenes Docker |

Con endpoints, el NAT solo se usa para trafico a Hacienda API (minimo).

---

## CI/CD con GitHub Actions

```yaml
# .github/workflows/deploy.yml
name: Deploy to ECS

on:
  push:
    branches: [main]

env:
  AWS_REGION: sa-east-1
  ECR_REPOSITORY: invoicing-service
  ECS_CLUSTER: invoicing
  ECS_SERVICE_API: invoicing-api
  ECS_SERVICE_WORKER: invoicing-worker

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
      - uses: actions/checkout@v4

      - uses: aws-actions/configure-aws-credentials@v4
        with:
          aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
          aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
          aws-region: ${{ env.AWS_REGION }}

      - uses: aws-actions/amazon-ecr-login@v2
        id: ecr

      - name: Build and push Docker image
        run: |
          docker build -t ${{ steps.ecr.outputs.registry }}/${{ env.ECR_REPOSITORY }}:${{ github.sha }} .
          docker push ${{ steps.ecr.outputs.registry }}/${{ env.ECR_REPOSITORY }}:${{ github.sha }}

      - name: Deploy API service
        run: |
          aws ecs update-service \
            --cluster ${{ env.ECS_CLUSTER }} \
            --service ${{ env.ECS_SERVICE_API }} \
            --force-new-deployment

      - name: Deploy Worker service
        run: |
          aws ecs update-service \
            --cluster ${{ env.ECS_CLUSTER }} \
            --service ${{ env.ECS_SERVICE_WORKER }} \
            --force-new-deployment

      - name: Wait for stable deployment
        run: |
          aws ecs wait services-stable \
            --cluster ${{ env.ECS_CLUSTER }} \
            --services ${{ env.ECS_SERVICE_API }} ${{ env.ECS_SERVICE_WORKER }}
```

Zero-downtime deploy: ECS hace rolling update — levanta nuevos tasks, espera health check, mata los viejos.

Costo GitHub Actions: $0 (2,000 min/mes gratis en repos privados).

---

## Backups

| Que | Frecuencia | Retencion | Donde |
|---|---|---|---|
| RDS snapshots | Diario automatico | 7-30 dias (segun etapa) | RDS nativo |
| DB dump completo | Semanal (cron job) | 365 dias | S3 → Glacier |
| Secrets | Con cada cambio | Versionado nativo | Secrets Manager |
| Codigo | Cada push | Indefinido | GitHub |
| Docker images | Cada deploy | 30 ultimas | ECR |

---

## Dominios y DNS

```
api.facturacion.cr          → CloudFront → ALB (API produccion)
sandbox.facturacion.cr      → CloudFront → ALB (API sandbox)
portal.facturacion.cr       → CloudFront → ALB (Dashboard, Fase 3)
docs.facturacion.cr         → S3 static site (documentacion API)
status.facturacion.cr       → External status page
```

Route 53 como DNS autoritativo. CloudFront al frente para SSL + cache + WAF.

---

## Estimacion de capacidad

### Etapa 1 (~$112/mes)

| Metrica | Capacidad |
|---|---|
| Facturas por hora | ~200-500 |
| Tenants activos | ~10-30 |
| Requests por segundo (pico) | ~10-20 |
| Almacenamiento DB (1 ano) | ~2-5 GB |

### Etapa 2 (~$206/mes)

| Metrica | Capacidad |
|---|---|
| Facturas por hora | ~500-2,000 |
| Tenants activos | ~50-200 |
| Requests por segundo (pico) | ~30-80 |
| Almacenamiento DB (1 ano) | ~10-25 GB |

### Etapa 3 (~$315+/mes)

| Metrica | Capacidad |
|---|---|
| Facturas por hora | ~2,000-10,000 |
| Tenants activos | ~200-1,000 |
| Requests por segundo (pico) | ~80-300 |
| Almacenamiento DB (1 ano) | ~25-100 GB |

El cuello de botella siempre es Hacienda (~1-3s por factura). El procesamiento async con colas absorbe picos.

---

## Nota sobre el NAT Gateway

El NAT Gateway es el costo mas doloroso de AWS (~$32/mes por AZ). Alternativas a futuro:

- **Fargate con IPv6** — eliminaria necesidad de NAT para trafico saliente
- **VPC Endpoints** — reducen trafico que pasa por NAT (S3, Secrets, CloudWatch, ECR gratis)
- **Un solo NAT en Etapa 1** — suficiente, redundancia en Etapa 2+