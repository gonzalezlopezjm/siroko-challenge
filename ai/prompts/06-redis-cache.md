# Prompt 06 — Caché Redis para optimización de rendimiento

**Fecha:** 2026-05-30
**Herramienta:** Claude Code (claude-sonnet-4-6)
**Contexto:** El stack ya pasaba los targets de P95 del k6 sin caché (18 ms GET products, 12 ms GET product/{id}), pero los endpoints de lectura hacen una o dos queries SQL en cada request y el endpoint de búsqueda llama a OpenAI + Qdrant en cada consulta. Queremos añadir Redis como capa de caché para absorber ráfagas y reducir carga en base de datos y APIs externas.

---

## Prompt enviado

```
Vale, agregamos redis como capa de cache para tratar de optimizar performance?
```

---

## Respuesta de Claude Code

Antes de proponer nada, Claude exploró la estructura actual: Query Handlers existentes, Command Handlers, DTOs (para verificar serializabilidad), `cache.yaml`, `docker-compose.yml` y el Dockerfile. Hizo una pregunta de clarificación sobre la estrategia de invalidación del caché de búsqueda (TTL corto vs. invalidación activa desde `IndexProductHandler`) antes de escribir código.

Una vez confirmada la estrategia (TTL 2 min para búsqueda, sin invalidación activa), propuso e implementó:

1. **`docker/php/Dockerfile`** — `pecl install redis` con deps de compilación efímeras (`apk add --virtual .build-deps autoconf build-base` + `apk del .build-deps` en la misma capa).
2. **`docker-compose.yml`** — Servicio `redis:7-alpine` con healthcheck (`redis-cli ping`), volumen persistente y `depends_on` desde `php`.
3. **`.env`** — `REDIS_URL=redis://redis:6379`.
4. **`config/packages/cache.yaml`** — Pool `cache.products` (`RedisTagAwareAdapter`, TTL 600s) + `cache.search` (`RedisAdapter`, TTL 120s); `when@test` usa `array` adapter para ambos.
5. **`GetProductHandler`** — `cache->get('product.{id}', ...)` con tags `[product, product.{id}]`. Excepción dentro del callback no cachea el 404.
6. **`ListProductsHandler`** — Clave compuesta por filtros/página con tags `[product, products.list]`, TTL 300s.
7. **`CreateProductHandler`** — `invalidateTags(['products.list'])` tras `save()`.
8. **`UpdateProductHandler`** — `invalidateTags(['product.{id}', 'products.list'])` tras `save()`.
9. **`DeleteProductHandler`** — `invalidateTags(['product.{id}', 'products.list'])` tras `delete()`.
10. **`SearchProductsHandler`** — `cache->get('search.{md5}', ...)` con TTL 120s; el evento `SearchPerformed` se despacha dentro del callback (solo en cache miss).

La inyección se hace con `#[Autowire(service: 'cache.products')]` y `#[Autowire(service: 'cache.search')]`, evitando configuración explícita en `services.yaml`.

---

## Decisión tomada (criterio propio)

La propuesta se adoptó sin cambios significativos. Dos observaciones propias:

**Sobre la ubicación del caché:** La alternativa —un decorador del bus de queries que intercepte antes del handler— sería más limpia en arquitectura (separa la preocupación). Sin embargo, añade una clase extra, configuración de decoración en `services.yaml` y ninguna ventaja práctica dado que estos handlers no tienen lógica de negocio que proteger. Inyectar el pool directamente en el handler es el cambio mínimo correcto.

**Sobre `SearchPerformed` en cache miss:** Claude tomó la decisión correcta al poner el dispatch dentro del callback (solo en miss). En cache hit no se despacha el evento, lo que significa que el dashboard de Grafana subregistra las búsquedas cacheadas. Es un tradeoff aceptable: los hits son indistinguibles de un resultado real para el usuario, y el evento es para métricas de negocio, no de rendimiento.

Ver [DECISIONS.md #021](../DECISIONS.md#021).
