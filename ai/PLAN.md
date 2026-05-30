# PLAN.md — Siroko Senior Challenge
> Especificación funcional y técnica para guiar la implementación con IA.
> Versión: DRAFT v0.3

---

## 0. Contrato de API — Fuente de Verdad

**`docs/openapi.yaml`** es la especificación OpenAPI 3.1 que define los contratos HTTP de todos los endpoints.
Es la fuente de verdad para:
- Nombres de campos en request bodies y responses
- Códigos de estado HTTP y estructuras de error
- Esquemas reutilizables (`Money`, `ProductDTO`, `CartDTO`, `OrderDTO`, `SearchResponse`…)

> Cualquier discrepancia entre el código y el spec debe resolverse **adaptando el código al spec**, no al revés.
> Ver `ai/DECISIONS.md` #011 para el razonamiento.

**Aspectos clave del contrato que deben respetarse en todos los BCs:**

| Endpoint | Entrada relevante | Salida relevante |
|---|---|---|
| `POST /api/products` | `price: { amount, currency }` (objeto anidado) | `{ productId }` |
| `PATCH /api/products/{id}` | `price: { amount, currency }` (opcional) | 204 |
| `GET /api/products` | query params: `category`, `brand`, `page`, `pageSize` | `{ data: ProductDTO[], pagination }` |
| `GET /api/products/{id}` | — | `ProductDTO` |
| `POST /api/carts` | `{ customerId? }` | `{ cartId }` |
| `GET /api/carts/{id}` | — | `CartDTO` con `items`, `total` |
| `POST /api/carts/{id}/items` | `{ productId, quantity }` | 204 |
| `POST /api/orders` | `{ cartId, customerId?, shippingAddress }` | `{ orderId }` |
| `GET /api/orders/{id}` | — | `OrderDTO` |
| `GET /api/search?q=` | `q`, `limit` | `{ results: SearchResultDTO[], meta: SearchMeta }` |

---

## 1. Visión del Proyecto

API REST que integra **dos bounded contexts** sobre el dominio de una tienda de deporte:

- **Catalog / Cart / Order**: permite gestionar el catálogo de productos, añadir artículos a un carrito y completar una compra generando una orden.
- **Search**: motor de búsqueda semántica que entiende la intención del usuario (no palabras clave exactas), con fallback a LLM cuando el vector search no produce resultados de calidad.

La integración de ambos contextos en una sola API es una decisión explícita: el catálogo de productos es compartido y la búsqueda semántica indexa los mismos productos que se pueden añadir al carrito.

---

## 2. Bounded Contexts y Responsabilidades

### 2.1 Catalog BC
**Responsabilidad**: CRUD del catálogo de productos. Source of truth del inventario.

**Entidades y Value Objects:**
```
Product (Aggregate Root)
  ├── ProductId          (UUID v4, inmutable)
  ├── ProductName        (string, 1-255 chars, non-empty)
  ├── ProductDescription (string, 0-2000 chars)
  ├── Price              (Money: amount en céntimos int + Currency enum)
  ├── Category           (enum: CYCLING | FITNESS | APPAREL | ACCESSORIES)
  ├── Brand              (string, 1-100 chars, non-empty)
  ├── Attributes         (map: color[], size[], material[], ... extensible)
  ├── Stock              (int >= 0)
  ├── ImageUrl           (nullable, URL válida)
  └── CreatedAt / UpdatedAt (DateTimeImmutable)
```

> `Brand` y `Attributes` se añaden como campos de primera clase para soportar
> el filtrado estructurado que genera el LLM en el fallback de búsqueda.

**Domain Events:**
- `ProductCreated`  → dispara indexación automática en Search BC
- `ProductUpdated`  → dispara re-indexación automática en Search BC
- `ProductDeleted`  → dispara eliminación del índice vectorial
- `StockUpdated`

**Reglas de negocio:**
- El precio NO puede ser negativo ni cero.
- El stock NO puede ser negativo.
- Un producto eliminado NO puede añadirse al carrito.
- El nombre es único dentro de una misma categoría (invariante de dominio).

---

### 2.2 Cart BC
**Responsabilidad**: gestión del carrito de compra. Estado transitorio antes del checkout.

**Entidades y Value Objects:**
```
Cart (Aggregate Root)
  ├── CartId             (UUID v4)
  ├── CustomerId         (UUID v4 o "guest-{uuid}" para anónimos)
  ├── items: CartItem[]
  └── CreatedAt / UpdatedAt

CartItem (Entity dentro del agregado)
  ├── CartItemId         (UUID v4)
  ├── ProductId          (referencia al Catalog BC, solo el ID)
  ├── ProductSnapshot    (nombre + precio en el momento de añadir)
  ├── Quantity           (int >= 1)
  └── UnitPrice          (Money: precio unitario en el momento de añadir)
```

**Reglas de negocio:**
- Un carrito puede tener como máximo **50 líneas** distintas.
- Añadir el mismo producto dos veces incrementa la cantidad, no duplica la línea.
- La cantidad por línea no puede superar **99 unidades**.
- El `ProductSnapshot` se toma en el momento de añadir (inmutable en el carrito). Si el precio del producto cambia en el catálogo, el carrito conserva el precio original.
- Un carrito sin actividad durante **30 días** puede ser eliminado (TTL, no se modela en dominio puro).
- No se puede añadir un producto con `stock = 0`.

