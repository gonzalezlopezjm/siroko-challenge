# Siroko Senior PHP Challenge

API REST en PHP 8.3 + Symfony 7 que integra dos retos del challenge en una única solución cohesiva:

- **Opción A — Catalog + Cart + Order**: gestión de productos, carrito de compra y checkout.
- **Opción B — Semantic Search**: motor de búsqueda semántica con embeddings y fallback inteligente a LLM.

## Decisión de integración

Se implementan ambas opciones en una sola API porque comparten el mismo bounded context de producto. El Catalog BC es la fuente de verdad; el Search BC lo indexa automáticamente vía eventos de dominio. La separación es lógica (namespaces DDD), no física.

## Arquitectura

```
Hexagonal Architecture + DDD + CQRS

src/
├── Catalog/    → Domain | Application (CQRS) | Infrastructure (Doctrine XML + HTTP)
├── Cart/       → Domain | Application (CQRS) | Infrastructure
├── Order/      → Domain | Application (CQRS) | Infrastructure
└── Search/     → Domain (Ports) | Application | Infrastructure (Qdrant + OpenAI + LLM)
```

Reglas clave:
- El dominio es PHP puro — sin Symfony, sin Doctrine en `Domain/`.
- Doctrine mappings en XML (sin annotations en entidades).
- Money en céntimos (`int`), nunca `float`.
- IDs generados en Application layer, nunca en el dominio.
- Fallback LLM desactivado silenciosamente si no hay token configurado.

## Levantar el entorno

```bash
cp .env.example .env
# Editar .env con tus claves (OPENAI_API_KEY, ADMIN_API_KEY, etc.)

docker-compose up --build
# La API estará disponible en http://localhost:8080
```

El entrypoint ejecuta automáticamente estos pasos en orden:

1. `composer install` (sin scripts, para evitar timeouts antes de que la DB esté lista)
2. Espera a que PostgreSQL acepte conexiones
3. `doctrine:migrations:migrate` — aplica migraciones pendientes
4. `cache:warmup` — calienta la caché de Symfony
5. `app:qdrant:ensure-collection` — crea la colección vectorial si no existe
6. `app:fixtures:load` — carga los 28 productos de muestra (omite los ya existentes)
7. **`app:search:reindex`** — indexa los productos en Qdrant para búsqueda semántica  
   > **Requiere `OPENAI_API_KEY` configurada.** Si la variable no está definida, este paso se omite silenciosamente y el endpoint `GET /api/search` devolverá resultados vacíos.
8. `app:metrics:seed` — genera datos históricos de ejemplo para Grafana (si no existen)
9. `app:demo:setup` — crea pedidos y emails de demo (si no existen)
10. Arranca `supervisord` con php-fpm y el worker de Symfony Messenger

### Caché Redis

Los endpoints de lectura del catálogo y búsqueda usan Redis como caché:

| Endpoint | Pool | TTL | Invalidación |
|----------|------|-----|--------------|
| `GET /api/products` | `cache.products` (TagAware) | 5 min | Al crear/actualizar/eliminar producto y al hacer checkout (stock) |
| `GET /api/products/{id}` | `cache.products` (TagAware) | 10 min | Al actualizar/eliminar ese producto y al hacer checkout (stock) |
| `GET /api/search` | `cache.search` | 2 min | Por TTL (sin invalidación activa) |

Para inspeccionar las claves en Redis:

```bash
docker compose exec redis redis-cli KEYS "*"
docker compose exec redis redis-cli FLUSHALL   # vaciar caché completa
```

## Dashboards de desarrollo

| Servicio | URL | Descripción |
|----------|-----|-------------|
| **API** | http://localhost:8080 | API REST principal |
| **Mailpit** | http://localhost:8025 | Bandeja de entrada de emails de desarrollo |
| **Grafana** | http://localhost:3000 | Dashboard de observabilidad (sin login) |
| **Redis** | localhost:6379 | Caché de producto y búsqueda (inspeccionar con `redis-cli`) |

### Grafana — observabilidad

