# API Use Cases — Catalog, Cart & Orders

Casos de uso de referencia para la suite de tests de aceptación e integración.  
Ejecutar contra `http://localhost:8080` con los fixtures de `docs/fixtures.json` cargados.

**Auth admin:** `Authorization: Bearer <ADMIN_API_KEY>`  
**Dinero:** siempre en céntimos de euro (`int`). 4995 = 49,95 €  
**IDs:** UUIDs v4 generados por la capa de aplicación

---

## Índice de casuísticas

| BC | # | Casuística | Casos |
|---|---|---|---|
| Catalog | 1 | Crear producto — happy path | CAT01–CAT03 |
| Catalog | 2 | Crear producto — validaciones de entrada | CAT04–CAT09 |
| Catalog | 3 | Crear producto — reglas de negocio | CAT10–CAT11 |
| Catalog | 4 | Actualizar producto (PATCH parcial) | CAT12–CAT17 |
| Catalog | 5 | Eliminar producto | CAT18–CAT19 |
| Catalog | 6 | Leer catálogo (GET) | CAT20–CAT26 |
| Catalog | 7 | Seguridad — endpoints de admin | CAT27–CAT29 |
| Cart | 8 | Crear y leer carrito | CART01–CART04 |
| Cart | 9 | Añadir ítems — happy path | CART05–CART08 |
| Cart | 10 | Añadir ítems — errores de dominio | CART09–CART13 |
| Cart | 11 | Actualizar cantidad | CART14–CART17 |
| Cart | 12 | Eliminar ítem y vaciar carrito | CART18–CART21 |
| Orders | 13 | Checkout — happy path | ORD01–ORD04 |
| Orders | 14 | Checkout — errores de validación | ORD05–ORD08 |
| Orders | 15 | Checkout — reglas de negocio | ORD09–ORD11 |
| Orders | 16 | Cancelar pedido | ORD12–ORD14 |
| Orders | 17 | Leer pedido | ORD15–ORD16 |
| Orders | 18 | Flujos completos end-to-end | E2E01–E2E04 |

---

## 1. Crear producto — happy path

`POST /api/products`  `Authorization: Bearer <ADMIN_API_KEY>`

---

### CAT01 — Producto mínimo válido

**Request:**
```json
{
  "name": "Maillot Test",
  "price": { "amount": 4995, "currency": "EUR" },
  "category": "CYCLING",
  "brand": "Siroko",
  "stock": 10
}
```

**Respuesta esperada:** `201 Created`
```json
{ "productId": "<uuid>" }
```

**Verificar:**
- El UUID devuelto es válido
- `GET /api/products/<uuid>` devuelve el producto con `description: ""` y `attributes: {}`
- `imageUrl` es `null`

---

### CAT02 — Producto completo con atributos y imagen

**Request:**
```json
{
  "name": "Culote Gravel Test",
  "description": "Culote para gravel con badana integrada.",
  "price": { "amount": 8995, "currency": "EUR" },
  "category": "CYCLING",
  "brand": "Siroko",
  "attributes": {
    "color": ["negro", "tierra"],
    "talla": ["S", "M", "L"],
    "genero": ["hombre"]
  },
  "stock": 25,
  "imageUrl": "https://example.com/culote.jpg"
}
```

**Respuesta esperada:** `201 Created`

**Verificar:**
- `GET` posterior devuelve `attributes` exactamente como se enviaron
- `imageUrl` se persiste correctamente

---

### CAT03 — Categorías válidas (todas las variantes del enum)

Repetir CAT01 con `category` en cada valor del enum:

| category | Esperado |
|---|---|
| `CYCLING` | 201 |
| `FITNESS` | 201 |
| `APPAREL` | 201 |
| `ACCESSORIES` | 201 |

**Verificar:** que el campo `category` del GET coincide con el valor enviado.

---

## 2. Crear producto — validaciones de entrada

---

### CAT04 — Campo requerido ausente (name)

**Request:** body sin `name`

**Respuesta esperada:** `422 Unprocessable Entity`
```json
{
  "error": { "code": "missing_field", "message": "Field \"name\" is required." }
}
```

**Repetir para:** `price`, `category`, `brand`, `stock` (uno a uno).

---

### CAT05 — Body vacío / JSON inválido

**Request:** body `""` o `"not json"`

**Respuesta esperada:** `400 Bad Request`
```json
{ "error": { "code": "invalid_request" } }
```

