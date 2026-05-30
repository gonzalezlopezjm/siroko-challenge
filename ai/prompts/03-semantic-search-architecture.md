# Prompt 03 — Arquitectura del motor de búsqueda semántica

**Fecha:** 2026-05-28
**Herramienta:** Claude Code (claude-sonnet-4-6)
**Contexto:** Diseño del Search BC. Necesitaba decidir cómo integrar Qdrant, OpenAI Embeddings y el fallback a LLM.

---

## Prompt enviado

```
Estoy diseñando el bounded context de búsqueda semántica. El flujo que tengo en mente:
1. Usuario envía una query en lenguaje natural: "camiseta roja para el frío"
2. Se genera un embedding de la query con OpenAI text-embedding-3-small
3. Se busca por similitud coseno en Qdrant
4. Si no hay resultados, ¿qué hacemos?

Para el fallback cuando Qdrant no devuelve resultados, se me ocurren dos enfoques:
a) Enviar al LLM (GPT-4o-mini) la query + la lista completa de productos del catálogo
   y pedirle que rankee cuáles son más relevantes.
b) Usar el LLM solo para extraer filtros estructurados de la query
   (category, brand, colors, keywords) y buscar con SQL usando esos filtros.

¿Cuál recomiendas? ¿Hay alguna otra alternativa?

Además, ¿dónde colocarías la lógica de fallback? ¿En el adaptador de Qdrant,
en el handler de Application, o en un Domain Service?
```

---

## Respuesta de Claude Code

Claude recomendó el enfoque (a): enviar el catálogo completo al LLM para que rankee. Argumentó que era más preciso semánticamente y que GPT-4o-mini tiene contexto suficiente para listas medianas de productos. Para la ubicación del fallback, propuso implementarlo dentro del adaptador de Qdrant: "si Qdrant devuelve array vacío, el adaptador llama al LLM internamente y devuelve sus resultados".

---

## Decisión tomada (criterio propio)

Rechacé el enfoque (a): con 10.000 productos, enviar el catálogo entero al LLM es O(n) en tokens y completamente inviable en coste y latencia. Con 28 productos en fixtures ya serían ~5.000 tokens por búsqueda. Ver [DECISIONS.md #007](../DECISIONS.md#007).

Rechacé también colocar el fallback en el adaptador de Qdrant: violaría SRP y haría el comportamiento imposible de testear unitariamente. Ver [DECISIONS.md #003](../DECISIONS.md#003).

Implementé el enfoque (b) con la lógica de fallback en `SearchProductsHandler` (Application layer), coordinando dos puertos distintos: `SemanticSearchPort` y `QueryExpanderPort`.