**Domain Events:**
- `ItemAddedToCart`
- `ItemRemovedFromCart`
- `ItemQuantityUpdated`
- `CartCleared`

---

### 2.3 Order BC
**Responsabilidad**: procesar el checkout y persistir órdenes.

**Entidades y Value Objects:**
```
Order (Aggregate Root)
  ├── OrderId            (UUID v4)
  ├── CustomerId         (UUID v4)
  ├── lines: OrderLine[]
  ├── TotalAmount        (Money)
  ├── OrderStatus        (enum: PENDING | CONFIRMED | CANCELLED)
  ├── ShippingAddress    (VO: street, city, postalCode, country)
  └── CreatedAt

OrderLine (Entity dentro del agregado)
  ├── OrderLineId        (UUID v4)
  ├── ProductId
  ├── ProductName        (snapshot)
  ├── Quantity
  └── UnitPrice          (Money)
```

**Reglas de negocio:**
- Una orden se crea siempre en estado `PENDING`.
- El total se calcula sumando `quantity * unitPrice` de cada línea.
- La orden se puede confirmar (`CONFIRMED`) o cancelar (`CANCELLED`) pero no revertir a `PENDING`.
- No se puede crear una orden con carrito vacío.
- **En el momento de crear la orden, se verifica el stock real de cada producto contra el Catalog BC. Si cualquier línea supera el stock disponible, la operación falla con `insufficient_stock` y NO se crea la orden (atomicidad total).**
- Al crear la orden con éxito, se descuenta el stock de cada producto del Catalog BC dentro de la misma transacción.

**Domain Events:**
- `OrderCreated`
- `OrderConfirmed`
- `OrderCancelled`

---

### 2.4 Search BC
**Responsabilidad**: búsqueda semántica de productos.

**Puertos (interfaces en dominio):**
```
EmbeddingPort
  └── embed(text: string): float[]

SemanticSearchPort
  └── search(queryVector: float[], limit: int, threshold: float): SearchResult[]

SemanticEnrichmentPort
  └── enrich(name, description, category, brand, attributes): SemanticEnrichment
```

**Value Objects:**
```
SearchQuery    (string, 1-500 chars)
SearchResult   (productId: ProductId, score: float)

SemanticEnrichment (generado por LLM en el momento de indexación, §14.3):
  ├── estilo?: string[]
  ├── ocasion?: string[]
  ├── estetica?: string[]
  ├── mood?: string[]
  ├── temporada?: string[]
  ├── ajuste?: string
  ├── silueta?: string
  └── percepcion_material?: string[]

ProductIndexData (datos enviados a Qdrant + payload):
  └── id, name, description, category, brand, attributes (enriquecidos)
```

**Flujo de búsqueda (Application Layer):**
```
1. Recibir SearchQuery
2. Normalizar query con expansión de sinónimos en español
3. Generar embedding de la query normalizada (EmbeddingPort)
4. Buscar en VectorDB (SemanticSearchPort) con threshold=0.75, limit=10
5. Aplicar filtro de tipo de producto cuando la query contiene una palabra de tipo
   específico (e.g. "calcetines", "camiseta") — elimina resultados semánticamente
   cercanos pero de tipo incorrecto
6. Devolver resultados ordenados por score. Si no hay resultados → []
```

**Reglas de negocio:**
- El threshold de similitud es configurable vía env var (`SEARCH_SIMILARITY_THRESHOLD=0.75`).
- El número máximo de resultados es configurable (`SEARCH_MAX_RESULTS=10`).
- La indexación de productos es **automática**: los eventos `ProductCreated` y `ProductUpdated` disparan el `IndexProductHandler` vía Symfony Messenger. No existe endpoint de indexación manual.
- En el momento de indexación, `SemanticEnrichmentPort` enriquece el documento con metadatos semánticos generados por LLM (estilo, ocasión, estética…). Si no está configurado, la indexación prosigue sin enriquecimiento.

**Domain Events:**
- `ProductIndexed`
- `SearchPerformed` (con metadata: query, results_count)

---

## 3. Seguridad y Autenticación

### 3.1 Endpoints protegidos (requieren autenticación)
Los endpoints de escritura del catálogo son operaciones de **administración** y requieren autenticación mediante **API Key** en la cabecera `Authorization: Bearer <token>`.

```
POST   /api/products        → requiere auth (rol ADMIN)
PATCH  /api/products/{id}   → requiere auth (rol ADMIN)
DELETE /api/products/{id}   → requiere auth (rol ADMIN)
```

### 3.2 Endpoints públicos (sin autenticación)
```
GET    /api/products         → público
GET    /api/products/{id}    → público
POST   /api/carts            → público (carrito anónimo)
GET    /api/carts/{id}       → público
POST   /api/carts/{id}/items → público
PATCH  /api/carts/{id}/items/{productId} → público
DELETE /api/carts/{id}/items/{productId} → público
DELETE /api/carts/{id}/items → público
POST   /api/orders           → público
GET    /api/orders/{id}      → público
POST   /api/orders/{id}/cancel → público
GET    /api/search           → público
```

### 3.3 Implementación
- `Symfony Security` con un `ApiKeyAuthenticator` custom.
- API Key almacenada en `.env` (`ADMIN_API_KEY`).
- Sin JWT ni usuarios en base de datos: scope reducido para la prueba.
- Respuesta 401 si el token falta o es inválido; 403 si el rol no es suficiente.