---

### CAT06 — Precio negativo o cero

**Request:** `"price": { "amount": 0, "currency": "EUR" }`

**Respuesta esperada:** `422 Unprocessable Entity`
```json
{ "error": { "code": "invalid_price" } }
```

**Repetir con** `amount: -1`.

---

### CAT07 — Objeto price malformado

**Request:** `"price": 4995` (sin objeto)

**Respuesta esperada:** `422`
```json
{ "error": { "code": "invalid_price" } }
```

**Repetir con** `"price": { "amount": 4995 }` (sin `currency`).

---

### CAT08 — Stock negativo

**Request:** `"stock": -1`

**Respuesta esperada:** `422`
```json
{ "error": { "code": "invalid_stock" } }
```

**Nota:** `stock: 0` es válido (producto agotado).

---

### CAT09 — Categoría inválida (valor fuera del enum)

**Request:** `"category": "RUNNING"`

**Respuesta esperada:** `422`
```json
{ "error": { "code": "invalid_enum_value" } }
```

---

## 3. Crear producto — reglas de negocio

---

### CAT10 — Nombre duplicado en la misma categoría

**Setup:** crear un producto con name="Maillot Único", category="CYCLING".

**Request:** intentar crear otro producto con el mismo name y category.

**Respuesta esperada:** `409 Conflict`
```json
{ "error": { "code": "duplicate_product_name" } }
```

---

### CAT11 — Mismo nombre en categoría distinta (permitido)

**Setup:** existe "Camiseta Basic" en category="FITNESS".

**Request:** crear "Camiseta Basic" en category="APPAREL".

**Respuesta esperada:** `201 Created`

**Verificar:** que ambos productos coexisten y tienen IDs distintos.

---

## 4. Actualizar producto (PATCH parcial)

`PATCH /api/products/{id}`  `Authorization: Bearer <ADMIN_API_KEY>`

Todos los campos son opcionales; solo los enviados se modifican.

---

### CAT12 — Actualizar un solo campo (precio)

**Request:** `{ "price": { "amount": 5995, "currency": "EUR" } }`

**Respuesta esperada:** `204 No Content`

**Verificar:** `GET` posterior muestra el nuevo precio; `name`, `stock`, `attributes` no han cambiado.

---

### CAT13 — Actualizar stock a cero (agotado)

**Request:** `{ "stock": 0 }`

**Respuesta esperada:** `204 No Content`

**Verificar:** el producto sigue existiendo con `stock: 0`.

---

### CAT14 — Limpiar imageUrl enviando null

**Setup:** producto con `imageUrl` establecida.

**Request:** `{ "imageUrl": null }`

**Respuesta esperada:** `204 No Content`

**Verificar:** `GET` posterior devuelve `"imageUrl": null`.

---

### CAT15 — PATCH sobre producto inexistente

**Request:** `PATCH /api/products/00000000-0000-0000-0000-000000000000`

**Respuesta esperada:** `404 Not Found`
```json
{ "error": { "code": "product_not_found" } }
```

---

### CAT16 — PATCH que genera nombre duplicado

**Setup:** existen "Maillot A" y "Maillot B" en la misma categoría.

**Request:** `PATCH "Maillot B"` con `{ "name": "Maillot A" }`

**Respuesta esperada:** `409 Conflict`
```json
{ "error": { "code": "duplicate_product_name" } }
```

---

### CAT17 — PATCH con precio inválido

**Request:** `{ "price": { "amount": -100, "currency": "EUR" } }`

**Respuesta esperada:** `422 Unprocessable Entity`

---

## 5. Eliminar producto

`DELETE /api/products/{id}`  `Authorization: Bearer <ADMIN_API_KEY>`

---

### CAT18 — Eliminar producto existente

**Respuesta esperada:** `204 No Content`

**Verificar:** `GET /api/products/{id}` devuelve `404` tras la eliminación.

---

### CAT19 — Eliminar producto inexistente

**Request:** `DELETE /api/products/00000000-0000-0000-0000-000000000000`

**Respuesta esperada:** `404 Not Found`
```json
{ "error": { "code": "product_not_found" } }
```

---

## 6. Leer catálogo (GET)

Endpoints públicos — sin autenticación.

---

### CAT20 — Listado por defecto

`GET /api/products`

**Respuesta esperada:** `200 OK`
```json
{
  "data": [...],
  "pagination": {
    "page": 1,
    "pageSize": 20,
    "total": 28,
    "totalPages": 2
  }
}
```

