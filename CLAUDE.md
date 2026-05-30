# CLAUDE.md — Siroko Senior Challenge
> Contrato arquitectónico y harness de ejecución para agentes de IA.
> Cualquier sugerencia que contradiga estas reglas debe ser rechazada
> y registrada en `ai/DECISIONS.md`.

---

## 0. Fuentes de Verdad — Leer Antes de Implementar

| Recurso | Propósito |
|---|---|
| **Este archivo** | Reglas de arquitectura, convenciones de código, workflow de ejecución |
| `ai/PLAN.md` | Especificación funcional completa: bounded contexts, casos de uso, flujos, sad paths |
| `docs/openapi.yaml` | **Contrato HTTP de todos los endpoints** — nombres de campos, schemas, códigos de respuesta |
| `ai/DECISIONS.md` | Decisiones arquitectónicas tomadas; no contradecirlas sin justificación escrita |

**Antes de tocar cualquier controller HTTP, leer la sección correspondiente en `docs/openapi.yaml`.
El código debe ajustarse al spec, nunca al revés.**

---

## 1. Contexto del Proyecto

API REST en **PHP 8.3 + Symfony 7** que integra dos bounded contexts:
- **Catalog + Cart + Order** (Opción A): gestión de productos, carrito y checkout.
- **Search** (Opción B): motor de búsqueda semántica con fallback a LLM.

Ambos contextos conviven en una única API. El dominio es **completamente agnóstico** de Symfony.

---

## 2. Estructura de Directorios

```
src/
├── Catalog/
│   ├── Domain/
│   │   ├── Model/            # Entidades, Value Objects, Aggregates
│   │   ├── Repository/       # Interfaces de repositorio (puertos)
│   │   ├── Service/          # Domain Services
│   │   ├── Event/            # Domain Events
│   │   └── Exception/        # Excepciones de dominio
│   ├── Application/
│   │   ├── Command/          # *Command.php (data objects) + *Handler.php (servicios)
│   │   └── Query/            # *Query.php (data objects) + *Handler.php + *DTO.php
│   └── Infrastructure/
│       ├── Persistence/
│       │   ├── Model/        # *Orm.php — modelo de persistencia (tipos primitivos)
│       │   ├── Mapping/      # *Orm.orm.xml — XML mappings de Doctrine
│       │   └── Doctrine*Repository.php
│       └── Http/             # Controllers Symfony (thin)
│
├── Cart/          # misma estructura que Catalog
├── Order/         # misma estructura que Catalog
└── Search/
    ├── Domain/
    │   └── Port/             # SemanticSearchPort, EmbeddingPort, LanguageModelPort
    ├── Application/
    └── Infrastructure/
        ├── VectorDb/         # Adaptador Qdrant
        ├── Embedding/        # Adaptador OpenAI Embeddings
        └── LlmFallback/      # Adaptadores LLM (OpenAI, Anthropic, NullAdapter)

tests/
├── Unit/                     # Pruebas de dominio puras
├── Integration/              # Use cases con SQLite in-memory
└── Acceptance/               # HTTP end-to-end

ai/
├── PLAN.md
├── DECISIONS.md
├── prompts/
└── agents/
```

---

## 3. Reglas de Dominio (NO negociables)

### 3.1 Pureza del Dominio
- **PROHIBIDO** importar Symfony, Doctrine o cualquier framework en `Domain/`.
- Las entidades son **plain PHP objects**. Sin annotations ni atributos de Doctrine/Symfony.
- Los repositorios en `Domain/Repository/` son **interfaces PHP puras**.
- Los Domain Events son **immutable value objects**.

### 3.2 Value Objects
- Todo ID es un Value Object (`ProductId`, `CartId`, `OrderId`…). NUNCA `string` o `int` directamente en entidades.
- `Money` usa enteros (céntimos). **NUNCA `float` para dinero.**
- Los Value Objects son **immutables** — sin setters; `with*()` devuelve nueva instancia.
- Validación en el constructor del Value Object, no en la entidad.

### 3.3 Entidades y Agregados
- Sin setters públicos. El estado cambia mediante **métodos de dominio** con nombres de negocio (`cart->addItem()`, `order->confirm()`).
- Los agregados controlan sus invariantes internamente.
- **No exponer colecciones mutables** — devolver arrays o colecciones de solo lectura.

### 3.4 Domain Services
- Solo cuando la lógica no pertenece a ninguna entidad ni VO concreto.
- Sin dependencias de infraestructura.

