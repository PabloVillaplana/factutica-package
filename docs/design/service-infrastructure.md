# Invoicing Service — Plan de Infraestructura

> Implementacion en AWS y DigitalOcean con estimacion de costos.

---

## Opcion A: DigitalOcean

Mas simple, mas barato para empezar. Ideal para Fase 1 y 2.

### Arquitectura

```
                    ┌──────────────────────┐
                    │   Cloudflare (DNS)    │
                    │   SSL + DDoS + CDN    │
                    └──────────┬───────────┘
                               │
                    ┌──────────▼───────────┐
                    │   DO Load Balancer   │
                    │   ($12/mes)          │
                    └──────────┬───────────┘
                               │
                 ┌─────────────┼─────────────┐
                 │                           │
        ┌────────▼────────┐        ┌────────▼────────┐
        │   App Server 1  │        │   App Server 2  │
        │   (Droplet)     │        │   (Droplet)     │
        │   Laravel API   │        │   Laravel API   │
        │   + Horizon     │        │   + Horizon     │
        │   $24/mes       │        │   $24/mes       │
        └────────┬────────┘        └────────┬────────┘
                 │                           │
                 └─────────────┬─────────────┘
                               │
              ┌────────────────┼────────────────┐
              │                │                │
     ┌────────▼──────┐ ┌──────▼───────┐ ┌──────▼──────┐
     │  Managed MySQL │ │ Managed Redis│ │  Spaces     │
     │  (DB Cluster)  │ │  (Cache)     │ │  (S3-compat)│
     │  $15/mes       │ │  $15/mes     │ │  $5/mes     │
     └───────────────┘ └──────────────┘ └─────────────┘
```

### Componentes

| Componente | Servicio DO | Spec inicial | Costo/mes |
|---|---|---|---|
| **App servers** | Droplets (x2) | 2 vCPU, 4GB RAM, Ubuntu | $48 |
| **Load balancer** | DO Load Balancer | - | $12 |
| **Base de datos** | Managed MySQL | 1 vCPU, 2GB RAM, 25GB | $15 |
| **Cache/Queue** | Managed Redis | 1 vCPU, 1GB RAM | $15 |
| **Object storage** | Spaces | Certificados encriptados, backups | $5 |
| **DNS + SSL** | Cloudflare (free) | Proxy, DDoS, SSL edge | $0 |
| **Monitoreo** | DO Monitoring + Uptime | Built-in | $0 |
| **Backups DB** | Managed backup | Daily, 7 dias retencion | Incluido |
| | | **Total estimado** | **~$95/mes** |

### Escalamiento

| Fase | Cambio | Costo adicional |
|---|---|---|
| Mas trafico | Agregar Droplet 3 atras del LB | +$24/mes |
| Mas DB | Escalar a 2 vCPU, 4GB | +$35/mes |
| Mas Redis | Escalar a 2GB | +$15/mes |
| Alta disponibilidad DB | MySQL standby node | +$15/mes |

### Alternativa: App Platform (PaaS)

Si se quiere aun mas simple (sin administrar Droplets):

| Componente | Servicio | Costo/mes |
|---|---|---|
| App (x2 containers) | App Platform Pro | $24 |
| Worker (Horizon) | App Platform Worker | $12 |
| MySQL | Managed DB | $15 |
| Redis | Managed Redis | $15 |
| | **Total** | **~$66/mes** |

Ventaja: deploy con `git push`, zero-downtime deploys, auto-scaling incluido.
Desventaja: menos control sobre la infra.

### Deploy

```bash
# Opcion 1: Droplets con deploy script
ssh deploy@app1 "cd /var/www/invoicing && git pull && composer install --no-dev && php artisan migrate --force && php artisan horizon:terminate"

# Opcion 2: App Platform
git push origin main  # deploy automatico

# Opcion 3: Docker
doctl apps create --spec .do/app.yaml
```

---

## Opcion B: AWS

Mas robusto, mas servicios nativos, mejor para Fase 2-3 con muchos clientes.

### Arquitectura

