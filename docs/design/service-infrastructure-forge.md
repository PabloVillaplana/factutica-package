# Invoicing Service — Infraestructura con Laravel Forge

> Plan de infraestructura usando Laravel Forge como capa de gestion. Deploy Laravel nativo sin Docker ni PaaS.

---

## Por que Forge

- **Hecho para Laravel** — configura PHP-FPM, Nginx, queue workers, scheduler, SSL, y deploys automaticamente
- **Sin Docker** — deploy tradicional con `git pull` + `composer install`. Sin Dockerfiles, sin registries, sin orquestadores
- **Elige tu proveedor** — Forge provisiona servidores en DigitalOcean, AWS, Hetzner, Vultr, o custom. Cambiar de proveedor es reprovisionar, no reescribir infra
- **$12/mes** — un precio fijo cubre servidores ilimitados, sitios ilimitados, workers ilimitados
- **Lo que ya conoces** — mismo stack que Laravel Herd en local, pero en produccion. Sin curva de aprendizaje de ECS, Fargate, o Kubernetes

---

## Arquitectura

```
                         ┌───────────────────────┐
                         │   Cloudflare (DNS)     │
                         │   SSL + DDoS + WAF     │
                         └───────────┬───────────┘
                                     │
                         ┌───────────▼───────────┐
                         │   Load Balancer        │
                         │   (DO/AWS/Forge)       │
                         └───────────┬───────────┘
                                     │
                    ┌────────────────┼────────────────┐
                    │                                 │
           ┌────────▼────────┐              ┌────────▼────────┐
           │  Web Server     │              │  Web Server     │
           │  Nginx + PHP-FPM│              │  Nginx + PHP-FPM│
           │  Forge-managed  │              │  Forge-managed  │
           └────────┬────────┘              └────────┬────────┘
                    │                                 │
                    └────────────────┬────────────────┘
                                     │
            ┌────────────────────────┼────────────────────────┐
            │                        │                        │
   ┌────────▼────────┐    ┌─────────▼────────┐    ┌─────────▼────────┐
   │  Managed MySQL  │    │  Managed Redis   │    │  Object Storage  │
   │  (DO/AWS/Hetz)  │    │  (o en servidor) │    │  Backups         │
   └─────────────────┘    └──────────────────┘    └──────────────────┘
```

---

## Forge + Hetzner (mejor precio/rendimiento)

Hetzner ofrece el mejor rendimiento por dolar. Forge lo soporta nativamente.

### Por que Hetzner

| Aspecto | Hetzner | DigitalOcean | AWS Lightsail |
|---|---|---|---|
| 2 vCPU, 4GB RAM | **$6.30/mes** | $24/mes | $18/mes |
| 4 vCPU, 8GB RAM | **$12.50/mes** | $48/mes | $36/mes |
| Datacenter mas cercano | Ashburn, US | NYC | sa-east-1 |
| Managed MySQL | No | Si | No (RDS aparte) |
| Managed Redis | No | Si | No |

**Hetzner es 3-4x mas barato** en compute. La desventaja es que no tiene managed MySQL ni Redis — pero Forge instala y configura MySQL y Redis directo en el servidor.

### Cuando NO usar Hetzner

- Si necesitas managed MySQL con failover automatico → DigitalOcean o AWS
- Si necesitas data residency Latam → AWS sa-east-1
- Si un cliente exige un cloud provider especifico

---

## Crecimiento progresivo

### Etapa 1 — MVP (1 servidor)

Un solo servidor Hetzner maneja API + Worker + MySQL + Redis. Simple y barato.

```
Forge subscription                                  $12/mes
Hetzner CX32 (4 vCPU, 8GB, 80GB SSD)              $12.50/mes
  ├── Nginx + PHP-FPM (API)
  ├── Laravel Horizon (Worker)
  ├── MySQL 8.0 (local)
  └── Redis (local)
Hetzner Backup (20% del server)                     $2.50/mes
Cloudflare (free)                                   $0
──────────────────────────────────────────────────
Total:                                             ~$27/mes
```

**Todo en un servidor.** Forge configura Nginx, PHP 8.2, MySQL, Redis, SSL, y queue workers automaticamente. Un `git push` deploya.

**Limitaciones:** Sin alta disponibilidad. Si el servidor cae, todo cae. Aceptable para MVP y sandbox.

### Etapa 2 — Produccion (servidores separados)

Separar app de datos. Agregar redundancia.