---

## 4. Casos de Uso

### 4.1 Catalog

| Caso de Uso | Tipo | Auth | Input | Output |
|---|---|---|---|---|
| CreateProduct | Command | ADMIN | name, description, price, currency, category, brand, attributes?, stock, imageUrl? | productId |
| UpdateProduct | Command | ADMIN | productId, campos opcionales | void |
| DeleteProduct | Command | ADMIN | productId | void |
| GetProduct | Query | — | productId | ProductDTO |
| ListProducts | Query | — | category?, brand?, page, pageSize | ProductDTO[] + pagination |

### 4.2 Cart

| Caso de Uso | Tipo | Input | Output |
|---|---|---|---|
| CreateCart | Command | customerId? | cartId |
| AddItemToCart | Command | cartId, productId, quantity | void |
| UpdateItemQuantity | Command | cartId, productId, quantity | void |
| RemoveItemFromCart | Command | cartId, productId | void |
| ClearCart | Command | cartId | void |
| GetCart | Query | cartId | CartDTO (con líneas y totales) |

### 4.3 Order

| Caso de Uso | Tipo | Input | Output |
|---|---|---|---|
| Checkout | Command | cartId, customerId, shippingAddress | orderId |
| GetOrder | Query | orderId | OrderDTO |
| CancelOrder | Command | orderId | void |

### 4.4 Search (interno)

| Caso de Uso | Tipo | Trigger | Output |
|---|---|---|---|
| IndexProduct | Command | Evento `ProductCreated` / `ProductUpdated` | void |
| RemoveProductFromIndex | Command | Evento `ProductDeleted` | void |
| SearchProducts | Query | HTTP GET /api/search | SearchResultDTO[] + meta |

---

## 5. Flujos Detallados

### 5.1 Happy Path: Checkout completo
```
POST /api/products  [ADMIN]  → 201 { productId }   (crear producto con stock=10)
POST /api/carts              → 201 { cartId }
POST /api/carts/{id}/items   → 204                  (añadir 2 unidades del producto)
GET  /api/carts/{id}         → 200 { items[], total }
POST /api/orders             → 201 { orderId }       (verifica stock OK, descuenta 2)
GET  /api/orders/{id}        → 200 { status: PENDING, lines[], total }
```

### 5.2 Sad Path: Stock insuficiente en checkout
```
POST /api/products  [ADMIN]  → 201 { productId }   (stock=1)
POST /api/carts              → 201 { cartId }
POST /api/carts/{id}/items   → 204                  (añadir 3 unidades — se permite en carrito)
POST /api/orders             → 422 {
  error: {
    code: "insufficient_stock",
    message: "Product 'Mallot Mizuno' only has 1 unit(s) in stock, 3 requested.",
    context: { productId: "...", available: 1, requested: 3 }
  }
}
```

### 5.3 Happy Path: Búsqueda semántica con resultado directo
```
# Producto ya indexado automáticamente al crearlo
GET /api/search?q=camiseta+térmica+roja → 200 {
  results: [{ productId, name, score: 0.92 }, ...],
  meta: { results_count: 5 }
}
```

### 5.4 Happy Path: Auto-indexación al crear producto
```
POST /api/products [ADMIN] → 201 { productId }
# Internamente: ProductCreated event → IndexProductHandler (async via Messenger)
# → EmbeddingPort.embed(name + description + brand + attributes)
# → SemanticSearchPort.upsert(productId, vector, payload)
# Transparente para el cliente HTTP
```

---

## 6. Sad Paths y Errores

| Escenario | HTTP | Código de error |
|---|---|---|
| Producto no encontrado | 404 | `product_not_found` |
| Carrito no encontrado | 404 | `cart_not_found` |
| Orden no encontrada | 404 | `order_not_found` |
| Producto sin stock (añadir a carrito) | 422 | `insufficient_stock` |
| **Stock insuficiente en checkout** | **422** | **`insufficient_stock`** |
| Carrito vacío en checkout | 422 | `cart_is_empty` |
| Precio negativo o cero | 422 | `invalid_price` |
| Cantidad fuera de rango (0 o >99) | 422 | `invalid_quantity` |
| Límite de 50 líneas en carrito | 422 | `cart_lines_limit_exceeded` |
| Orden ya cancelada | 409 | `order_already_cancelled` |
| Query de búsqueda vacía | 422 | `invalid_search_query` |
| Fallo en embedding API | 503 | `embedding_service_unavailable` |
| Sin resultados vectoriales | 200 | `[]` con `results_count: 0` |
| Sin autenticación en endpoint admin | 401 | `unauthorized` |
| Token inválido en endpoint admin | 401 | `invalid_token` |

**Formato de error estándar:**
```json
{
  "error": {
    "code": "insufficient_stock",
    "message": "Product 'Mallot Mizuno' only has 1 unit(s) in stock, 3 requested.",
    "context": { "productId": "123e4567-...", "available": 1, "requested": 3 }
  }
}
```

---

## 7. Decisiones Arquitectónicas

### 7.1 Un solo repositorio, dos contextos
Los bounded contexts comparten el mismo repositorio Git y el mismo proceso Symfony. La separación es **lógica** (namespaces, carpetas), no física (microservicios). Justificación: el alcance de la prueba no justifica la complejidad operacional de microservicios, y la comunicación entre contextos es simple.