**Verificar:** estructura de cada ítem incluye `id`, `name`, `price.amount`, `price.currency`, `category`, `brand`, `attributes`, `stock`, `imageUrl`, `createdAt`, `updatedAt`.

---

### CAT21 — Paginación

`GET /api/products?page=2&pageSize=10`

**Verificar:**
- `pagination.page === 2`, `pagination.pageSize === 10`
- `data` contiene como máximo 10 ítems
- No se solapan ítems entre página 1 y página 2

---

### CAT22 — Filtro por categoría

`GET /api/products?category=CYCLING`

**Verificar:** todos los ítems de `data` tienen `category === "CYCLING"`.

---

### CAT23 — Filtro por brand

`GET /api/products?brand=Siroko`

**Verificar:** todos los ítems devueltos tienen `brand === "Siroko"`.

---

### CAT24 — Filtro combinado categoría + brand

`GET /api/products?category=FITNESS&brand=Siroko`

**Verificar:** solo devuelve productos que cumplen ambos filtros simultáneamente.

---

### CAT25 — Categoría inválida en filtro

`GET /api/products?category=RUNNING`

**Respuesta esperada:** `422 Unprocessable Entity`
```json
{ "error": { "code": "invalid_enum_value" } }
```

---

### CAT26 — Detalle de producto

`GET /api/products/{id}`

**Respuesta esperada:** `200 OK` con el objeto completo del producto.

**Repetir con ID inexistente** → `404 Not Found`.
**Repetir con UUID malformado** → `400 Bad Request`, `code: invalid_id`.

---

## 7. Seguridad — endpoints de admin

---

### CAT27 — POST sin token

`POST /api/products` sin header `Authorization`

**Respuesta esperada:** `401 Unauthorized`

---

### CAT28 — POST con token incorrecto

`Authorization: Bearer token-incorrecto`

**Respuesta esperada:** `401 Unauthorized`

---

### CAT29 — GET es público (sin token)

`GET /api/products` y `GET /api/products/{id}` sin header `Authorization`

**Respuesta esperada:** `200 OK`

**Verificar:** que los métodos de lectura no requieren auth.

---

## 8. Crear y leer carrito

`POST /api/carts`  `GET /api/carts/{cartId}`

---

### CART01 — Crear carrito anónimo

**Request:** `{}` o body vacío

**Respuesta esperada:** `201 Created`
```json
{ "cartId": "<uuid>" }
```

---

### CART02 — Crear carrito con customerId

**Request:** `{ "customerId": "user-123" }`

**Respuesta esperada:** `201 Created`

**Verificar:** `GET /api/carts/{cartId}` devuelve `customerId: "user-123"`.

---

### CART03 — Leer carrito vacío recién creado

`GET /api/carts/{cartId}`

**Respuesta esperada:** `200 OK`
```json
{
  "id": "<uuid>",
  "customerId": null,
  "items": [],
  "total": { "amount": 0, "currency": "EUR" },
  "createdAt": "...",
  "updatedAt": "..."
}
```

---

### CART04 — Leer carrito inexistente

`GET /api/carts/00000000-0000-0000-0000-000000000000`

**Respuesta esperada:** `404 Not Found`
```json
{ "error": { "code": "cart_not_found" } }
```

---

## 9. Añadir ítems al carrito — happy path

`POST /api/carts/{cartId}/items`

---

### CART05 — Añadir un ítem

**Request:** `{ "productId": "<uuid>", "quantity": 2 }`

**Respuesta esperada:** `204 No Content`

**Verificar via GET:** el ítem aparece con `quantity: 2`, `unitPrice.amount` coincide con el precio del producto, `subtotal.amount === unitPrice.amount * 2`, y `total.amount` del carrito refleja el subtotal.

---

### CART06 — Añadir el mismo producto dos veces (acumulación)

1. `POST items` con productId=A, quantity=3
2. `POST items` con productId=A, quantity=4

**Verificar:** `GET` muestra quantity=7 (no dos líneas separadas). El carrito tiene una sola línea para ese producto.

---

### CART07 — Añadir múltiples productos distintos

**Setup:** añadir productId=A (qty=1) y productId=B (qty=2).

**Verificar:** el carrito tiene 2 ítems, el `total.amount` es la suma de ambos subtotales.

---

### CART08 — Acumulación con tope de 99 unidades

