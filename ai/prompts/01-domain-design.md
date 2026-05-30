# Prompt 01 — Diseño inicial del dominio

**Fecha:** 2026-05-28
**Herramienta:** Claude Code (claude-sonnet-4-6)
**Contexto:** Arranque del proyecto. Necesitaba definir los bounded contexts y el modelado de dominio antes de tocar código.

---

## Prompt enviado

```
Voy a implementar el Siroko Senior Challenge. He elegido implementar ambas opciones (A y B)
porque comparten el mismo bounded context de producto.

Los requisitos son:
- Opción A: gestión de productos (CRUD), carrito de compra, checkout generando una orden persistente.
- Opción B: motor de búsqueda semántica con embeddings (OpenAI) y persistencia en vector DB (Qdrant).

Restricciones técnicas:
- PHP 8.3 + Symfony 7
- Arquitectura Hexagonal + DDD + CQRS
- El dominio debe estar completamente desacoplado de Symfony y Doctrine
- Testing exhaustivo

Necesito que me ayudes a diseñar el modelo de dominio antes de implementar nada:
1. ¿Qué bounded contexts identificas?
2. ¿Cuáles son los aggregate roots de cada uno?
3. ¿Qué value objects necesito?
4. ¿Qué invariantes de dominio son críticas?
5. ¿Qué eventos de dominio tendría sentido publicar?

Sé específico con los tipos de datos. Por ejemplo, para precios, ¿usarías float, string decimal, o int?
```

---

## Respuesta relevante de Claude Code (extracto)

Claude propuso cuatro bounded contexts: Catalog, Cart, Order y Search. Para precios sugirió usar `float` con dos decimales de precisión, argumentando que era el tipo más natural para representar moneda en PHP.

También propuso que los IDs de dominio se generaran dentro del constructor de la entidad usando `Ramsey\Uuid\Uuid::uuid4()`, para que el agregado fuera autocontenido.

Para `CartItem`, sugirió guardar solo el `ProductId` y resolver el producto en runtime al necesitar el precio.

---

## Decisión tomada (criterio propio)

Rechacé el `float` para dinero → `int` en céntimos (ver [DECISIONS.md #001](../DECISIONS.md#001)).
Rechacé la generación de IDs en el dominio → IDs inyectados desde Application layer (ver [DECISIONS.md #004](../DECISIONS.md#004)).
Rechacé la referencia viva al Product en CartItem → snapshot de precio inmutable (ver [DECISIONS.md #005](../DECISIONS.md#005)).

El resto del modelado (bounded contexts, aggregates, eventos) fue coherente con el enfoque DDD y se adoptó con ajustes menores.