### 3.5 Domain Events
- Reflejan hechos de negocio en pasado: `ProductCreated`, `CartCheckedOut`.
- Se recolectan en el agregado con `pullDomainEvents()` y se despachan en la capa de infraestructura/aplicación.

---

## 4. Reglas de Aplicación (CQRS + Messenger)

### 4.1 Commands
- Inmutables. Representan una intención de cambio de estado.
- El Handler **puede** devolver un ID (necesario para respuesta HTTP 201); no devuelve entidades.
- Un Handler = un caso de uso. Sin lógica de negocio; orquesta dominio e infraestructura.
- Naming: `AddItemToCartCommand` / `AddItemToCartHandler`.
- Los handlers llevan `#[AsMessageHandler]` para ser autowired por Symfony Messenger.
- Los eventos de dominio se despachan **después** de `$repository->save()`, nunca antes.

### 4.2 Queries
- Inmutables. Representan una petición de datos.
- Los Query Handlers pueden acceder directamente a infraestructura (DBAL, ORM) sin pasar por el dominio.
- Devuelven DTOs, **nunca entidades de dominio**.
- Naming: `GetCartQuery` / `GetCartHandler` → devuelve `CartDTO`.

### 4.3 Buses
- `MessageBusInterface` de Symfony Messenger. Nunca instanciar handlers directamente.
- Routing por interfaces marcadoras en `messenger.yaml`:
  - `CommandInterface` → transport `sync`
  - `QueryInterface` → transport `sync`
  - `DomainEventInterface` → transport `async`

### 4.4 Exclusiones en `services.yaml`
Excluir solo los data objects, **no el directorio completo** (los handlers deben ser autowired):
```yaml
App\Catalog\:
    resource: '../src/Catalog/'
    exclude:
        - '../src/Catalog/Domain/Model/'
        - '../src/Catalog/Domain/Event/'
        - '../src/Catalog/Domain/Exception/'
        - '../src/Catalog/Domain/Repository/'
        - '../src/Catalog/Application/Command/*Command.php'   # data objects
        - '../src/Catalog/Application/Query/*Query.php'      # data objects
        - '../src/Catalog/Application/Query/*DTO.php'        # data objects
        - '../src/Catalog/Infrastructure/Persistence/Model/' # ORM models
```

---

## 5. Reglas de Infraestructura

### 5.1 Repositorios — Modelo de Persistencia Separado
- Cada BC usa una clase `*Orm` en `Infrastructure/Persistence/Model/` con tipos primitivos PHP. El dominio nunca es tocado por Doctrine.
- El repositorio Doctrine implementa la interfaz del dominio y traduce `*Orm` ↔ entidad internamente.
- Mapeo con **XML Doctrine mappings** (`Infrastructure/Persistence/Mapping/*.orm.xml`).
  - El fichero XML debe llamarse `<ClassName>Orm.orm.xml` (p.ej. `ProductOrm.orm.xml`).
  - El `doctrine.yaml` usa `prefix: 'App\<BC>\Infrastructure\Persistence\Model'`.
- `EntityManager` solo en repositorios de infraestructura, nunca en Application o Domain.

### 5.2 Puertos y Adaptadores (Search BC)
- `SemanticSearchPort`, `EmbeddingPort`, `LanguageModelPort` — interfaces en dominio.
- Los adaptadores concretos (Qdrant, OpenAI, Anthropic, Null) van en `Infrastructure/`.
- La lógica de fallback (VectorDB sin resultados → LLM) vive en **Application**, no en Infrastructure.

### 5.3 HTTP Controllers (thin)
1. Parsear y validar la request (estructura básica, campos requeridos según `docs/openapi.yaml`).
2. Construir el Command o Query.
3. `$this->bus->dispatch(...)`.
4. Serializar el resultado según `docs/openapi.yaml`.

Sin lógica de negocio. Sin acceso directo a repositorios.

**Manejo de excepciones — siempre capturar `HandlerFailedException`:**
```php
try {
    $this->bus->dispatch($command);
} catch (HandlerFailedException $e) {
    $cause = $e->getPrevious() ?? $e;
    return match (true) {
        $cause instanceof ProductNotFoundException => $this->error('product_not_found', $cause->getMessage(), 404),
        $cause instanceof InvalidPriceException    => $this->error('invalid_price', $cause->getMessage(), 422),
        default                                    => throw $e,
    };
}
```
Messenger wrappea las excepciones de los handlers en `HandlerFailedException`. Nunca capturar la excepción de dominio directamente (no llegará sin wrapping en producción).