```
Forge subscription                                  $12/mes

Web Server 1 — Hetzner CX22 (2 vCPU, 4GB)         $6.30/mes
  ├── Nginx + PHP-FPM (API)
  └── Laravel Horizon (Worker)

Web Server 2 — Hetzner CX22 (2 vCPU, 4GB)         $6.30/mes
  ├── Nginx + PHP-FPM (API)
  └── Laravel Horizon (Worker)

DB Server — Hetzner CX32 (4 vCPU, 8GB)            $12.50/mes
  ├── MySQL 8.0
  └── Redis

Hetzner Load Balancer                               $6.40/mes
Hetzner Backups (3 servers)                          $5/mes
Cloudflare Pro (WAF)                                $20/mes
──────────────────────────────────────────────────
Total:                                             ~$68/mes
```

**Que cambio:** App y datos separados. 2 web servers atras de un LB. Si un web server cae, el otro sigue. DB en servidor dedicado con mas RAM.

### Etapa 2b — Produccion con Managed DB (DO o AWS)

Si queres failover automatico de base de datos:

```
Forge subscription                                  $12/mes

Web Server 1 — Hetzner CX22 (2 vCPU, 4GB)         $6.30/mes
Web Server 2 — Hetzner CX22 (2 vCPU, 4GB)         $6.30/mes
Hetzner Load Balancer                               $6.40/mes
Hetzner Backups                                     $2.50/mes

DO Managed MySQL (db-s-1vcpu-2gb) + Standby         $30/mes
DO Managed Redis (db-s-1vcpu-1gb)                    $15/mes

Cloudflare Pro                                      $20/mes
──────────────────────────────────────────────────
Total:                                             ~$98/mes
```

**Hibrido:** Compute barato en Hetzner, datos con HA en DigitalOcean. Forge conecta ambos.

### Etapa 3 — Escala

```
Forge subscription                                  $12/mes

Web Server x3 — Hetzner CX32 (4 vCPU, 8GB)        $37.50/mes
Worker Server x2 — Hetzner CX22 (2 vCPU, 4GB)     $12.60/mes
Portal Server x2 — Hetzner CX22 (2 vCPU, 4GB)     $12.60/mes
Hetzner Load Balancer x2 (API + Portal)             $12.80/mes
Hetzner Backups                                     $5/mes

DO Managed MySQL (db-s-2vcpu-4gb) + Standby         $60/mes
DO Managed Redis (db-s-2vcpu-4gb)                    $40/mes

Cloudflare Pro                                      $20/mes
──────────────────────────────────────────────────
Total:                                             ~$212/mes
```

### Resumen

```
Etapa 1 (MVP)        ~$27/mes    ← 1 servidor, todo junto
     │
     ▼
Etapa 2 (Prod)       ~$68/mes    ← separar app/datos, 2 web servers
     │                 ~$98/mes   ← (con managed DB)
     ▼
Etapa 3 (Escala)     ~$212/mes   ← workers separados, portal, DB grande
```

---

## Que hace Forge en cada servidor

Cuando provisionas un servidor con Forge, automaticamente instala y configura:

| Componente | Configuracion |
|---|---|
| **Ubuntu 24.04** | LTS, security updates automaticos |
| **Nginx** | Optimizado para Laravel, gzip, headers de seguridad |
| **PHP 8.2** | FPM, OPcache, extensiones (bcmath, xml, openssl, mbstring, mysql) |
| **MySQL 8.0** | (si seleccionas DB en el servidor) |
| **Redis** | (si seleccionas cache/queue en el servidor) |
| **SSL** | Let's Encrypt auto-renewal |
| **Firewall** | UFW: solo 22, 80, 443 |
| **SSH** | Key-based auth, root disabled |
| **Unattended upgrades** | Security patches automaticos |

### Forge configura los workers

En el dashboard de Forge:

```
Site: api.facturacion.cr
├── Daemon: php artisan horizon
│   ├── Processes: 1
│   ├── Auto-restart: yes
│   └── User: forge
├── Scheduler: * * * * * php artisan schedule:run
└── Deploy script (auto en git push):
    cd /home/forge/api.facturacion.cr
    git pull origin main
    composer install --no-dev --optimize-autoloader
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan horizon:terminate
```

---

## Deploy con Forge

### Push to deploy (automatico)

```
git push origin main
  → Forge detecta push (GitHub webhook)
  → Ejecuta deploy script en cada servidor (rolling)
  → Zero-downtime con Envoyer ($12/mes extra) o manual
```