### 7.2 Comunicación entre Bounded Contexts
- Cart BC referencia productos solo por `ProductId` (no importa `Product` del Catalog BC).
- Al hacer checkout, el `CheckoutHandler` consulta el Catalog BC via `ProductRepository` para verificar y descontar stock en la misma transacción.
- La indexación en Search BC se dispara vía `ProductCreated` / `ProductUpdated` events procesados por Symfony Messenger (desacoplado pero en el mismo proceso).

### 7.3 Vector DB: Qdrant
Elegido sobre `pgvector` porque:
- Propósito específico → mejor rendimiento en búsqueda ANN.
- Docker image oficial ligera.
- Cliente HTTP compatible con cualquier HTTP client PHP (no requiere librería nativa).
- Más fácil de sustituir por otro vector store en el futuro (el puerto lo abstrae).

### 7.4 LLM para enriquecimiento en indexación, no en búsqueda
El `SemanticEnrichmentPort` enriquece el documento semántico de cada producto en el momento de indexación (async, vía Messenger) con metadatos generados por LLM: estilo, ocasión, estética, mood, temporada, silueta, percepción del material. Ventajas:
- El coste LLM se paga una vez por producto, no en cada búsqueda.
- Si `LANGUAGE_MODEL_API_KEY` no está configurada, la indexación prosigue sin enriquecimiento (`NullSemanticEnrichmentAdapter`). No se lanza excepción.
- Intercambiable: cambiar de proveedor LLM no afecta al flujo de búsqueda.

### 7.5 Indexación automática vía eventos
No existe endpoint `/index`. Los eventos de dominio `ProductCreated` y `ProductUpdated` son procesados por Symfony Messenger, que ejecuta `IndexProductHandler` de forma desacoplada. Ventajas: el cliente HTTP no espera la indexación (que puede tardar ~500ms por la llamada a embedding API), y la lógica de indexación no contamina el flujo de creación de producto.

### 7.6 Money como enteros (céntimos)
`Price` almacena el valor en céntimos como `int`. Nunca `float`. La representación decimal es responsabilidad del DTO/serialización.

### 7.7 IDs como UUIDs v4
Todos los IDs son UUID v4 generados en la capa de aplicación (el Handler genera el ID antes de crear la entidad). El dominio acepta el ID en el constructor; no lo genera internamente.

---

## 8. Contratos de API (resumen)

```
# Catalog — Admin (requiere Authorization: Bearer <ADMIN_API_KEY>)
POST   /api/products              → CreateProduct           [ADMIN]
PATCH  /api/products/{id}         → UpdateProduct           [ADMIN]
DELETE /api/products/{id}         → DeleteProduct           [ADMIN]

# Catalog — Público
GET    /api/products              → ListProducts
GET    /api/products/{id}         → GetProduct

# Cart — Público
POST   /api/carts                 → CreateCart
GET    /api/carts/{id}            → GetCart
POST   /api/carts/{id}/items      → AddItemToCart
PATCH  /api/carts/{id}/items/{productId} → UpdateItemQuantity
DELETE /api/carts/{id}/items/{productId} → RemoveItemFromCart
DELETE /api/carts/{id}/items      → ClearCart

# Order — Público
POST   /api/orders                → Checkout
GET    /api/orders/{id}           → GetOrder
POST   /api/orders/{id}/cancel    → CancelOrder

# Search — Público
GET    /api/search?q={query}&limit={n} → SearchProducts
```

---

## 9. Stack Técnico

| Capa | Tecnología |
|---|---|
| Lenguaje | PHP 8.3 |
| Framework | Symfony 7 |
| ORM | Doctrine ORM 3 (solo en Infrastructure) |
| Bus | Symfony Messenger |
| Vector DB | Qdrant (HTTP API) |
| Embeddings | OpenAI `text-embedding-3-small` |
| LLM Enrichment | OpenAI GPT-4o-mini (enriquecimiento semántico en indexación, opcional) |
| Base de datos | PostgreSQL 16 |
| Testing | PHPUnit 11 |
| Contenedores | Docker + docker-compose |
| Docs API | OpenAPI 3.1 (`docs/openapi.yaml`) |

---

## 10. Performance: Métricas Objetivo

| Endpoint | P95 objetivo | Estrategia |
|---|---|---|
| `GET /products` | < 50ms | Índice en category + brand; paginación cursor |
| `GET /carts/{id}` | < 30ms | Sin joins innecesarios; cart completo en una query |
| `POST /orders` (checkout) | < 200ms | Transacción única; stock check + update en misma TX |
| `GET /search` (vector) | < 150ms | Qdrant en local; embedding cacheado si mismo query |
| `GET /search` (LLM fallback) | < 2s | LLM solo procesa la query, no el catálogo |

Las métricas se medirán con `wrk` o `k6` en el entorno Docker y se incluirá un resumen en el README.

---

# Semantic Search Architecture

> Esta sección describe la arquitectura de recuperación semántica para evolucionar el motor de búsqueda actual hacia un sistema híbrido de producción orientado a e-commerce de moda en español.

---

## 11. Filosofía de Búsqueda

La búsqueda en moda no es una búsqueda por palabras clave. Un usuario no busca un SKU ni una referencia exacta: describe lo que siente, lo que quiere proyectar, o el contexto en el que lo usará.

