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

El entrypoint ejecuta automáticamente: `composer install` → migraciones → warmup de caché → creación de colección Qdrant.

## Dashboards de desarrollo

| Servicio | URL | Descripción |
|----------|-----|-------------|
| **API** | http://localhost:8080 | API REST principal |
| **Mailpit** | http://localhost:8025 | Bandeja de entrada de emails de desarrollo |
| **Grafana** | http://localhost:3000 | Dashboard de observabilidad (sin login) |

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

Ver [`ai/PLAN.md`](ai/PLAN.md) para el modelado completo de entidades, Value Objects, agregados, eventos y casos de uso.

## Documentación IA

Ver carpeta [`/ai`](ai/) con:
- `PLAN.md` — especificación funcional y técnica
- `DECISIONS.md` — decisiones tomadas frente a sugerencias de IA
- `prompts/` — prompts clave del proceso
