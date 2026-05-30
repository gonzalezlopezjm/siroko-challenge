# Prompt 04 — Depuración: buscador devolvía 0 resultados

**Fecha:** 2026-05-30
**Herramienta:** Claude Code (claude-sonnet-4-6)
**Contexto:** El buscador estaba implementado y los tests pasaban con mocks, pero en el entorno real con Qdrant + OpenAI devolvía 0 resultados para todas las queries.

---

## Prompt enviado

```
El buscador semántico está implementado pero devuelve 0 resultados para todas las queries
en el entorno real. Los tests de aceptación pasan porque usan InMemorySemanticSearchAdapter.

He revisado:
- Los productos sí están indexados en Qdrant (he confirmado con la API REST de Qdrant que
  hay 28 puntos en la colección siroko_products)
- El embedding de la query se genera correctamente (he loggeado el vector, tiene 1536 dimensiones)
- La búsqueda en Qdrant devuelve array vacío

He ejecutado el fallback SQL también y devuelve resultados correctos.

¿Qué puede estar fallando? El score_threshold está en 0.70. ¿Debería bajarlo?
```

---

## Respuesta de Claude Code

Claude sugirió bajar el `SEARCH_SIMILARITY_THRESHOLD` de 0.70 a 0.60 como solución rápida, argumentando que era "un valor más razonable para búsquedas en lenguaje natural". También sugirió cambiar el modelo de embeddings a `text-embedding-ada-002` porque "tiene mejor rendimiento en español".

---

## Decisión tomada (criterio propio)

Rechacé ambas sugerencias. Bajar el umbral es un workaround que aumenta falsos positivos sin resolver el problema real. Cambiar de modelo de embeddings requeriría re-indexar todo y no estaba justificado sin diagnóstico.

Hice el diagnóstico real: comparé manualmente el texto enriquecido de un producto indexado con la query expandida y vi que el expansor de query generaba texto genérico ("una prenda de ciclismo para...") mientras los productos estaban indexados con descripciones técnicas de catálogo. La similitud coseno entre estilos de texto tan diferentes era ~0.63, por debajo del umbral.

La solución inmediata fue mejorar los prompts del expansor y del enrichment para que ambos generaran texto con el mismo vocabulario técnico de catálogo. Ver [DECISIONS.md #012](../DECISIONS.md#012) y [#013](../DECISIONS.md#013).

**Iteración posterior:** Tras las mejoras de #012–#015, evaluamos si el `QueryExpanderPort` seguía aportando valor suficiente para justificar su coste: una llamada LLM síncrona de ~200–400ms en cada búsqueda, y el riesgo de filtros mal inferidos que eliminaban todos los resultados (problema estructural del enfoque, no solo de los prompts). La conclusión fue eliminar el expansor completamente y embebear directamente la query del usuario. Ver [DECISIONS.md #020](../DECISIONS.md#020).