### Deploy script

```bash
cd /home/forge/api.facturacion.cr
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmrestart.lock

$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan horizon:terminate

echo 'Deploy complete'
```

### Zero-downtime deploy (opcional)

**Opcion 1: Laravel Envoyer** ($12/mes)
- Deploy atomico con symlinks
- Health checks post-deploy
- Rollback con un click

**Opcion 2: Manual con deploy script**
- `php artisan down --retry=60` antes de migrar
- `php artisan up` despues del deploy
- No es zero-downtime pero es simple

**Opcion 3: Load Balancer rolling**
- Sacar server 1 del LB → deploy → meter al LB
- Sacar server 2 del LB → deploy → meter al LB
- Forge no lo automatiza pero se puede scriptear

---

## Gestion de secrets

### Forge Environment (.env)

Forge tiene un editor de `.env` encriptado por sitio. Los secrets se almacenan en el servidor y no estan en git.

```env
APP_KEY=base64:...
DB_PASSWORD=...
MASTER_ENCRYPTION_KEY=...       ← para encriptar certs y creds en DB
INVOICING_WEBHOOK_SECRET=...
```

**Acceso:** Solo via dashboard de Forge (2FA habilitado) o SSH al servidor.

### Para secrets mas sensibles (certificados .p12)

Los certificados .p12 se guardan **encriptados en MySQL**, no en el filesystem. La `MASTER_ENCRYPTION_KEY` en el `.env` es el unico secret critico en disco.

Para protegerlo mas:

- **File permissions:** `chmod 600 .env`, owned by `forge` user
- **Forge 2FA:** Habilitado obligatoriamente
- **SSH keys:** Solo keys autorizadas en Forge, no passwords

---

## Monitoreo

### Forge Monitoring (incluido en plan $12)

- **Server health:** CPU, RAM, disk por servidor
- **Alertas:** Email cuando CPU > 80%, disk > 90%, etc.
- **Daemon status:** Alerta si Horizon muere

### Laravel Telescope (en la app)

Instalado en el servicio:

- Requests con payload y response
- Jobs (exitos y fallos)
- Queries SQL lentas
- Exceptions
- Logs

### Cloudflare Analytics (gratis)

- Requests por minuto
- Status codes (2xx, 4xx, 5xx)
- Trafico por pais
- Threats bloqueados

### Uptime monitoring

**Forge:** Incluye checks basicos de uptime.
**Betterstack/OhDear:** Status page publica + alertas ($0-10/mes).

### Alertas

| Alerta | Fuente | Canal |
|---|---|---|
| Servidor caido | Forge / Betterstack | Email + Slack |
| CPU > 80% | Forge | Email |
| Disk > 90% | Forge | Email |
| Horizon muerto | Forge daemon monitor | Email |
| 5xx > 5% | Cloudflare | Email |
| Certificado expirando | Cron job en la app | Email al tenant |

---

## Seguridad de red

### Con Cloudflare proxy

```
Internet → Cloudflare (proxy, WAF, DDoS, SSL edge)
  → Forge LB o servidor directo (443 only)
    → Nginx + PHP-FPM
      → MySQL (localhost o private network)
      → Redis (localhost o private network)
```

### Firewall (UFW, configurado por Forge)

**Servidor web:**

```
22/tcp    from Forge IPs only     (SSH management)
80/tcp    from Cloudflare IPs     (HTTP → redirect HTTPS)
443/tcp   from Cloudflare IPs     (HTTPS)
```

**Servidor DB (si separado):**

```
22/tcp    from Forge IPs only
3306/tcp  from web server IPs only
6379/tcp  from web server IPs only
```

### Configuracion Nginx (Forge default + ajustes)

```nginx
# Forge genera esto automaticamente, ajustar:

# Ocultar version
server_tokens off;

# Headers de seguridad
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;

# Limitar body size (excepto upload de certificados)
client_max_body_size 2m;
location /certificates {
    client_max_body_size 10m;
}

# Rate limiting basico en Nginx (complementa Cloudflare)
limit_req_zone $http_x_api_key zone=api:10m rate=60r/m;
location /receipts {
    limit_req zone=api burst=20 nodelay;
}
```

---

## CI/CD con GitHub Actions + Forge

```yaml
# .github/workflows/deploy.yml
name: Test & Deploy

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
      - name: Trigger Forge deploy
        run: |
          curl -X POST \
            "${{ secrets.FORGE_DEPLOY_URL }}" \
            --silent --show-error --fail
```