1. Añadir productId=A con quantity=95
2. Añadir productId=A con quantity=10

**Verificar:** la cantidad queda en 99 (no 105). No se lanza error, el límite se aplica silenciosamente.

---

## 10. Añadir ítems — errores de dominio

---

### CART09 — Carrito inexistente

`POST /api/carts/00000000-0000-0000-0000-000000000000/items`

**Respuesta esperada:** `404`
```json
{ "error": { "code": "cart_not_found" } }
```

---

### CART10 — Producto inexistente

**Request:** `{ "productId": "00000000-0000-0000-0000-000000000000", "quantity": 1 }`

**Respuesta esperada:** `404`
```json
{ "error": { "code": "product_not_found" } }
```

---

### CART11 — Producto sin stock

**Setup:** crear producto con `stock: 0`.

**Request:** intentar añadirlo al carrito.

**Respuesta esperada:** `422`
```json
{
  "error": {
    "code": "insufficient_stock",
    "context": { "productId": "<uuid>", "available": 0, "requested": 1 }
  }
}
```

**Nota:** la comprobación en esta capa es binaria (`stock > 0`). Un producto con stock=1 se puede añadir aunque se pidan 5 unidades — la comprobación exacta de cantidad ocurre en checkout.

---

### CART12 — Campos requeridos ausentes

**Request sin productId:** `{ "quantity": 1 }`

**Respuesta esperada:** `422`
```json
{ "error": { "code": "missing_field" } }
```

**Repetir sin quantity.**

---

### CART13 — Límite de 50 líneas distintas

**Setup:** añadir 50 productos distintos a un carrito.

**Request:** intentar añadir un producto 51.

**Respuesta esperada:** `422`
```json
{ "error": { "code": "cart_lines_limit_exceeded" } }
```

**Nota:** añadir más unidades de un producto ya en el carrito no consume una nueva línea y no lanza este error.

---

## 11. Actualizar cantidad de un ítem

`PATCH /api/carts/{cartId}/items/{productId}`

---

### CART14 — Actualización correcta

**Request:** `{ "quantity": 5 }`

**Respuesta esperada:** `204 No Content`

**Verificar:** `GET` muestra la nueva cantidad y el subtotal recalculado.

---

### CART15 — Actualizar cantidad a 0 (vaciado de línea)

**Request:** `{ "quantity": 0 }`

**Respuesta esperada:** `204 No Content`

**Verificar:** la línea permanece en el carrito con quantity=0 (no se elimina automáticamente — es distinto de `DELETE item`).

---

### CART16 — Actualizar ítem que no está en el carrito

**Request:** PATCH con un productId que no existe en ese carrito.

**Respuesta esperada:** `404`
```json
{ "error": { "code": "item_not_found" } }
```

---

### CART17 — Tope de 99 en actualización

**Request:** `{ "quantity": 150 }`

**Respuesta esperada:** `204 No Content`

**Verificar:** `GET` muestra quantity=99 (capped silenciosamente).

---

## 12. Eliminar ítem y vaciar carrito

---

### CART18 — Eliminar un ítem

`DELETE /api/carts/{cartId}/items/{productId}`

**Respuesta esperada:** `204 No Content`

**Verificar:** el ítem ya no aparece en `GET`, el total se recalcula.

---

### CART19 — Eliminar ítem que no existe en el carrito

**Respuesta esperada:** `404`
```json
{ "error": { "code": "item_not_found" } }
```

---

### CART20 — Vaciar carrito (clear all)

`DELETE /api/carts/{cartId}/items`

**Respuesta esperada:** `204 No Content`

**Verificar:** `GET` devuelve `items: []` y `total.amount: 0`.

---

### CART21 — Vaciar carrito inexistente

`DELETE /api/carts/00000000-0000-0000-0000-000000000000/items`

**Respuesta esperada:** `404`
```json
{ "error": { "code": "cart_not_found" } }
```

---

## 13. Checkout — happy path

`POST /api/orders`

---

### ORD01 — Checkout mínimo (sin email, sin customerId)

**Setup:** carrito con un ítem.

**Request:**
```json
{
  "cartId": "<uuid>",
  "shippingAddress": {
    "street": "Calle Mayor 1",
    "city": "Madrid",
    "postalCode": "28001",
    "country": "ES"
  }
}
```

**Respuesta esperada:** `201 Created`
```json
{ "orderId": "<uuid>" }
```

