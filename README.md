# Sneaker Factory — Proyecto Intermodular 2.º DAW

Plataforma web de catálogo, venta y personalización de zapatillas con
panel interno de gestión, trazabilidad de eventos y despliegue
reproducible sobre Docker.

Proyecto intermodular del **Grupo 3 (2.º DAW)** que integra **Desarrollo
Web en Entorno Servidor (DWES)**, **Desarrollo Web en Entorno Cliente
(DWEC)** y **Despliegue de Aplicaciones Web (DAW)** sobre una única
aplicación y base de datos compartida.

------------------------------------------------------------------------

## Índice

1.  [Arquitectura y contrato técnico
    intermodular](#1-arquitectura-y-contrato-técnico-intermodular)
2.  [Estructura del repositorio](#2-estructura-del-repositorio)
3.  [Requisitos previos](#3-requisitos-previos)
4.  [Instalación y puesta en marcha](#4-instalación-y-puesta-en-marcha)
5.  [Base de datos, esquema y credenciales de
    prueba](#5-base-de-datos-esquema-y-credenciales-de-prueba)
6.  [Contrato de la API REST](#6-contrato-de-la-api-rest)
7.  [Operación, observabilidad y
    mantenimiento](#7-operación-observabilidad-y-mantenimiento)
8.  [Copias de seguridad, restauración y
    actualizaciones](#8-copias-de-seguridad-restauración-y-actualizaciones)
9.  [Guía rápida de diagnóstico de
    incidencias](#9-guía-rápida-de-diagnóstico-de-incidencias)
10. [Documentación adicional](#10-documentación-adicional)
11. [Avisos críticos antes de la
    entrega](#11-avisos-críticos-antes-de-la-entrega)

------------------------------------------------------------------------

## 1. Arquitectura y contrato técnico intermodular

La aplicación se despliega en tres contenedores aislados orquestados
mediante Docker Compose (`compose.yml`), con arranque escalonado
mediante `depends_on` (`condition: service_healthy`) y política
`restart: unless-stopped`.

| Servicio  | Contenedor        | Imagen                                       | Puertos                                           | Redes                            | Función                                                                                  |
|:----------|:------------------|:---------------------------------------------|:--------------------------------------------------|:---------------------------------|:-----------------------------------------------------------------------------------------|
| `web`     | `sneaker_web`     | `nginx:1.27-alpine`                          | `8081:80` (301 → HTTPS), `8443:443` (TLS 1.2/1.3) | `frontend_net`                   | Proxy inverso, terminación TLS, archivos estáticos (`/frontend`) e imágenes (`/assets/`) |
| `backend` | `sneaker_backend` | `sneaker-backend:1.0` (`php:8.2-fpm-alpine`) | `9000/tcp` (solo interno)                         | `frontend_net`, `backend_net`    | API REST en PHP 8.2 nativa (sin frameworks ni Composer), ejecutada como `www-data`       |
| `db`      | `sneaker_db`      | `postgres:15-alpine`                         | `5432/tcp` (solo interno, no publicado)           | `backend_net` (`internal: true`) | Base de datos relacional PostgreSQL 15 con volumen persistente `postgres_data`           |

### Flujo de una petición

``` text
Navegador / curl
  └─▶ Nginx (sneaker_web :8081 HTTP 301 / :8443 HTTPS)
        ├─ Estáticos (/frontend, /assets/)
        └─ FastCGI (frontend_net :9000) ──▶ PHP-FPM (sneaker_backend)
                                              ├─ Router → Middlewares (Auth / CSRF / Role)
                                              ├─ Controllers → Services → Repositories (PDO)
                                              └─ TCP (backend_net :5432, internal: true) ──▶ PostgreSQL (sneaker_db)
```

### Segmentación de red y seguridad base

- **`frontend_net`:** conecta únicamente `sneaker_web` y
  `sneaker_backend`.
- **`backend_net` (`internal: true`):** aislada de Internet; conecta
  solo `sneaker_backend` y `sneaker_db`. `sneaker_web` no puede resolver
  ni alcanzar `db:5432`, y el puerto 5432 no se publica en el host.
- **Contenedor sin privilegios:** `sneaker_backend` ejecuta PHP-FPM bajo
  el usuario `www-data`.
- **Cortafuegos recomendado (servidor Linux):** UFW con política por
  defecto `deny incoming` / `allow outgoing`, permitiendo `8081/tcp`,
  `8443/tcp` y `22/tcp` restringido a la subred de administración.

## 2. Estructura del repositorio

``` text
sneaker_factory-main/
├── compose.yml              # Orquestación de servicios, redes, volúmenes y healthchecks
├── .env.example             # Plantilla de variables de entorno (sin secretos reales)
├── assets/
│   └── img/                 # Imágenes de catálogo (10 modelos)
├── backend/
│   ├── Dockerfile           # Imagen propia PHP 8.2-FPM + pdo_pgsql (usuario www-data)
│   ├── config/              # Config.php y Database.php (PDO)
│   ├── public/              # index.php (front controller) y health.php
│   └── src/
│       ├── Core/             # Router, Request, Response, Validator, ErrorHandler, Logger, Autoloader
│       ├── Middlewares/      # AuthMiddleware, CsrfMiddleware, RoleMiddleware
│       ├── Controllers/      # 8 controladores HTTP
│       ├── Services/         # Lógica de negocio (Auth, Catalog, Order, Report, Audit)
│       ├── Repositories/     # Acceso a datos con sentencias preparadas PDO
│       ├── Models/           # Objetos de dominio inmutables (readonly)
│       └── Routes/api.php    # Definición centralizada de endpoints y permisos
├── data/
│   ├── 01_schema.sql        # Esquema relacional (8 tablas, restricciones e índices)
│   └── 02_seed.sql          # Datos iniciales reproducibles
├── frontend/                # Interfaz HTML5, CSS3 y JS Vanilla multipágina
├── nginx/
│   ├── default.conf         # Configuración de proxy inverso, TLS, logs y cabeceras
│   └── certs/               # Certificados TLS locales (generados en despliegue; no versionar .key)
└── scripts/
    ├── deploy.sh            # Puesta en marcha automatizada desde cero
    ├── check.sh             # Verificación de salud y recursos
    ├── backup.sh            # Copia consistente de BD + configuración con retención (5 copias)
    └── restore.sh           # Restauración en base aislada o producción con confirmación
```

## 3. Requisitos previos

- **Linux (recomendado Ubuntu Server)** o entorno compatible con Bash.
- **Docker Engine** y **Docker Compose Plugin** (`docker compose`).
- **OpenSSL** (generación automática del certificado TLS de laboratorio)
  y **curl** (comprobaciones de salud y pruebas).

## 4. Instalación y puesta en marcha

### 4.1. Despliegue automatizado (recomendado)

``` bash
git clone <URL_DEL_REPOSITORIO> sneaker_factory
cd sneaker_factory
chmod +x scripts/*.sh
./scripts/deploy.sh
```

El script `scripts/deploy.sh` realiza automáticamente:

1.  Comprobación de prerrequisitos (`docker`, `docker compose`,
    `openssl`, `curl`) y disponibilidad de puertos.
2.  Creación de `.env` a partir de `.env.example` si no existe.
    **Sustituye `DB_PASSWORD` por una contraseña segura en cualquier
    entorno fuera del laboratorio.**
3.  Generación de un certificado TLS autofirmado con SAN
    (`DNS:sneaker.local`, `DNS:localhost`, `IP:127.0.0.1`) si no existe
    en `nginx/certs/`.
4.  Construcción y levantamiento de los tres contenedores sin eliminar
    volúmenes (`docker compose up -d --build`).
5.  Espera activa hasta que los tres contenedores alcancen el estado
    `healthy`.
6.  Ejecución de `./scripts/check.sh` y creación de una primera copia de
    seguridad.

### 4.2. Variables de entorno (`.env`)

Copia `.env.example` a `.env` antes de levantar manualmente los
servicios. El archivo `.env` está excluido en `.gitignore` y **nunca
debe subirse al repositorio**.

| Variable       | Valor por defecto     | Descripción                                                |
|:---------------|:----------------------|:-----------------------------------------------------------|
| `PORT`         | `8081`                | Puerto HTTP publicado por Nginx (redirige con 301 a HTTPS) |
| `DB_HOST`      | `db`                  | Nombre del servicio PostgreSQL en `backend_net`            |
| `DB_PORT`      | `5432`                | Puerto interno de PostgreSQL                               |
| `DB_NAME`      | `sneaker_db`          | Nombre de la base de datos                                 |
| `DB_USER`      | `sneaker_user`        | Usuario de conexión a PostgreSQL                           |
| `DB_PASSWORD`  | *(definir en `.env`)* | Contraseña de PostgreSQL (obligatoria)                     |
| `APP_ENV`      | `production`          | Entorno de ejecución de la aplicación (opcional)           |
| `APP_TIMEZONE` | `Europe/Madrid`       | Zona horaria de PHP (opcional)                             |

### 4.3. Resolución de nombre local y aviso sobre HTTPS/TLS

Al ser un entorno de laboratorio sin dominio público registrado, se
utiliza el nombre local `sneaker.local`. Añade la entrada
correspondiente al archivo `hosts` de cada equipo cliente que acceda a
la aplicación.

**Linux / macOS** (`/etc/hosts`):

``` text
<IP_DEL_SERVIDOR> sneaker.local
```

**Windows** (`C:\Windows\System32\drivers\etc\hosts`, como
administrador):

``` powershell
Add-Content -Path C:\Windows\System32\drivers\etc\hosts -Value "`n<IP_DEL_SERVIDOR> sneaker.local"
```

**Acceso a la aplicación:**

- Portal web: <https://sneaker.local:8443> (o <https://localhost:8443>
  en local).
- Redirección HTTP → HTTPS: <http://sneaker.local:8081>.
- Estado del servicio: <https://sneaker.local:8443/health> y
  <https://sneaker.local:8443/health.php>.

**Limitación de TLS en laboratorio:** el certificado generado por
`deploy.sh` es autofirmado para pruebas (validez de 375 días). El
tráfico viaja cifrado con TLS 1.2/1.3, pero el navegador mostrará una
advertencia de seguridad porque el certificado no está emitido por una
Autoridad Certificadora pública.

## 5. Base de datos, esquema y credenciales de prueba

Al arrancar por primera vez con el volumen `postgres_data` vacío,
PostgreSQL ejecuta automáticamente los scripts montados en
`/docker-entrypoint-initdb.d`:

1.  **`data/01_schema.sql`:** crea las 8 tablas (`roles`, `usuarios`,
    `modelos`, `variantes_modelo`, `pedidos`, `pedido_lineas`,
    `pedido_historial_estados`, `auditoria_eventos`), claves foráneas,
    restricciones `CHECK`, columnas generadas (`subtotal`), campos
    `JSONB` e índices.
2.  **`data/02_seed.sql`:** inserta datos ficticios reproducibles: 3
    roles, 12 usuarios (1 administrador, 1 usuario de almacén y 10
    clientes), 10 modelos de 10 marcas, 30 variantes (incluyendo
    variantes con stock bajo y agotado), 20 pedidos que cubren los 5
    estados (6 personalizados en `JSONB`), 24 líneas, historial de
    estados y eventos de auditoría.

### Cuentas ficticias de prueba

Todas las cuentas de prueba utilizan la contraseña **`Sneaker2026!`**,
almacenada con hash bcrypt mediante `pgcrypto` y compatible con
`password_verify()` de PHP.

| Rol       | Email                                                            | Permisos principales                                                                                                               |
|:----------|:-----------------------------------------------------------------|:-----------------------------------------------------------------------------------------------------------------------------------|
| `admin`   | `admin@sneakerfactory.local`                                     | Gestión completa de catálogo (alta y baja lógica), stock, pedidos, informes, exportación, importación y auditoría                  |
| `almacen` | `almacen@sneakerfactory.local`                                   | Edición de modelos, variantes y stock; importación masiva; gestión de pedidos y cambios de estado; dashboard, ventas y exportación |
| `cliente` | `lucia.fernandez@ejemplo.test` (y demás cuentas `@ejemplo.test`) | Consulta de catálogo, gestión del perfil propio, creación, consulta y cancelación de pedidos propios                               |

> **Importante:** son credenciales ficticias para pruebas. No reutilices
> esta contraseña en un entorno real.

## 6. Contrato de la API REST

Todas las respuestas JSON siguen uno de estos formatos estándar:

**Respuesta correcta:**

``` json
{
  "ok": true,
  "data": {},
  "meta": {}
}
```

**Respuesta de error:**

``` json
{
  "ok": false,
  "error": {
    "code": "...",
    "message": "...",
    "details": {}
  }
}
```

La autenticación se gestiona mediante cookies de sesión endurecidas
(`HttpOnly`, `SameSite=Lax` y `Secure` en HTTPS) y protección CSRF
mediante la cabecera `X-CSRF-Token` en las operaciones de escritura.

### Endpoints disponibles

| Método        | Ruta                                 | Acceso                 | CSRF      | Descripción                                                     |
|:--------------|:-------------------------------------|:-----------------------|:----------|:----------------------------------------------------------------|
| `GET`         | `/health`, `/api/health`             | Público                | No        | Estado de la aplicación y conectividad con PostgreSQL           |
| `POST`        | `/api/auth/register`                 | Público                | No        | Registro de una nueva cuenta de cliente                         |
| `POST`        | `/api/auth/login`                    | Público                | No        | Inicio de sesión (emite cookie de sesión y `csrf_token`)        |
| `POST`        | `/api/auth/logout`                   | Autenticado            | Sí        | Cierre y destrucción de sesión                                  |
| `GET`         | `/api/auth/me`                       | Autenticado            | No        | Datos del usuario autenticado y `csrf_token` actual             |
| `GET` / `PUT` | `/api/profile`                       | Autenticado            | `PUT`: Sí | Consulta y edición del perfil propio y contraseña               |
| `GET`         | `/api/modelos`                       | Público                | No        | Catálogo paginado con filtros combinables y ordenación          |
| `GET`         | `/api/modelos/{id}`                  | Público                | No        | Detalle de modelo con sus variantes y disponibilidad            |
| `GET`         | `/api/pedidos`                       | Autenticado            | No        | Listado de pedidos propios (o globales si es personal)          |
| `GET`         | `/api/pedidos/{id}`                  | Autenticado            | No        | Detalle de pedido (control anti-IDOR en servidor)               |
| `POST`        | `/api/pedidos`                       | Autenticado            | Sí        | Alta transaccional de pedido multilínea (bloqueo `FOR UPDATE`)  |
| `PATCH`       | `/api/pedidos/{id}/cancelar`         | Propietario            | Sí        | Cancelación de pedido propio en estado pendiente o confirmado   |
| `GET`         | `/api/admin/modelos`                 | Administrador, almacén | No        | Catálogo interno, incluidos modelos inactivos                   |
| `POST`        | `/api/admin/modelos`                 | Solo administrador     | Sí        | Alta de un nuevo modelo                                         |
| `PUT`         | `/api/admin/modelos/{id}`            | Administrador, almacén | Sí        | Modificación de un modelo existente                             |
| `DELETE`      | `/api/admin/modelos/{id}`            | Solo administrador     | Sí        | Baja lógica de modelo (`activo = FALSE`)                        |
| `POST`        | `/api/admin/modelos/{id}/variantes`  | Administrador, almacén | Sí        | Alta de variante (talla, color y stock)                         |
| `PATCH`       | `/api/admin/variantes/{id}/stock`    | Administrador, almacén | Sí        | Ajuste manual de existencias                                    |
| `GET`         | `/api/admin/pedidos`                 | Administrador, almacén | No        | Listado global de pedidos                                       |
| `GET`         | `/api/admin/pedidos/{id}`            | Administrador, almacén | No        | Detalle de cualquier pedido                                     |
| `PATCH`       | `/api/admin/pedidos/{id}/estado`     | Administrador, almacén | Sí        | Cambio de estado según máquina de transiciones                  |
| `GET`         | `/api/admin/pedidos/exportar`        | Administrador, almacén | No        | Exportación real de pedidos (`?formato=csv` o `?formato=json`)  |
| `GET`         | `/api/admin/dashboard`               | Administrador, almacén | No        | KPIs: pedidos por estado, unidades vendidas y stock crítico     |
| `GET`         | `/api/admin/informes/ventas-modelos` | Administrador, almacén | No        | Informe de ventas e ingresos por modelo (JOIN y agregación)     |
| `GET`         | `/api/admin/informes/clientes`       | Solo administrador     | No        | Ranking de mejores clientes por gasto y pedidos válidos         |
| `POST`        | `/api/admin/stock/importar`          | Administrador, almacén | Sí        | Importación masiva validada de stock en transacción todo o nada |
| `GET`         | `/api/admin/auditoria`               | Solo administrador     | No        | Consulta del registro de auditoría de eventos                   |

### Reproducción de una petición completa (navegador/curl → Nginx → PHP-FPM → PostgreSQL)

Los siguientes ejemplos utilizan `curl -k` para aceptar el certificado
autofirmado del entorno de laboratorio. En un entorno real debe
utilizarse un certificado de confianza y evitarse `-k`.

**1. Comprobar la salud del servicio y la conexión con PostgreSQL:**

``` bash
curl -k https://sneaker.local:8443/health
```

**2. Iniciar sesión como cliente** (guarda la cookie de sesión y
devuelve `csrf_token`):

``` bash
curl -k -c cookies.txt -X POST https://sneaker.local:8443/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"lucia.fernandez@ejemplo.test","password":"Sneaker2026!"}'
```

**3. Crear un pedido multilínea** (sustituye `<CSRF_TOKEN>` por el valor
recibido en el paso anterior):

``` bash
curl -k -b cookies.txt -X POST https://sneaker.local:8443/api/pedidos \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: <CSRF_TOKEN>" \
  -d '{
    "direccion_envio": "Av. de la Constitución 12, Sevilla",
    "notas": "Prueba de integración",
    "lineas": [
      {"variante_id": 1, "cantidad": 1},
      {
        "variante_id": 4,
        "cantidad": 2,
        "opciones_personalizacion": {
          "color_cordones": "azul",
          "texto_talon": "LUCIA"
        }
      }
    ]
  }'
```

## 7. Operación, observabilidad y mantenimiento

### Comandos habituales de administración

``` bash
# Estado y healthchecks de los contenedores
docker compose ps

# Verificación integral (web, /health.php, API, PostgreSQL, CPU/RAM/disco)
./scripts/check.sh

# Logs en tiempo real (Nginx, backend y base de datos)
docker compose logs -f --tail=50
tail -f logs/nginx/access.log logs/nginx/error.log

# Consumo de recursos, redes, volúmenes e imágenes
docker stats --no-stream
docker images sneaker-backend
docker network inspect sneaker_factory_backend_net
docker volume inspect sneaker_factory_postgres_data

# Parada controlada (conserva los datos de PostgreSQL)
docker compose down
```

### Diferencia entre reiniciar, reconstruir y borrar volúmenes

| Acción                             | Comando                             | Efecto sobre el servicio y los datos                                                                                                                                      |
|:-----------------------------------|:------------------------------------|:--------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Reiniciar contenedores**         | `docker compose restart [servicio]` | Reinicia procesos existentes sin cambiar la imagen ni borrar datos.                                                                                                       |
| **Reconstruir imágenes**           | `docker compose up -d --build`      | Compila de nuevo el backend/Dockerfile y recrea contenedores conservando `postgres_data`.                                                                                 |
| **Borrar volúmenes (destructivo)** | `docker compose down -v`            | Elimina contenedores y el volumen `postgres_data`. Al volver a arrancar se reejecutan `01_schema.sql` y `02_seed.sql`, perdiendo todos los pedidos y cambios posteriores. |

## 8. Copias de seguridad, restauración y actualizaciones

### Copias de seguridad automatizadas

- **Ejecución manual:** `./scripts/backup.sh`.
- **Contenido:** volcado lógico consistente (`pg_dump --no-owner`) y
  archivo `.tar.gz` con la configuración crítica (`compose.yml`,
  `.env.example`, `nginx/default.conf`, `backend/Dockerfile`, `scripts/`
  y `data/`).
- **Registro y retención:** guarda la trazabilidad en
  `backups/backup.log` y conserva automáticamente las **últimas 5
  copias**.
- **Programación diaria con cron (02:00 h):**

``` cron
0 2 * * * /ruta/al/proyecto/scripts/backup.sh >> /ruta/al/proyecto/backups/cron.log 2>&1
```

### Restauración segura

``` bash
# Restauración en una base de datos aislada de pruebas (sneaker_restore_test): comportamiento por defecto
./scripts/restore.sh backups/sneaker_db_YYYYMMDD_HHMMSS.sql

# Restauración sobre producción: genera un backup preventivo y pide teclear RESTAURAR
./scripts/restore.sh backups/sneaker_db_YYYYMMDD_HHMMSS.sql sneaker_db
```

### Actualización conservando datos y reversión (rollback)

``` bash
# 1. Respaldo y etiqueta de la versión estable actual
./scripts/backup.sh
git tag v1.0

# 2. Actualizar código y reconstruir sin eliminar volúmenes
git pull
docker compose build backend
docker compose up -d
./scripts/check.sh

# 3. En caso de fallo, revertir a la versión estable
git checkout v1.0
docker compose build backend
docker compose up -d
./scripts/check.sh
```

## 9. Guía rápida de diagnóstico de incidencias

| Síntoma                                     | Causa habitual                                                           | Comprobación y solución                                                                                                                              |
|:--------------------------------------------|:-------------------------------------------------------------------------|:-----------------------------------------------------------------------------------------------------------------------------------------------------|
| **502 Bad Gateway en Nginx**                | `sneaker_backend` está detenido o no responde en `:9000`.                | Revisar `docker compose ps` y `docker compose logs backend`; levantar con `docker compose up -d backend`.                                            |
| **500 o `"database": "down"` en `/health`** | `sneaker_db` está detenido o las credenciales son incorrectas en `.env`. | Verificar `docker compose logs db`, comprobar `.env` y ejecutar `docker compose start db`.                                                           |
| **403 `CSRF_INVALID`**                      | Falta la cabecera `X-CSRF-Token` o ha caducado la sesión.                | Obtener el token actualizado desde `GET /api/auth/me` o iniciar una nueva sesión mediante `POST /api/auth/login`.                                    |
| **404 en archivos CSS/estáticos en Linux**  | Diferencia entre mayúsculas y minúsculas en los nombres de archivo.      | Comprobar que las rutas enlazadas en `frontend/*.html` coinciden exactamente con los nombres reales en disco.                                        |
| **Aviso «No es seguro» en el navegador**    | Certificado autofirmado de laboratorio.                                  | Es el comportamiento esperado con un certificado local; confirmar que el SAN coincide con `sneaker.local` y continuar solo en el entorno de pruebas. |

## 10. Documentación adicional

La especificación detallada de arquitectura, esquema E/R, matriz de
permisos, evidencias de pruebas funcionales y de infraestructura,
auditoría técnica e incidencias se encuentra en la **Memoria Técnica del
Proyecto Intermodular**, entregada junto al repositorio.