Los patrones de búsqueda típicos en moda incluyen:

- **Estilo y estética**: `"estilo old money"`, `"look minimalista"`, `"ropa aesthetic"`, `"outfit urbano"`.
- **Ocasión e intención**: `"para salir por la noche"`, `"para oficina sin corbata"`, `"ropa cómoda de casa"`.
- **Ajuste y silueta**: `"corte recto"`, `"ajuste slim"`, `"oversized"`, `"tiro alto"`.
- **Inspiración**: `"ropa tipo Zara pero más cara"`, `"estilo escandinavo"`.
- **Descriptivo conceptual**: `"sudadera oversized minimalista"`, `"camiseta básica beige"`, `"zapatillas urbanas"`.

La búsqueda por coincidencia exacta de palabras clave falla en todos estos casos: el catálogo usa terminología normalizada (`sudadera`, `azul marino`, `corte regular`) mientras el usuario expresa intención (`hoodie oscuro clásico`). El motor de búsqueda debe cerrar ese gap semántico.

La búsqueda semántica no reemplaza al keyword search: lo complementa. El sistema híbrido permite que ambas estrategias contribuyan proporcionalmente al resultado final.

---

## 12. Recuperación Semántica con Prioridad en Español

### 12.1 Spanish-first semantic retrieval

El catálogo está principalmente en español. Los usuarios buscan principalmente en español. Esto condiciona toda la arquitectura:

- Los modelos de embedding deben tener calidad semántica alta en español, no solo en inglés.
- Las pipelines de normalización priorizan terminología de moda española.
- El enriquecimiento semántico se genera y valida en español para preservar coherencia.
- Los sinónimos, el vocabulario controlado y los descriptores de estilo se definen en español.

El soporte multilingüe es deseable (usuarios que mezclan inglés y español: `"camiseta básica white"`, `"oversized hoodie gris"`) pero el español es el objetivo primario de optimización.

Modelos English-only como `text-embedding-ada-002` no son aptos como modelo principal: producen degradación semántica en español que se traduce directamente en resultados peores.

---

## 13. Arquitectura de Recuperación Híbrida

El sistema combina cuatro capas de recuperación:

```
BM25 Keyword Search
+
Semantic Vector Search
+
Metadata Filters
+
Reranking
=
Final Results
```

### Rol de cada capa

| Capa | Responsabilidad | Fortaleza |
|---|---|---|
| **BM25** | Coincidencia exacta de términos | Precisión en búsquedas literales: marcas, referencias, colores exactos |
| **Semantic Vector** | Similitud conceptual | Recupera productos relevantes aunque no coincidan las palabras exactas |
| **Metadata Filters** | Restricciones estructuradas | Limita el espacio de búsqueda antes de retrieval: categoría, talla, precio, stock |
| **Reranking** | Optimización de relevancia final | Reordena los candidatos priorizando los más relevantes para la intención concreta |

La búsqueda semántica sola no es suficiente: el semantic drift hace que modelos de embedding a veces recuperen productos plausibles pero incorrectos. BM25 ancla los resultados en coincidencias léxicas. Los filtros de metadatos eliminan candidatos estructuralmente incorrectos antes de pagar el coste de comparar vectores. El reranking corrige el orden final.

---

## 14. Pipeline de Preprocesamiento de Datos

La calidad del retrieval depende directamente de la calidad de los datos indexados. Un modelo de embedding excelente sobre datos mal normalizados produce resultados mediocres. Esta sección describe las transformaciones que deben aplicarse **antes** de generar embeddings.

### 14.1 Normalización de Atributos del Catálogo

Antes de indexar, cada producto pasa por una normalización que estandariza los campos clave:

**Títulos y nombres:**
- Capitalización consistente: `SUDADERA BÁSICA` → `Sudadera básica`.
- Eliminación de términos irrelevantes para el embedding: códigos de referencia, sufijos internos.
- Deduplicación de términos repetidos: `Camiseta camiseta blanca` → `Camiseta blanca`.

**Colores:**
- Mapeo a vocabulario controlado de colores:
  ```
  navy        → azul marino
  beige claro → beige
  crema       → beige
  off-white   → blanco roto
  khaki       → kaki
  ```

**Atributos de ajuste:**
- Normalización de variantes ortográficas y anglicismos:
  ```
  oversize   → oversized
  slim fit   → slim
  regular fit → regular
  straight    → recto
  ```

**Categorías:**
- Mapeo a taxonomía interna controlada antes de indexar. Las variaciones externas (`pantalones`, `pants`, `bottoms`) se resuelven a categorías canónicas.

El objetivo de la normalización no es simplificar el dato, sino eliminar la fragmentación semántica: que `navy` y `azul marino` no sean dos clusters de embedding distintos.

### 14.2 Normalización de Terminología de Moda en Español

La moda mezcla anglicismos, términos en español y variantes regionales. Para maximizar la coherencia semántica del índice, se aplica un mapa de sinónimos durante la indexación **y** durante el procesamiento de queries:

```
sudadera     ↔  hoodie, jersey, sweatshirt
zapatillas   ↔  sneakers, deportivas, tenis
vaqueros     ↔  jeans, pantalones vaqueros, denim
chaqueta     ↔  jacket, americana, blazer
pantalón     ↔  pants, trousers
camiseta     ↔  t-shirt, tee, top básico
abrigo       ↔  coat, sobretodo
vestido      ↔  dress
falda        ↔  skirt
```