**Formato de error estándar** (obligatorio en todos los endpoints):
```json
{
  "error": {
    "code": "error_code_snake_case",
    "message": "Human readable message.",
    "context": { "optional": "extra data" }
  }
}
```

---

## 6. Reglas de Testing

### 6.1 Unit Tests (`tests/Unit/`)
- Prueban exclusivamente clases de `Domain/`.
- **Sin mocks de dominio** — usar objetos reales o Builders.
- Sin base de datos, sin HTTP, sin filesystem.
- Verificar que los eventos de dominio se emiten con `pullDomainEvents()`.

### 6.2 Integration Tests (`tests/Integration/`)
- Extienden `IntegrationTestCase` (crea schema SQLite in-memory antes de cada test).
- Usar el helper `$this->dispatch($bus, $message)` para desenwrappear `HandlerFailedException`:
  ```php
  protected function dispatch(MessageBusInterface $bus, object $message): mixed
  {
      try {
          return $bus->dispatch($message)->last(HandledStamp::class)?->getResult();
      } catch (HandlerFailedException $e) {
          throw $e->getPrevious() ?? $e;
      }
  }
  ```
- Recuperar servicios con `self::getContainer()->get(MessageBusInterface::class)`.
- **No recuperar repositorios directamente** (se inline en el container compilado) — verificar estado vía Query handlers.
- Pueden mockear puertos externos (Qdrant, LLM APIs).

### 6.3 Acceptance Tests (`tests/Acceptance/`)
- Extienden `WebTestCase` de Symfony.
- Recrear el schema en `setUp()` con `SchemaTool`.
- Leer `ADMIN_API_KEY` del container: `self::getContainer()->getParameter('admin_api_key')`.
- Verificar que los shapes de request/response coinciden con `docs/openapi.yaml`.
- Cubrir happy paths y sad paths definidos en `ai/PLAN.md §6`.

### 6.4 Cobertura mínima
- Domain: **100%** de lógica de negocio.
- Application handlers: **100%** de happy + sad paths definidos en `ai/PLAN.md`.
- Infrastructure: cobertura razonable en repositorios; no obsesionarse con adapters externos.

---

## 7. Convenciones de Código

- PHP 8.3: usar `readonly`, `enum`, `fibers` donde aporten claridad, no por moda.
- `declare(strict_types=1)` en todos los archivos.
- Ningún método público sin tipo de retorno declarado.
- Sin `array` genérico en firmas públicas; usar colecciones tipadas o DTOs.
- Excepciones de dominio en `Domain/Exception/`, extienden `\DomainException`. Nunca `\Exception` ni `\RuntimeException` en dominio.
- Nombres en **inglés**. Comentarios y documentación en español.

---

## 8. Anti-patrones Prohibidos

| Anti-patrón | Por qué está prohibido |
|---|---|
| Doctrine Entity = Domain Entity | Acopla dominio a persistencia |
| `float` para Money | Pérdida de precisión en aritmética financiera |
| Lógica en Controllers | Viola SRP, dificulta testing |
| Repositorios genéricos `findAll()` en dominio | Fuga de infraestructura al dominio |
| `new` de servicios de infraestructura en dominio | Viola inversión de dependencias |
| Query Handlers devolviendo entidades | Expone dominio a la presentación |
| Mocks de Value Objects en tests de dominio | Los VO son simples; usar objetos reales |
| `static` en entidades | Dificulta testing y viola DI |
| Excepciones genéricas en dominio | Sin semántica de negocio |
| Excluir el directorio `Application/Command/` completo | Impide que los handlers sean autowired |
| Capturar excepciones de dominio en controllers sin capturar `HandlerFailedException` | La excepción no llegará sin wrapping |

---

## 9. Workflow de Implementación

Seguir **siempre** este orden. Nunca saltarse pasos.

```
1. Domain first       → entidad/VO/evento antes de cualquier otra cosa
2. Puerto             → interfaz en Domain/Port/ si hay dependencia externa
3. Use Case           → Command/Query + Handler (orquesta, no implementa negocio)
4. Tests unitarios    → tests/Unit/ — sin mocks, sin DB, sin HTTP
5. Tests integración  → tests/Integration/ — SQLite in-memory
6. Infraestructura    → adaptador concreto del puerto (Doctrine, HTTP client…)
7. Controller         → thin; verificar contract en docs/openapi.yaml antes de escribir
8. Tests aceptación   → tests/Acceptance/ — happy + sad paths del PLAN.md
```