Forge genera un deploy webhook URL por sitio. GitHub Actions corre tests → si pasan → trigger deploy en Forge.

---

## Backups

| Que | Frecuencia | Retencion | Donde |
|---|---|---|---|
| MySQL dump | Diario (Forge scheduled backup) | 7 dias | Servidor local |
| MySQL dump | Semanal (script + upload) | 365 dias | Spaces/S3 |
| Server snapshot | Semanal (Hetzner) | 4 semanas | Hetzner |
| Codigo | Cada push | Indefinido | GitHub |

**Forge Backup:** Se puede configurar backup automatico de MySQL a S3/Spaces desde el dashboard.

---

## Dominios y DNS

```
api.facturacion.cr          → Cloudflare → LB / Web servers
sandbox.facturacion.cr      → Cloudflare → LB / Web servers
portal.facturacion.cr       → Cloudflare → Portal servers (Fase 3)
docs.facturacion.cr         → Cloudflare → Static site
status.facturacion.cr       → Betterstack status page
```

SSL: Let's Encrypt via Forge (auto-renewal) + Cloudflare edge SSL (Full Strict).

---

## Estimacion de capacidad

### Etapa 1 — 1 servidor CX32 (~$27/mes)

| Metrica | Capacidad |
|---|---|
| Facturas por hora | ~200-500 |
| Tenants activos | ~10-30 |
| Requests por segundo (pico) | ~20-40 |
| Almacenamiento DB (1 ano) | ~2-5 GB |

Un servidor Hetzner CX32 (4 vCPU, 8GB) corre Laravel con Horizon sin problema para este volumen.

### Etapa 2 — 2 web + 1 DB (~$68-98/mes)

| Metrica | Capacidad |
|---|---|
| Facturas por hora | ~500-2,000 |
| Tenants activos | ~30-150 |
| Requests por segundo (pico) | ~40-100 |
| Almacenamiento DB (1 ano) | ~5-20 GB |

### Etapa 3 — 3 web + 2 worker + portal (~$212/mes)

| Metrica | Capacidad |
|---|---|
| Facturas por hora | ~2,000-5,000 |
| Tenants activos | ~150-500 |
| Requests por segundo (pico) | ~100-200 |
| Almacenamiento DB (1 ano) | ~20-50 GB |

---

## Comparacion con las otras opciones

| Aspecto | Forge + Hetzner | DO App Platform | AWS Fargate |
|---|---|---|---|
| **Etapa 1** | **~$27/mes** | ~$50/mes | ~$112/mes |
| **Etapa 2** | **~$68-98/mes** | ~$106/mes | ~$206/mes |
| **Etapa 3** | ~$212/mes | ~$254/mes | ~$315+/mes |
| **Complejidad** | Baja (Forge maneja) | Baja (PaaS) | Media-Alta |
| **Docker** | No necesario | Buildpack auto | Dockerfile requerido |
| **Deploy** | `git push` + script | `git push` auto | Build + push ECR + ECS update |
| **SSH al server** | Si | No | No |
| **Laravel nativo** | 100% | 95% (buildpack) | 80% (Docker custom) |
| **Queue workers** | Forge daemon | Worker container | Fargate task |
| **Scheduler** | Cron nativo | Job scheduler | EventBridge + ECS |
| **SSL** | Let's Encrypt auto | Incluido | ACM |
| **Escalar** | Agregar servidor | Cambiar plan | Auto-scaling |

### La ventaja de Forge

Forge es el unico que te da **exactamente el mismo entorno** que tenes en Laravel Herd en local. No hay Docker de por medio, no hay buildpacks, no hay abstracciones. Es PHP-FPM + Nginx + MySQL + Redis en un servidor Ubuntu que vos podes SSH si algo falla.

### La desventaja de Forge

Escalar requiere accion manual (provisionar servidor, agregarlo al LB). No hay auto-scaling. Para la mayoria de servicios de facturacion esto no es problema — el trafico es predecible y crece gradualmente.

---

## Costos fijos de Forge

| Plan | Costo | Incluye |
|---|---|---|
| **Growth** | $12/mes | Servidores ilimitados, sitios ilimitados, business hours support |
| **+ Envoyer** | +$12/mes | Zero-downtime deploys (opcional) |

Forge cobra por cuenta, no por servidor. Si tenes 1 servidor o 20, son los mismos $12/mes.