```
                    ┌───────────────────────────┐
                    │   Route 53 (DNS)          │
                    └──────────┬────────────────┘
                               │
                    ┌──────────▼────────────────┐
                    │   CloudFront (CDN + SSL)  │
                    │   + WAF                    │
                    └──────────┬────────────────┘
                               │
                    ┌──────────▼────────────────┐
                    │   ALB (Application LB)    │
                    └──────────┬────────────────┘
                               │
              ┌────────────────┼────────────────┐
              │                                 │
     ┌────────▼────────┐              ┌────────▼────────┐
     │   ECS Fargate   │              │   ECS Fargate   │
     │   API Container │              │   Worker        │
     │   (x2 tasks)    │              │   (Horizon)     │
     └────────┬────────┘              └────────┬────────┘
              │                                 │
              └────────────────┬────────────────┘
                               │
         ┌─────────────────────┼─────────────────────┐
         │                     │                     │
  ┌──────▼───────┐    ┌───────▼──────┐    ┌─────────▼────────┐
  │  RDS MySQL   │    │ ElastiCache  │    │  S3              │
  │  (Multi-AZ)  │    │ Redis        │    │  Certs + Backups │
  └──────────────┘    └──────────────┘    └──────────────────┘
         │
  ┌──────▼───────┐    ┌──────────────┐    ┌──────────────────┐
  │  Secrets Mgr │    │ CloudWatch   │    │  SES             │
  │  (master key)│    │  (logs +     │    │  (emails)        │
  └──────────────┘    │   alertas)   │    └──────────────────┘
                      └──────────────┘
```

### Componentes

| Componente | Servicio AWS | Spec inicial | Costo/mes |
|---|---|---|---|
| **App containers** | ECS Fargate (x2 tasks) | 0.5 vCPU, 1GB RAM | ~$30 |
| **Worker container** | ECS Fargate (x1 task) | 0.5 vCPU, 1GB RAM | ~$15 |
| **Load balancer** | ALB | - | ~$22 |
| **Base de datos** | RDS MySQL (db.t4g.micro) | 2 vCPU, 1GB, 20GB | ~$15 |
| **Cache/Queue** | ElastiCache Redis (t4g.micro) | 1GB | ~$13 |
| **Object storage** | S3 | Certificados, backups | ~$2 |
| **Secrets** | Secrets Manager | Master encryption key | ~$1 |
| **DNS** | Route 53 | Hosted zone | ~$1 |
| **SSL** | ACM | Certificado SSL | $0 |
| **Logs** | CloudWatch | Logs + metricas basicas | ~$5 |
| **Emails** | SES | Notificaciones | ~$1 |
| | | **Total estimado** | **~$105/mes** |

### Escalamiento

| Fase | Cambio | Costo adicional |
|---|---|---|
| Mas trafico | Fargate auto-scaling (3-6 tasks) | +$15-45/mes |
| Mas DB | db.t4g.small (2GB) | +$15/mes |
| Alta disponibilidad DB | Multi-AZ | +$15/mes |
| Mas Redis | cache.t4g.small | +$13/mes |
| WAF (proteccion API) | AWS WAF | +$10/mes |

### Alternativa: Lightsail (AWS simple)

Para empezar mas barato sin complejidad de ECS:

| Componente | Servicio | Costo/mes |
|---|---|---|
| App server (x2) | Lightsail instances (2GB) | $20 |
| Load balancer | Lightsail LB | $18 |
| MySQL | Lightsail Managed DB | $15 |
| Object storage | Lightsail Storage | $1 |
| Redis | (en la instancia o ElastiCache) | $0-13 |
| | **Total** | **~$54-67/mes** |

### Deploy

```bash
# Opcion 1: ECS Fargate con Docker
aws ecr get-login-password | docker login --username AWS --password-stdin $ECR_REPO
docker build -t invoicing-service .
docker push $ECR_REPO:latest
aws ecs update-service --cluster invoicing --service api --force-new-deployment

# Opcion 2: Lightsail con deploy script
ssh deploy@lightsail "cd /var/www/invoicing && git pull && composer install --no-dev && php artisan migrate --force"
```

---

## Comparacion directa

| Aspecto | DigitalOcean | AWS |
|---|---|---|
| **Costo inicial** | ~$95/mes | ~$105/mes |
| **Costo minimo** | ~$66/mes (App Platform) | ~$54/mes (Lightsail) |
| **Complejidad** | Baja | Media-Alta |
| **Auto-scaling** | Manual o App Platform | Fargate auto-scaling nativo |
| **Multi-AZ / HA** | Limitado (datacenter unico) | Multi-AZ nativo |
| **Managed MySQL** | Si | Si (RDS) |
| **Managed Redis** | Si | Si (ElastiCache) |
| **Secrets management** | No nativo (usar .env encriptado) | Secrets Manager nativo |
| **WAF** | No nativo (usar Cloudflare) | AWS WAF nativo |
| **Monitoreo** | Basico + Cloudflare analytics | CloudWatch completo |
| **Certificados SSL** | Let's Encrypt o Cloudflare | ACM (gratis, auto-renueva) |
| **Regiones Latam** | No (mas cercano: NYC) | Sao Paulo (sa-east-1) |
| **Ideal para** | Fase 1-2, equipos pequenos | Fase 2-3, escala, compliance |