La expansión de sinónimos ocurre en dos momentos distintos con propósitos distintos:

- **Durante la indexación**: el documento semántico generado para cada producto incorpora variantes normalizadas para aumentar la cobertura del embedding.
- **Durante el procesamiento de queries**: la query del usuario se normaliza antes de embeber, para que `"zapatillas urbanas"` y `"sneakers urbanas"` produzcan vectores cercanos.

### 14.3 Enriquecimiento Semántico

Los datos brutos del catálogo (nombre, descripción, color, talla) son insuficientes para recuperación semántica de alta calidad en moda. Falta la capa de significado que sí captura un experto: el estilo, la ocasión, el humor estético.

El enriquecimiento semántico genera metadatos adicionales por producto que no existen en el catálogo original:

```json
{
  "style":     ["streetwear", "minimal", "casual"],
  "fit":       "oversized",
  "occasion":  ["casual", "urban", "weekend"],
  "aesthetic": ["clean", "modern", "understated"],
  "silhouette":"boxy",
  "season":    ["otoño", "invierno", "primavera"],
  "mood":      ["relajado", "urbano"],
  "material_perception": ["suave", "cálido", "grueso"]
}
```

Este enriquecimiento puede generarse mediante:

- **Pipelines LLM**: se envía el producto al modelo con un prompt estructurado; el modelo devuelve JSON con los campos semánticos. Requiere coste por producto pero escala con el catálogo, no con las búsquedas.
- **Modelos de clasificación entrenados**: más rápido y predecible en producción, pero requiere datos de entrenamiento etiquetados.
- **Enriquecimiento basado en reglas**: para casos simples (categoría CYCLING → season incluye cualquier estación; color negro → aesthetic incluye `clásico`, `versátil`).

El enriquecimiento no es un añadido opcional: es lo que permite que `"camiseta tipo Uniqlo"` recupere productos con aesthetic `minimal`, `japonés`, `clean` aunque ninguna de esas palabras aparezca en el catálogo.

### 14.4 Generación del Documento Semántico

El embedding **no se genera a partir del título del producto**. El título solo es la punta del iceberg semántico. En su lugar, se construye un documento semántico ad hoc por producto que combina todos los campos relevantes:

```
{campo normalizado} + descripción + tags generados + metadatos de estilo + descriptores de ajuste + contexto de uso
```

Ejemplo:

```
Sudadera oversized minimalista con estética streetwear limpia.
Algodón grueso de gramaje alto.
Corte urbano relajado inspirado en moda contemporánea japonesa.
Ocasión: uso casual, salidas urbanas, fin de semana.
Colores: negro, gris marengo.
Estilo: minimal, streetwear, clean.
```

Este texto generado es la fuente del embedding. No el título `"Sudadera básica negra talla L"`.

La longitud del documento semántico debe mantenerse dentro del contexto del modelo de embedding (generalmente 512 tokens). Para documentos largos, la estrategia preferida es el promedio ponderado de chunks, no el truncado.

---

## 15. Query Understanding

Antes del retrieval, la query del usuario se analiza para extraer información estructurada que enriquece la búsqueda.

### 15.1 Extracción de entidades de búsqueda

Una query como:

```
"sudadera negra oversized por menos de 60 euros"
```

Debería extraer:

```json
{
  "category":  "sudadera",
  "color":     "negro",
  "fit":       "oversized",
  "max_price": 60,
  "currency":  "EUR"
}
```

Las entidades extraídas se convierten en **filtros de metadatos** que se aplican antes del retrieval vectorial para reducir el espacio de búsqueda. Esto mejora tanto la precisión como la latencia.

### 15.2 Transformaciones de la query

La query pasa por las siguientes transformaciones antes de embeber:

1. **Normalización de sinónimos**: `zapatillas` → `sneakers / zapatillas`, `vaqueros` → `jeans / vaqueros`.
2. **Expansión de vocabulario controlado**: `basic` → `básico`, `hoodie` → `sudadera`.
3. **Corrección tipográfica**: `sudarea` → `sudadera`, `oversied` → `oversized`.
4. **Limpieza de stopwords situacionales**: `"de color"`, `"tipo"`, `"estilo"` reducen el peso semántico sin eliminarlos del todo.
5. **Detección de intención de precio**: patrones como `"menos de X€"`, `"por debajo de X"`, `"barato"`, `"premium"` se extraen como filtros, no como parte del embedding.

La query normalizada se usa tanto para BM25 (keyword matching) como para generar el vector semántico.

---

## 16. Arquitectura de Embeddings

### 16.1 Modelos recomendados

Para un catálogo en español con terminología de moda, los modelos candidatos son:

| Modelo | Dimensiones | Fortaleza | Consideración |
|---|---|---|---|
| `multilingual-e5-large` | 1024 | Alta calidad en español, buena comprensión semántica | Mayor coste de inferencia |
| `multilingual-e5-base` | 768 | Balance calidad/velocidad para producción | Opción pragmática por defecto |
| `bge-m3` | 1024 | Soporte BEIR multilingüe, denso + disperso | Más flexible para retrieval híbrido |

**Criterios de selección en orden de prioridad:**