**Verificar:**
- `GET /api/orders/{orderId}` devuelve `status: "PENDING"`
- El stock del producto se ha decrementado en la cantidad pedida
- El campo `customerId` es `null`

---

### ORD02 — Checkout con customerId y customerEmail

**Request:**
```json
{
  "cartId": "<uuid>",
  "customerId": "user-123",
  "customerEmail": "cliente@ejemplo.com",
  "shippingAddress": {
    "street": "Gran Vía 50",
    "city": "Bilbao",
    "postalCode": "48001",
    "country": "ES"
  }
}
```

**Respuesta esperada:** `201 Created`

**Verificar:**
- `GET /api/orders/{orderId}` devuelve `customerId: "user-123"`
- El worker envía un email a `cliente@ejemplo.com` visible en Mailpit (`http://localhost:8025`)

---

### ORD03 — Checkout con carrito de múltiples ítems

**Setup:** carrito con 3 productos distintos.

**Verificar:**
- El pedido tiene 3 líneas (`lines` con 3 elementos)
- `total.amount` es la suma de los subtotales de todas las líneas
- El stock de cada uno de los 3 productos ha bajado en la cantidad pedida

---

### ORD04 — El carrito persiste después del checkout

**Setup:** crear carrito, añadir ítem, hacer checkout.

**Verificar:** `GET /api/carts/{cartId}` sigue devolviendo `200 OK` con los ítems (el carrito no se elimina ni vacía automáticamente al hacer checkout).

---

## 14. Checkout — errores de validación

---

### ORD05 — cartId ausente

**Request sin cartId:** `{ "shippingAddress": { ... } }`

**Respuesta esperada:** `422`
```json
{ "error": { "code": "missing_field" } }
```

---

### ORD06 — Campo de shippingAddress ausente

**Request:** omitir `shippingAddress.city` (o cualquier otro campo requerido).

**Respuesta esperada:** `422`
```json
{ "error": { "code": "missing_field", "message": "Field \"shippingAddress.city\" is required." } }
```

**Repetir para:** `street`, `postalCode`, `country`.

---

### ORD07 — cartId con formato inválido (no UUID)

**Request:** `"cartId": "no-es-un-uuid"`

**Respuesta esperada:** `400 Bad Request`
```json
{ "error": { "code": "invalid_id" } }
```

---

### ORD08 — cartId no encontrado

**Request:** UUID válido pero inexistente.

**Respuesta esperada:** `404`
```json
{ "error": { "code": "cart_not_found" } }
```

---

## 15. Checkout — reglas de negocio

---

### ORD09 — Checkout de carrito vacío

**Setup:** crear carrito nuevo (sin ítems).

**Respuesta esperada:** `422`
```json
{ "error": { "code": "cart_is_empty" } }
```

---

### ORD10 — Stock insuficiente en checkout

**Setup:**
1. Crear producto con stock=2
2. Añadir ese producto al carrito con quantity=3
3. Hacer checkout

**Respuesta esperada:** `422`
```json
{
  "error": {
    "code": "insufficient_stock",
    "context": {
      "productId": "<uuid>",
      "available": 2,
      "requested": 3
    }
  }
}
```

**Verificar:** el stock del producto no ha cambiado (la transacción es atómica).

---

### ORD11 — Race condition de stock (checkout concurrente)

**Setup:** producto con stock=1.

1. Dos carritos distintos añaden ese producto (quantity=1 cada uno)
2. Los dos hacen checkout casi simultáneamente

**Verificar:** exactamente uno de los dos checkouts tiene éxito (`201`) y el otro devuelve `422 insufficient_stock`. El stock final del producto es 0.

---

## 16. Cancelar pedido

`POST /api/orders/{orderId}/cancel`

---

### ORD12 — Cancelar pedido en estado PENDING

**Respuesta esperada:** `204 No Content`

**Verificar:** `GET /api/orders/{orderId}` devuelve `status: "CANCELLED"`.

**Si el pedido tenía `customerEmail`:** el worker envía email de cancelación visible en Mailpit.

---

### ORD13 — Cancelar pedido ya cancelado

**Setup:** pedido en estado CANCELLED.

**Respuesta esperada:** `409 Conflict`
```json
{ "error": { "code": "order_already_cancelled" } }
```

---

### ORD14 — Cancelar pedido inexistente

**Request:** `POST /api/orders/00000000-0000-0000-0000-000000000000/cancel`