---

## Recomendacion por fase

### Fase 1 (MVP): DigitalOcean App Platform

```
App Platform Pro ($24)
  ├── API container (x2 replicas)
  └── Worker container (Horizon)
Managed MySQL ($15)
Managed Redis ($15)
Spaces ($5)
Cloudflare (free)
─────────────────
Total: ~$59/mes
```

**Por que:** Deploy en minutos con `git push`. Sin servidores que administrar. Suficiente para helixERP + primeros clientes sandbox.

### Fase 2 (Produccion): DigitalOcean Droplets o AWS Lightsail

```
Droplets x2 ($48) o Lightsail x2 ($20)
Load Balancer ($12-18)
Managed MySQL con standby ($30)
Managed Redis ($15)
Spaces/S3 ($5)
─────────────────
Total: ~$80-110/mes
```

**Por que:** Mas control. Standby de DB para alta disponibilidad. Aun simple de operar.

### Fase 3 (Escala): AWS ECS Fargate

```
Fargate API (auto-scaling 2-6) (~$30-90)
Fargate Worker (~$15-30)
ALB ($22)
RDS MySQL Multi-AZ ($30)
ElastiCache Redis ($13)
S3 + Secrets Manager ($3)
CloudWatch + WAF ($15)
─────────────────
Total: ~$128-203/mes
```

**Por que:** Auto-scaling real. Multi-AZ. WAF nativo. Secrets Manager para certificados. Listo para muchos clientes.

---

## Infraestructura compartida (ambas opciones)

### CI/CD: GitHub Actions

```yaml
# .github/workflows/deploy.yml
name: Deploy
on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: dom, curl, openssl, mbstring, mysql, redis
      - run: composer install --no-dev
      - run: vendor/bin/pest --exclude-group=integration

  deploy:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to production
        run: |
          # DO App Platform: doctl apps create-deployment $APP_ID
          # AWS ECS: aws ecs update-service --force-new-deployment
          # Droplets/Lightsail: ssh deploy script
```

Costo: $0 (2,000 min/mes gratis en repos privados).

### Cloudflare (ambas opciones)

- **DNS** — nameservers de Cloudflare
- **SSL** — edge SSL (full strict mode)
- **DDoS** — proteccion basica incluida
- **Rate limiting** — capa extra sobre el rate limiting de la app
- **Page rules** — cache de assets estaticos (docs API)

Costo: $0 (plan free).

### Backups

| Que | Frecuencia | Retencion | Donde |
|---|---|---|---|
| Base de datos | Diario automatico | 7 dias | Managed backup (DO/RDS) |
| Base de datos | Semanal manual | 30 dias | Spaces/S3 |
| Certificados encriptados | Con cada upload | Indefinido | Ya estan en DB (encriptados) |
| Codigo | Cada push | Indefinido | GitHub |

### Dominios sugeridos

```
api.facturacion.cr          — API produccion
sandbox.facturacion.cr      — API sandbox
portal.facturacion.cr       — Dashboard web (Fase 3)
docs.facturacion.cr         — Documentacion API
status.facturacion.cr       — Status page
```

---

## Seguridad de red

### DigitalOcean

```
Internet → Cloudflare → DO LB (443 only)
  → Droplets (private network, no public IP)
    → MySQL (private network, trusted sources only)
    → Redis (private network, trusted sources only)
```

- Firewall de DO: solo permite trafico del LB a los Droplets
- MySQL/Redis: solo aceptan conexiones de la VPC privada
- Spaces: acceso via service credentials, no publico

### AWS

```
Internet → CloudFront → ALB (443 only, WAF)
  → ECS Fargate (private subnet)
    → RDS (private subnet, security group)
    → ElastiCache (private subnet, security group)
```

- VPC con subnets publicas (ALB) y privadas (app, DB, Redis)
- Security groups: ALB → ECS (8080), ECS → RDS (3306), ECS → Redis (6379)
- RDS y ElastiCache sin acceso publico
- Secrets Manager para master encryption key (nunca en .env)

---

## Estimacion de capacidad

Con la infraestructura inicial (~$95-105/mes):

| Metrica | Capacidad estimada |
|---|---|
| Facturas por hora | ~500-1,000 |
| Tenants activos | ~50-100 |
| Almacenamiento DB (1 ano) | ~5-10 GB |
| Requests por segundo (pico) | ~20-50 |

Estos numeros son conservadores. El cuello de botella es la API de Hacienda (~1-3s por factura), no nuestra infra. El procesamiento async con colas absorbe picos sin problema.