1. Calidad semántica en español (no solo en inglés).
2. Robustez multilingüe para queries mixtas.
3. Comprensión de terminología de moda (estilo, ajuste, ocasión).
4. Rendimiento en retrieval real, no solo en benchmarks genéricos.

Modelos English-only están descartados como opción primaria. Pueden usarse en pipelines auxiliares (ej. reranking cross-encoder entrenado en inglés) siempre que la query se traduzca previamente.

### 16.2 Generación de embeddings

- **Indexación (offline/async)**: los embeddings de productos se generan en segundo plano via Symfony Messenger al recibir `ProductCreated` / `ProductUpdated`. La latencia de la llamada al API de embeddings (~100-500ms) no bloquea el request HTTP del administrador.
- **Búsqueda (online/sync)**: el embedding de la query del usuario se genera en el momento de la búsqueda. El tiempo de embedding de un texto corto con `text-embedding-3-small` es ~50-100ms; con modelos self-hosted puede ser < 20ms.
- **Caché de embeddings de query**: queries idénticas dentro de una ventana temporal (~5 minutos) pueden reutilizar el vector sin llamar al API. Aplicable cuando el volumen de búsquedas lo justifica.

---

## 17. Estrategia de Indexación en Qdrant

### 17.1 Colecciones

Para la fase actual (texto únicamente):

```
products_text
```

Colección única con un vector de texto por producto. La arquitectura permite añadir colecciones adicionales en el futuro (imágenes, audio, etc.) sin modificar la existente.

### 17.2 Payload de metadatos

Cada punto en Qdrant incluye un payload estructurado que permite filtrado sin lookups adicionales a la base de datos relacional:

```json
{
  "name":        "Sudadera básica negra",
  "brand":       "Nike",
  "category":    "APPAREL",
  "gender":      "unisex",
  "price":       4999,
  "currency":    "EUR",
  "colors":      ["negro", "antracita"],
  "sizes":       ["XS", "S", "M", "L", "XL"],
  "stock":       true,
  "style":       ["minimal", "streetwear"],
  "fit":         "oversized",
  "occasion":    ["casual", "urban"],
  "season":      ["otoño", "invierno"]
}
```

Los filtros de metadatos se aplican en Qdrant **antes** de la búsqueda vectorial, reduciendo el espacio de comparación. Esto mejora tanto la precisión como la latencia.

Casos de uso de filtrado estructurado:

- `stock: true` — nunca devolver productos sin stock.
- `category: "APPAREL"` — limitar la búsqueda a una categoría.
- `price: { lte: 6000 }` — precio máximo en céntimos.
- `gender: "men"` — filtrar por género.

### 17.3 Métrica de distancia

**Cosine similarity** es la métrica por defecto para embeddings de texto. Normaliza los vectores y mide el ángulo entre ellos, lo que hace que la magnitud del vector no afecte al score.

Dot product es una alternativa más rápida cuando los vectores están pre-normalizados (como en `text-embedding-3-small` con `normalize=true`), pero la diferencia de latencia no es significativa a escala de catálogo de moda (< 1M productos).

---

## 18. Flujo de Ejecución en Búsqueda

```
1.  Usuario envía query                  "sudadera minimalista negra"
2.  Normalización de query               sinónimos + vocabulario + typos
3.  Query understanding                  category=APPAREL, color=negro, fit=oversized?
4.  Generación de filtros estructurados  { category: APPAREL, color: negro, stock: true }
5.  Embedding de query normalizada       float[1024]
6.  BM25 retrieval                       top-N por coincidencia léxica
7.  Semantic retrieval en Qdrant         filtros → ANN search → top-K por similitud
8.  Fusión de scores                     0.6 * semantic + 0.4 * BM25
9.  Reranking                            cross-encoder sobre candidatos fusionados
10. Respuesta ordenada                   top-10 por relevancia final
```

Ejemplos de flujo completo:

| Query | Filtros extraídos | Resultado esperado |
|---|---|---|
| `"sudadera minimalista negra"` | `color=negro, style=minimal` | Sudaderas con aesthetic minimal en negro |
| `"camiseta oversize beige"` | `category=camiseta, color=beige, fit=oversized` | Camisetas con corte oversized en tonos beige/crema |
| `"pantalón recto elegante"` | `category=pantalón, fit=recto, occasion=formal` | Pantalones de corte recto para ocasiones formales |

---

## 19. Scoring Híbrido

El score final de cada candidato combina señales de búsqueda léxica y semántica con pesos configurables:

```
final_score = α * semantic_similarity + (1 - α) * BM25_score
```

Configuración por defecto:

```
final_score = 0.6 * semantic_similarity + 0.4 * BM25_score
```

Los pesos `α` no son constantes permanentes. Deben:

- Ser configurables sin redeploy (variable de entorno o base de datos de configuración).
- Medirse periódicamente contra métricas de relevancia (click-through rate, conversión).
- Ajustarse según el tipo de query: queries cortas ("nike") favorecen BM25; queries descriptivas largas favorecen semantic.

La fusión se implementa mediante **Reciprocal Rank Fusion (RRF)** cuando los dos sistemas devuelven listas de distinto tamaño o con scores en escalas distintas: es más robusto que mezclar scores directamente.

---

## 20. Etapa de Reranking

El retrieval inicial (BM25 + semantic) optimiza el **recall**: recupera candidatos relevantes aunque no perfectamente ordenados. El reranking optimiza la **precisión**: reordena esos candidatos para que los más relevantes para la intención concreta del usuario aparezcan primero.