**Respuesta esperada:** `404`
```json
{ "error": { "code": "order_not_found" } }
```

---

## 17. Leer pedido

`GET /api/orders/{orderId}`

---

### ORD15 — Detalle de pedido completo

**Verificar estructura de la respuesta:**
```json
{
  "id": "<uuid>",
  "customerId": "user-123",
  "lines": [
    {
      "id": "<uuid>",
      "productId": "<uuid>",
      "productName": "Maillot M2 Pinerolo",
      "unitPrice": { "amount": 4995, "currency": "EUR" },
      "quantity": 2,
      "subtotal": { "amount": 9990, "currency": "EUR" }
    }
  ],
  "total": { "amount": 9990, "currency": "EUR" },
  "status": "PENDING",
  "shippingAddress": {
    "street": "Calle Mayor 1",
    "city": "Madrid",
    "postalCode": "28001",
    "country": "ES"
  },
  "createdAt": "2026-05-30T..."
}
```

---

### ORD16 — Pedido inexistente

`GET /api/orders/00000000-0000-0000-0000-000000000000`

**Respuesta esperada:** `404`
```json
{ "error": { "code": "order_not_found" } }
```

---

## 18. Flujos completos end-to-end

Estos flujos cruzan todos los BCs en un único escenario continuo.

---

### E2E01 — Flujo de compra completo con email

1. `POST /api/products` → crear producto A (stock=5) → `productId`
2. `POST /api/carts` → `cartId`
3. `POST /api/carts/{cartId}/items` → añadir producto A, qty=2
4. `GET /api/carts/{cartId}` → verificar total = precio × 2
5. `POST /api/orders` con `customerEmail` → `orderId`
6. `GET /api/orders/{orderId}` → `status: PENDING`, `lines[0].quantity: 2`
7. `GET /api/products/{productId}` → `stock: 3` (5 - 2)
8. Mailpit → existe email de confirmación para ese `customerEmail`

---

### E2E02 — Flujo de compra y cancelación con emails

1. Crear carrito, añadir ítem, checkout con `customerEmail`
2. Verificar email de confirmación en Mailpit
3. `POST /api/orders/{orderId}/cancel`
4. `GET /api/orders/{orderId}` → `status: CANCELLED`
5. Verificar email de cancelación en Mailpit (2 emails en total)
6. Intentar cancelar de nuevo → `409 order_already_cancelled`

---

### E2E03 — Modificar carrito antes del checkout

1. Crear carrito, añadir producto A (qty=3) y producto B (qty=1)
2. `PATCH` producto A → qty=1
3. `DELETE` producto B
4. `GET carrito` → solo producto A con qty=1
5. Hacer checkout → verificar pedido con 1 línea, total = precio A × 1

---

### E2E04 — Actualizar catálogo refleja cambio en nuevos carritos

1. Crear producto con precio 4995
2. Crear carrito, añadir ese producto (se guarda el precio en el carrito al momento del add)
3. `PATCH /api/products/{id}` → cambiar precio a 7995
4. `GET /api/carts/{cartId}` → el ítem del carrito mantiene el precio original (4995 capturado en el add)
5. Nuevo carrito con el mismo producto → captura el nuevo precio (7995)

---

## Resumen de cobertura

| Área | Casos | Errores HTTP cubiertos |
|---|---|---|
| Catalog — escritura | CAT01–CAT19 | 201, 204, 400, 401, 404, 409, 422 |
| Catalog — lectura | CAT20–CAT29 | 200, 400, 401, 404, 422 |
| Cart — gestión | CART01–CART21 | 200, 201, 204, 400, 404, 422 |
| Orders — checkout | ORD01–ORD11 | 201, 400, 404, 422 |
| Orders — ciclo de vida | ORD12–ORD16 | 204, 404, 409 |
| End-to-end | E2E01–E2E04 | flujos cruzados |

**Reglas de dominio cubiertas:**
- `stock` se decrementa atómicamente en checkout, no en add-to-cart
- La comprobación de stock en add-to-cart es binaria (> 0), no por cantidad
- El carrito persiste tras el checkout
- La cantidad en carrito se acumula (mismo producto) con tope silencioso de 99
- Nombre de producto es único por categoría, no globalmente
- `customerEmail` es opcional; si presente, el worker envía email de confirmación y cancelación
- Cancelar un pedido ya cancelado es un error de negocio (409, no 422)
- El total del pedido se calcula en el momento del checkout con el precio vigente del producto