---

## 10. Entorno y Comandos

```bash
# Levantar el entorno completo
docker compose up -d

# Tests
docker compose exec php bin/phpunit                          # todos
docker compose exec php bin/phpunit --testsuite Unit
docker compose exec php bin/phpunit --testsuite Integration
docker compose exec php bin/phpunit --testsuite Acceptance

# Symfony
docker compose exec php bin/console cache:clear
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php bin/console doctrine:schema:validate
docker compose exec php bin/console debug:container <NombreClase>
docker compose exec php bin/console debug:router
```

Variables de entorno en `.env.example` (nunca commitear `.env` con secretos).

---

## 11. Contrato OpenAPI — Shapes de Request/Response

Leer `docs/openapi.yaml` para el contrato completo. Shapes críticos:

**Money** — siempre objeto anidado, nunca campos planos:
```json
{ "amount": 4999, "currency": "EUR" }
```

**`POST /api/products`** request: `{ name, price: Money, category, brand, stock, description?, attributes?, imageUrl? }`
**`POST /api/products`** response 201: `{ "productId": "uuid" }`

**`GET /api/products`** response 200:
```json
{ "data": [ ProductDTO… ], "pagination": { "page", "pageSize", "total", "totalPages" } }
```

**`GET /api/products/{id}`** response 200 — `ProductDTO`:
```json
{ "id", "name", "description", "price": Money, "category", "brand", "attributes", "stock", "imageUrl", "createdAt", "updatedAt" }
```

**`GET /api/carts/{id}`** response 200 — `CartDTO`:
```json
{ "id", "customerId", "items": [ { "productId", "productName", "unitPrice": Money, "quantity", "subtotal": Money } ], "total": Money, "createdAt", "updatedAt" }
```

**`GET /api/orders/{id}`** response 200 — `OrderDTO`:
```json
{ "id", "customerId", "lines": [ { "id", "productId", "productName", "unitPrice": Money, "quantity", "subtotal": Money } ], "total": Money, "status", "shippingAddress": { "street", "city", "postalCode", "country" }, "createdAt" }
```

**`GET /api/search`** response 200:
```json
{ "results": [ { "productId", "name", "category", "brand", "score?" } ], "meta": { "results_count", "fallback_used", "fallback_available", "fallback_error?", "filters_extracted?" } }
```

---

## 12. Errores Frecuentes a Evitar

| Error | Causa | Solución |
|---|---|---|
| `No handler for message "..."` | Handler en directorio excluido de `services.yaml` | Excluir solo `*Command.php`, `*Query.php`, `*DTO.php` — no el directorio completo |
| `No mapping file found named 'X.orm.xml'` | XML no coincide con el nombre de la clase | Nombrar `<ClassName>Orm.orm.xml` |
| `Could not find service "test.service_container"` | Kernel no arranca en env `test` | Pasar `['environment' => 'test', 'debug' => true]` a `bootKernel()` |
| Excepción de dominio no capturada en controller | Messenger wrappea en `HandlerFailedException` | Capturar `HandlerFailedException` y usar `$e->getPrevious()` |
| Tests de integración conectan a PostgreSQL | `dbname_suffix` en `doctrine.yaml` when@test | Usar `url: '%env(resolve:DATABASE_URL)%'` en `when@test` |
| `ServiceNotFoundException` al recuperar repositorio en tests | El alias se inline al compilar | Recuperar `MessageBusInterface::class`, no el repositorio |

---

## 13. Checklist Pre-Entrega

- [ ] `bin/phpunit` verde en los 3 suites (Unit, Integration, Acceptance).
- [ ] `doctrine:schema:validate` devuelve OK en mapping y database.
- [ ] Shapes de request/response coinciden con `docs/openapi.yaml`.
- [ ] Sad paths del `ai/PLAN.md §6` tienen test de integración o aceptación.
- [ ] Ningún import de Symfony/Doctrine en `Domain/`.
- [ ] Ninguna excepción genérica (`\Exception`, `\RuntimeException`) en dominio.
- [ ] Decisiones arquitectónicas nuevas registradas en `ai/DECISIONS.md`.