### 20.1 Por qué es crítico en moda

El semantic drift (vectores plausibles pero incorrectos) es especialmente pronunciado en moda porque el vocabulario de estilo es subjetivo y polisémico. Un retrieval que devuelve `"pantalón recto negro formal"` ante la query `"pantalón recto negro casual"` no es un fallo obvio, pero sí relevante. El reranker, que procesa la query y el documento juntos, puede distinguir esta diferencia.

### 20.2 Rerankers candidatos

| Reranker | Tipo | Fortaleza |
|---|---|---|
| `bge-reranker-v2-m3` | Cross-encoder multilingüe | Buen rendimiento en español |
| Cohere Rerank | API externa | Sin infra propia; latencia de red |
| Custom cross-encoder | Entrenado en datos propios | Máxima precisión si hay datos de entrenamiento |

El reranker recibe los top-N candidatos de la etapa de fusión y devuelve los top-K reordenados. `N` típicamente es 50-100; `K` es el número de resultados finales (10-20). El reranker no realiza retrieval; solo reordena.

---

## 21. Escalabilidad y Optimización

### 21.1 Indexación vectorial

Qdrant usa **HNSW** (Hierarchical Navigable Small World) para approximate nearest neighbor search. Parámetros clave para producción:

- `m`: número de conexiones por nodo (trade-off memoria/calidad). Recomendado: 16-32 para catálogos de moda.
- `ef_construct`: precisión durante la construcción del índice. Mayor valor = mejor calidad, mayor tiempo de indexación.
- `ef`: precisión durante la búsqueda. Mayor valor = mejor recall, mayor latencia.

### 21.2 Quantización

Para catálogos grandes (> 500K productos) o restricciones de memoria, Qdrant soporta quantización de vectores:

- **Scalar quantization (INT8)**: reduce el tamaño del índice ~4x con degradación de calidad < 1% en la mayoría de casos.
- **Product quantization**: mayor compresión, mayor degradación. Solo recomendado si la memoria es el cuello de botella primario.

### 21.3 Generación de embeddings en segundo plano

La indexación no debe bloquear el flujo de creación/actualización de productos. La arquitectura actual usa Symfony Messenger para procesar `ProductCreated` / `ProductUpdated` de forma asíncrona. En producción:

- El worker de Messenger debe ejecutarse en procesos separados del servidor HTTP.
- La cola de indexación debe monitorizarse para detectar acumulación de mensajes (lag).
- En caso de fallo en la llamada al API de embeddings, el mensaje debe reintentarse con backoff exponencial.

### 21.4 Caché

- **Query embeddings**: vectores de queries frecuentes cacheados en Redis con TTL corto (5-10 minutos). Reducción de latencia significativa en horarios de alta demanda.
- **Resultados de búsqueda**: para queries exactamente idénticas, cachear el resultado completo durante < 2 minutos. No aplicable si los filtros de stock cambian frecuentemente.

### 21.5 Objetivos de latencia

| Etapa | Objetivo P95 |
|---|---|
| Normalización + query understanding | < 5ms |
| Embedding de query (API remota) | < 100ms |
| Embedding de query (self-hosted) | < 20ms |
| Retrieval en Qdrant (con filtros) | < 30ms |
| BM25 retrieval (Postgres full-text) | < 20ms |
| Fusión de scores | < 5ms |
| Reranking (API externa) | < 200ms |
| Reranking (self-hosted) | < 50ms |
| **Total búsqueda con reranking** | **< 350ms P95** |

---

## 22. Stack Recomendado para Búsqueda

| Componente | Tecnología | Justificación |
|---|---|---|
| **Vector DB** | Qdrant | Filtrado de payload nativo, HNSW, API HTTP simple |
| **Keyword search** | PostgreSQL full-text / tsvector | Ya disponible en el stack; sin dependencia adicional para BM25 básico |
| **Caché** | Redis | TTL por query, pipeline de cache warming |
| **Embedding workers** | Python (FastAPI) o PHP async | Workers separados del servidor HTTP; reconexión automática a Qdrant |
| **Reranker** | bge-reranker self-hosted (Ollama/vLLM) o Cohere API | Self-hosted reduce latencia y coste a escala |
| **API de búsqueda** | Symfony 7 (actual) | Suficiente para el volumen esperado; migrar a Go si la latencia se convierte en cuello de botella |

---

## 23. Mejoras Futuras

Las siguientes capacidades están intencionalmente fuera del alcance actual y se contemplan como roadmap:

- **Personalización por usuario**: reranking basado en historial de búsquedas y compras del usuario. Requiere modelo de usuario.
- **Ranking por comportamiento**: incorporar señales de click-through, tiempo en página y conversión como features del reranker.
- **Recomendaciones basadas en compra**: `"usuarios que compraron X también compraron Y"` via collaborative filtering.
- **Asistente conversacional de compra**: búsqueda multi-turno donde el contexto de la conversación refina los filtros de retrieval.
- **Session-aware reranking**: reordenar resultados considerando los productos ya vistos en la sesión actual.
- **Trending signals**: boosting temporal de productos con alta demanda reciente sin sacrificar relevancia semántica.

> **Fuera de scope permanente en esta fase**: imagen search, visual embeddings, recuperación multimodal. El sistema es estrictamente text-only.