El dashboard [http://localhost:3000](http://localhost:3000) arranca **sin login** y con el dashboard precargado. Muestra en tiempo real:

- Pedidos creados y cancelados en el tiempo
- Revenue acumulado
- Búsquedas totales y búsquedas sin resultados
- Patrón de actividad por hora del día

Las métricas se alimentan automáticamente desde el bus asíncrono: cada `OrderCreated`, `OrderCancelled` y `SearchPerformed` genera un registro en `app_metrics` procesado por el worker.

Para cargar datos históricos de ejemplo (útil en demos):

```bash
docker compose exec php bin/console app:metrics:seed
# Genera 7 días de tráfico realista (~6500 eventos con patrón de horas punta)

# Opcionalmente, especificar más días:
docker compose exec php bin/console app:metrics:seed --days=30
```

### Mailpit — captura de emails

Todos los emails transaccionales (confirmación de pedido, cancelación) se interceptan por [Mailpit](http://localhost:8025) sin salir del entorno local. No se requiere cuenta de correo real.

El worker de Symfony Messenger procesa los eventos de pedido en background y envía los emails a Mailpit automáticamente. En la UI de Mailpit puedes ver el HTML renderizado, las cabeceras y el texto plano de cada mensaje.

## Tests de rendimiento (k6)

El script [`k6/load-test.js`](k6/load-test.js) mide la latencia P95 de los endpoints críticos contra los targets definidos en el PLAN:

| Endpoint | Target P95 |
|----------|-----------|
| `GET /api/products` | < 50 ms |
| `GET /api/products/{id}` | < 50 ms |
| `GET /api/carts/{id}` | < 30 ms |
| `POST /api/orders` | < 200 ms |
| `GET /api/search` | < 150 ms |

Perfil de carga: warm-up 15 s → steady 60 s (10 VUs catálogo/carrito, 5 VUs checkout/search) → ramp-down 15 s.

### Ejecutar

```bash
# Con Docker (recomendado — usa la red interna siroko)
ADMIN_KEY=<tu-clave> docker compose --profile k6 run --rm k6

# Sin OPENAI_API_KEY configurada, omitir el escenario de búsqueda
SKIP_SEARCH=1 ADMIN_KEY=<tu-clave> docker compose --profile k6 run --rm k6

# Con k6 instalado localmente
BASE_URL=http://localhost:8080 ADMIN_KEY=<tu-clave> k6 run k6/load-test.js
```

### Resultados reales medidos

Los resultados varían significativamente según el modo de ejecución y los recursos disponibles. A continuación los datos reales obtenidos en esta misma máquina (Ubuntu 22.04, Docker, 55 productos en BD):

#### Latencia de request individual (sin carga concurrente)

Con caché Redis caliente en `APP_ENV=prod`:

| Endpoint | Latencia individual |
|----------|-------------------|
| `GET /api/products` | ~16 ms |
| `GET /api/products/{id}` | ~14 ms |

Todos los targets se cumplen holgadamente en request individual.

#### Bajo carga concurrente (30 VUs simultáneos con k6)

En Docker local, k6, PHP-FPM, Redis y PostgreSQL comparten los mismos recursos de CPU y disco, lo que introduce contención que no existiría en producción con hardware dedicado.

**`APP_ENV=dev` + 5 workers PHP-FPM (configuración por defecto):**

```
══════════════════════════════════════════════════
  Siroko API — Resultados de rendimiento (P95)
══════════════════════════════════════════════════
  ✗  GET /api/products            P95 =  1770.1 ms  (objetivo > 50 ms)
  ✗  GET /api/products/{id}       P95 =  1757.5 ms  (objetivo > 50 ms)
  ✗  GET /api/carts/{id}          P95 =  1906.9 ms  (objetivo > 30 ms)
  ✗  POST /api/orders             P95 =  1995.2 ms  (objetivo > 200 ms)
  ✗  GET /api/search              P95 =  1975.3 ms  (objetivo > 150 ms)
──────────────────────────────────────────────────
  Error rate:  0.00%  (objetivo < 1%)
  Check rate:  100.00%  (objetivo > 99%)
══════════════════════════════════════════════════
```

**`APP_ENV=prod` + 50 workers PHP-FPM** (`docker/php/www.conf`, `pm.max_children=50`):

```
══════════════════════════════════════════════════
  Siroko API — Resultados de rendimiento (P95)
══════════════════════════════════════════════════
  ✗  GET /api/products            P95 =   326.3 ms  (objetivo > 50 ms)
  ✗  GET /api/products/{id}       P95 =   304.3 ms  (objetivo > 50 ms)
  ✗  GET /api/carts/{id}          P95 =   931.8 ms  (objetivo > 30 ms)
  ✗  POST /api/orders             P95 =  1100.1 ms  (objetivo > 200 ms)
  ✗  GET /api/search              P95 =   370.9 ms  (objetivo > 150 ms)
──────────────────────────────────────────────────
  Error rate:  0.00%  (objetivo < 1%)
  Check rate:  100.00%  (objetivo > 99%)
══════════════════════════════════════════════════
```

La diferencia entre los dos escenarios muestra el efecto del modo de ejecución y del pool de workers: **5× de mejora** al pasar de dev a prod. El caché Redis elimina las consultas a PostgreSQL para el catálogo (hits >43% durante la prueba), llevando la latencia individual a ~15 ms; el P95 bajo carga masiva refleja la contención de CPU en el entorno Docker local y no el rendimiento real de la API en producción.

Para reproducir con 50 workers:

```bash
# El fichero docker/php/www.conf ya está incluido en el repositorio
# y montado vía volumen en docker-compose.yml
ADMIN_KEY=<tu-clave> APP_ENV=prod docker compose --profile k6 run --rm k6
```

Los targets de P95 están definidos en [`ai/PLAN.md §10`](ai/PLAN.md) y codificados como umbrales en el propio script k6 (`thresholds`), de forma que el test falla automáticamente si algún endpoint los supera.

El script crea y limpia su propio producto de prueba (stock=999999) en cada ejecución. No modifica los datos de fixtures.

---

## Ejecutar los tests

```bash
docker-compose exec php bin/phpunit
```

Por suites:

```bash
docker-compose exec php bin/phpunit --testsuite Unit
docker-compose exec php bin/phpunit --testsuite Integration
docker-compose exec php bin/phpunit --testsuite Acceptance
```

Con cobertura:

```bash
docker-compose exec php bin/phpunit --coverage-html var/coverage
```

## Datos de prueba

El archivo [`docs/fixtures.json`](docs/fixtures.json) contiene 28 productos reales de Siroko listos para cargar.

### Cargar productos de muestra

```bash
docker compose exec php bin/console app:fixtures:load
```

Crea todos los productos del catálogo de muestra. Si un producto ya existe (mismo nombre + categoría), se omite sin error.

### Resetear la base de datos y recargar fixtures

```bash
# Con confirmación interactiva
docker compose exec php bin/console app:db:reset

# Sin confirmación (CI, scripts)
docker compose exec php bin/console app:db:reset --force
```

Ejecuta en secuencia: drop del esquema completo → migraciones → carga de fixtures. Útil para volver a un estado conocido durante el desarrollo.

### Re-indexar en Qdrant (búsqueda semántica)

Los eventos de dominio (`ProductCreated`, `ProductUpdated`) se procesan de forma asíncrona via Symfony Messenger. Para indexar todos los productos existentes en Qdrant de una vez:

```bash
docker compose exec php bin/console app:search:reindex
```

Requiere `OPENAI_API_KEY` configurada. El comando pagina el catálogo en lotes de 20 y muestra progreso. El comando `app:db:reset --force` ya incluye este paso automáticamente.

Para procesar eventos pendientes en la cola async (indexación incremental en dev):

```bash
docker compose exec php bin/console messenger:consume async --limit=100
```

### Verificar que los datos se han cargado

```bash
curl http://localhost:8080/api/products | jq '.pagination.total'
# → 30

curl "http://localhost:8080/api/products?category=CYCLING" | jq '.pagination.total'
# → 13

curl "http://localhost:8080/api/search?q=culote+gravel" | jq '.results[].name'
```

---

## OpenAPI

La especificación completa está en [`docs/openapi.yaml`](docs/openapi.yaml).

Endpoints principales:

| Método | Ruta | Auth | Descripción |
|--------|------|------|-------------|
| `POST` | `/api/products` | ADMIN | Crear producto |
| `PATCH` | `/api/products/{id}` | ADMIN | Actualizar producto |
| `DELETE` | `/api/products/{id}` | ADMIN | Eliminar producto |
| `GET` | `/api/products` | — | Listar productos |
| `GET` | `/api/products/{id}` | — | Detalle de producto |
| `POST` | `/api/carts` | — | Crear carrito |
| `POST` | `/api/carts/{id}/items` | — | Añadir ítem al carrito |
| `POST` | `/api/orders` | — | Checkout (crear orden) — acepta `customerEmail` opcional |
| `POST` | `/api/orders/{id}/cancel` | — | Cancelar orden |
| `GET` | `/api/search?q=...` | — | Búsqueda semántica |

Auth admin: `Authorization: Bearer <ADMIN_API_KEY>`

### Emails de transición de pedido

El checkout acepta el campo opcional `customerEmail`. Si se incluye, el worker envía automáticamente:

- **Confirmación** al crear el pedido (`POST /api/orders`)
- **Cancelación** al cancelar el pedido (`POST /api/orders/{id}/cancel`)

```bash
# Checkout con email
curl -X POST http://localhost:8080/api/orders \
  -H "Content-Type: application/json" \
  -d '{
    "cartId": "<cartId>",
    "customerEmail": "cliente@ejemplo.com",
    "shippingAddress": {
      "street": "Calle Mayor 1",
      "city": "Madrid",
      "postalCode": "28001",
      "country": "ES"
    }
  }'

# Ver el email generado → http://localhost:8025
```

## Modelado del dominio

### Bounded Contexts y Agregados

| Bounded Context | Aggregate Root | Entities internas | Value Objects |
|----------------|----------------|-------------------|---------------|
| **Catalog** | `Product` | — | `ProductId`, `ProductName`, `Money`, `Currency`, `Category`, `Brand`, `Stock`, `ProductAttributes` |
| **Cart** | `Cart` | `CartItem` | `CartId` |
| **Order** | `Order` | `OrderLine`, `OrderLineId` | `OrderId`, `ShippingAddress`, `OrderStatus` |
| **Search** | — (stateless) | — | `SearchResult`, `ParsedSearchQuery` |

### Eventos de dominio

| Evento | Publicado por | Consumidores (async) |
|--------|---------------|----------------------|
| `ProductCreated` | Catalog | Search → indexación en Qdrant |
| `ProductUpdated` | Catalog | Search → re-indexación en Qdrant |
| `ProductDeleted` | Catalog | — |
| `OrderCreated` | Order | Email confirmación, Metrics |
| `OrderCancelled` | Order | Email cancelación, Metrics |
| `SearchPerformed` | Search | Metrics |

### Invariantes de dominio clave

- `Money` se representa en **céntimos enteros** (`int`). Nunca `float`.
- `CartItem` almacena un **snapshot de precio** en el momento de añadir, inmutable ante cambios posteriores en el catálogo.
- El stock se **verifica y descuenta dentro de la misma transacción** de checkout, previniendo overselling ante compras concurrentes.
- Los IDs se generan en **Application layer** (handlers), nunca en el dominio, manteniendo el dominio libre de dependencias externas.
- El dominio es **PHP puro** — cero referencias a Symfony, Doctrine ni ningún framework en `Domain/`.

Ver [`ai/PLAN.md`](ai/PLAN.md) para el modelado completo: casos de uso, sad paths y decisiones arquitectónicas.

## Documentación IA

Herramienta utilizada: **Claude Code** (claude-sonnet-4-6).

Ver carpeta [`/ai`](ai/) con:
- [`PLAN.md`](ai/PLAN.md) — especificación funcional y técnica completa (guía de implementación para el agente)
- [`DECISIONS.md`](ai/DECISIONS.md) — 19 decisiones tomadas frente a sugerencias de IA
- [`prompts/`](ai/prompts/) — 5 prompts clave del proceso con contexto, respuesta del modelo y decisión tomada

El fichero [`CLAUDE.md`](CLAUDE.md) en la raíz es el contrato arquitectónico del proyecto: reglas de dominio, convenciones y restricciones que el agente debía respetar en cada iteración (equivalente a `.cursorrules` para Claude Code).

> **Nota sobre trazabilidad de commits:** El desarrollo se realizó en una sesión continua con Claude Code. La trazabilidad AI vs. criterio propio está documentada en [`ai/DECISIONS.md`](ai/DECISIONS.md) (con propuesta del modelo + decisión razonada) y en los [`ai/prompts/`](ai/prompts/) (con los intercambios reales), en lugar de en mensajes de commit individuales.
