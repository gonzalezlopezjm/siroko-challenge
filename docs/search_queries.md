# Search Query Reference

Queries de referencia para verificar el buscador semántico + filtros híbridos.  
Ejecutar contra `GET /api/search?q=<query>` con los datos de `fixtures.json` indexados.

**Umbrales:** `score_threshold=0.70`, `limit=10`  
**Filtros siempre activos:** `in_stock: true`

---

## Índice de casuísticas

| # | Casuística | Queries |
|---|---|---|
| 1 | Tipo de producto directo | Q01–Q05 |
| 2 | Filtro por color | Q06–Q09 |
| 3 | Filtro por género | Q10–Q13 |
| 4 | Filtro por temporada / temperatura | Q14–Q17 |
| 5 | Filtro por ajuste y estilo | Q18–Q20 |
| 6 | Múltiples filtros combinados | Q21–Q25 |
| 7 | Semántica pura (sin keywords exactas) | Q26–Q30 |
| 8 | Por actividad deportiva | Q31–Q34 |
| 9 | Lifestyle y casual | Q35–Q37 |
| 10 | Sinónimos, typos y edge cases | Q38–Q40 |

---

## 1. Tipo de producto directo

Verifican que el buscador vectorial resuelve intención de producto clara.  
No se esperan filtros de payload, solo precisión semántica.

---

### Q01 — Maillot manga corta genérico

**Query:** `maillot de ciclismo manga corta`

**Filtros esperados:** ninguno

**Resultados esperados (top 5):**
- Maillot M2 Pinerolo
- Maillot SRX Pro HighTech
- Maillot M2 Yorkshire
- Maillot Mujer M2 Pinerolo
- Maillot Bio Nomad Cross Biodegradable

**No deben aparecer:** culotes, chaquetas, gafas

---

### Q02 — Culote de ciclismo

**Query:** `culote de bicicleta`

**Filtros esperados:** ninguno

**Resultados esperados (top 5):**
- Culotte BX Resistance
- Culote Corto Mortirolo
- Culote BX Squadra
- Culote BX Strada Mujer
- Culotte SRX Pro Elite

**No deben aparecer:** maillots, leggings de fitness, shorts de running

---

### Q03 — Gafas deportivas

**Query:** `gafas de ciclismo`

**Filtros esperados:** ninguno

**Resultados esperados:**
- Gafas K3 Tech MTB
- Gafas K3 The Cyclist
- Gafas K3 Criterium
- Gafas K3s Photochromic Grey

**No deben aparecer:** cascos, zapatillas

---

### Q04 — Zapatillas de ciclismo

**Query:** `zapatillas para ciclismo`

**Filtros esperados:** ninguno

**Resultados esperados:**
- Zapatillas Infinity Road
- Zapatillas SX200 MTB

**Verificar:** que no aparezcan zapatillas de running (no están en catálogo)

---

### Q05 — Calcetines de ciclismo

**Query:** `calcetines para bici`

**Filtros esperados:** ninguno

**Resultados esperados:**
- Calcetines S1 Burgundy
- Calcetines SRX Maloja Premium
- Calcetines S1 Ancares Azul

**No deben aparecer:** Pack Calcetines Running (son de running, no ciclismo — verificar si el LLM distingue)

---

## 2. Filtro por color

Verifican que el LLM extrae colores y Qdrant los aplica como filtro `color: {any: [...]}`.

---

### Q06 — Color explícito en ropa fitness

**Query:** `camiseta azul para correr`

**Filtros esperados:** `color: ["azul"]`

**Resultados esperados:**
- Camiseta Volt Sin Mangas Running (azul ✓)

**No deben aparecer:** camisetas negras ni grises (filtradas por color)

**Verificar:** que la respuesta no incluya la Camiseta Stoneway (azul marino pero APPAREL, no fitness)

---

### Q07 — Color en culote

**Query:** `culote negro de ciclismo`

**Filtros esperados:** `color: ["negro"]`

**Resultados esperados:** la mayoría de culotes son negros, deberían aparecer varios:
- Culotte SRX Pro Elite
- Culote Corto Mortirolo
- Culote BX Squadra
- Culote BX Envalira Largo

**Verificar:** no aparece Culotte Rugged Gravel (marrón/tierra, excluido por filtro)

---

### Q08 — Color en maillot mujer

**Query:** `maillot rosa mujer ciclismo`

**Filtros esperados:** `color: ["rosa"]`, `genero: ["mujer"]`

**Resultados esperados:**
- Maillot Mujer M2 Pinerolo (rosa ✓, mujer ✓)

**Verificar:** que no aparezcan maillots de hombre aunque sean rosa

---

### Q09 — Color poco común

**Query:** `ropa de ciclismo naranja`

**Filtros esperados:** `color: ["naranja"]`

**Resultados esperados:**
- Maillot M4 Oregon (naranja ✓)
- Maillot M8 VIBZ MTB Tarida (naranja ✓)
- Zapatillas SX200 MTB (naranja ✓)

**Verificar:** número reducido de resultados (color poco frecuente en catálogo)

---

## 3. Filtro por género

Verifican que el LLM extrae `genero: ["mujer"]` o `genero: ["hombre"]` y Qdrant lo filtra.

---

### Q10 — Ciclismo mujer genérico

**Query:** `ropa de ciclismo para mujer`

**Filtros esperados:** `genero: ["mujer"]`

**Resultados esperados (todos deben ser género mujer):**
- Maillot Mujer M2 Pinerolo
- Maillot M2 Aconcagua Mujer
- Culote Largo Mujer SRX Pro Aero Race
- Culote BX Strada Mujer
- Culote BX Trento Largo Mujer
- Culote SRX Pro Premier Largo Mujer

**Verificar:** que ningún resultado tenga `genero: ["hombre"]`

---

### Q11 — Fitness mujer

**Query:** `mallas de gym para mujer`

**Filtros esperados:** `genero: ["mujer"]`

**Resultados esperados:**
- Leggings Tenaz Workout (mujer ✓)

**No deben aparecer:** Leggings Deportivos Hombre

---

### Q12 — Hombre explícito

**Query:** `maillot de ciclismo para hombre verano`

**Filtros esperados:** `genero: ["hombre"]`, `temporada: ["verano"]`

**Resultados esperados:**
- Maillot M2 Pinerolo (hombre ✓, verano ✓)
- Maillot SRX Pro HighTech (hombre ✓, verano ✓)
- Maillot M2 Yorkshire (hombre ✓, verano ✓)

**No deben aparecer:** maillots de mujer

---

### Q13 — Sujetador deportivo

**Query:** `sujetador deportivo para running`

**Filtros esperados:** `genero: ["mujer"]`

**Resultados esperados:**
- Sujetador Deportivo High Support

**Verificar:** que el LLM infiere género mujer aunque no se mencione explícitamente

---

## 4. Filtro por temporada / temperatura

Verifican que el LLM mapea contexto térmico a `temporada: ["invierno"]` etc.

---

### Q14 — Invierno en ciclismo

**Query:** `ropa de ciclismo para el invierno`

**Filtros esperados:** `temporada: ["invierno"]`

**Resultados esperados:**
- Maillot M4 Oregon (invierno ✓)
- Culote BX Envalira Largo (invierno ✓)
- Culote BX Trento Largo Mujer (invierno ✓)
- Culote SRX Pro Premier Largo Mujer (invierno ✓)
- Guantes Core Hekla Invierno (invierno ✓)
- Guantes Vestkapp Invierno (invierno ✓)

**Verificar:** que no aparezcan culotes cortos de verano

---

### Q15 — Frío extremo (guantes)

**Query:** `guantes de ciclismo para frío extremo`

**Filtros esperados:** `temporada: ["invierno"]`

**Resultados esperados:**
- Guantes Core Hekla Invierno (0-8°C)
- Guantes Vestkapp Invierno (0-5°C)

**No deben aparecer:** Guantes Nuremberg Verano (15°C+, excluido por filtro de temporada)

---

### Q16 — Verano ligero

**Query:** `maillot ligero para el verano`

**Filtros esperados:** `temporada: ["verano"]`

**Resultados esperados:**
- Maillot M2 Pinerolo (verano ✓)
- Maillot Mujer M2 Pinerolo (verano ✓)
- Maillot M2 Yorkshire (verano ✓)
- Maillot Bio Nomad Cross Biodegradable (verano ✓)

**No deben aparecer:** Maillot M4 Oregon (invierno, excluido)

---

### Q17 — Contexto térmico en running

**Query:** `camiseta para correr cuando hace frío`

**Filtros esperados:** `temporada: ["invierno"]` o `["otoño"]`

**Resultados esperados:**
- Camiseta Running Manga Larga Hombre (otoño/invierno suave ✓)
- Chaqueta Alia Running (otoño ✓)

**Verificar:** que el LLM mapea "hace frío" a una temporada fría

---

## 5. Filtro por ajuste y estilo

---

### Q18 — Ajuste holgado

**Query:** `camiseta holgada para entrenar`

**Filtros esperados:** `ajuste: ["holgado"]`

**Resultados esperados:**
- Camiseta Volt Sin Mangas Running (holgado ✓)
- Camiseta Lifestyle Mujer (holgado/oversized ✓)

---

### Q19 — Estilo urbano

**Query:** `ropa urbana deportiva Siroko`

**Filtros esperados:** `estilo: ["urbano"]`, `brand: "Siroko"` *(o solo estilo)*

**Resultados esperados:**
- Gorra Mavericks 5 Paneles (urbano ✓, streetwear ✓)
- Sudadera Splash con Capucha (urbano ✓)
- Camiseta Lifestyle Manga Corta Hombre (casual ✓)

---

### Q20 — Alta compresión

**Query:** `culote de alta compresión para competición`

**Filtros esperados:** `ajuste: ["alta compresión"]`

**Resultados esperados:**
- Culotte SRX Pro Elite (alta compresión ✓)
- Culote SRX Pro Premier Largo Mujer (alta compresión ✓)
- Culote Largo Mujer SRX Pro Aero Race (aerodinámico/alta compresión ✓)

---

## 6. Múltiples filtros combinados

Verifican que el LLM extrae correctamente varios filtros simultáneos y Qdrant los aplica en `must`.

---

### Q21 — Color + género

**Query:** `culote negro para mujer`

**Filtros esperados:** `color: ["negro"]`, `genero: ["mujer"]`

**Resultados esperados (negros Y mujer):**
- Culote Largo Mujer SRX Pro Aero Race (negro ✓, mujer ✓)
- Culote BX Strada Mujer (negro ✓, mujer ✓)
- Culote BX Trento Largo Mujer (negro ✓, mujer ✓)
- Culote SRX Pro Premier Largo Mujer (negro ✓, mujer ✓)

**No deben aparecer:** culotes de hombre (filtrado por género) ni culotes no negros

---

### Q22 — Temporada + género + producto

**Query:** `maillot de mujer para primavera`

**Filtros esperados:** `genero: ["mujer"]`, `temporada: ["primavera"]`

**Resultados esperados:**
- Maillot Mujer M2 Pinerolo (mujer ✓, primavera ✓)
- Maillot M2 Aconcagua Mujer (mujer ✓, primavera ✓)

---

### Q23 — Talla + producto

**Query:** `culote de ciclismo talla XL`

**Filtros esperados:** `talla: ["XL"]`

**Resultados esperados:** todos los culotes que tengan XL en su array de tallas (la mayoría)

**Verificar:** que la talla se extrae correctamente y se aplica como filtro

---

### Q24 — Color + temporada + producto

**Query:** `guantes de ciclismo negros para invierno`

**Filtros esperados:** `color: ["negro"]`, `temporada: ["invierno"]`

**Resultados esperados:**
- Guantes Core Hekla Invierno (negro ✓, invierno ✓)
- Guantes Vestkapp Invierno (negro ✓, invierno ✓)

**No deben aparecer:** Guantes Nuremberg Verano (verano, excluido)

---

### Q25 — Ajuste + género + temporada

**Query:** `culote largo térmico de mujer para invierno`

**Filtros esperados:** `genero: ["mujer"]`, `temporada: ["invierno"]`

**Resultados esperados:**
- Culote BX Trento Largo Mujer (mujer ✓, invierno ✓)
- Culote SRX Pro Premier Largo Mujer (mujer ✓, invierno ✓)

**Verificar:** que no aparezcan culotes largos de hombre

---

## 7. Semántica pura (sin keywords exactas)

Verifican que el buscador vectorial resuelve intención aunque no se usen palabras del catálogo.  
**No se esperan filtros de payload** — toda la carga recae en el embedding.

---

### Q26 — Necesidad funcional de larga distancia

**Query:** `algo cómodo para pedalear muchas horas seguidas`

**Filtros esperados:** ninguno (o tal vez `temporada: ["verano"]` si el LLM infiere)

**Resultados esperados (los culotes con más horas de uso):**
- Culotte BX Resistance (hasta 10h)
- Culotte SRX Pro Elite (10-12h)
- Culote BX Squadra (8-10h)
- Culote BX Strada Mujer (8-10h)
- Culote SRX Pro Premier Largo Mujer (10-12h)

**Verificar:** que el texto enriquecido captura "horas de uso" y el embedding lo recupera

---

### Q27 — Protección ante lluvia en bici

**Query:** `me mojo cuando llueve en bici, qué me pongo`

**Filtros esperados:** ninguno

**Resultados esperados:**
- Chaqueta J3 Souplesse (DWR, repelente agua ✓)
- Chaleco SRX Pro Layer (resistente al agua ✓)

---

### Q28 — Rendimiento en competición ciclista

**Query:** `equipación para una gran fondo de ciclismo`

**Filtros esperados:** ninguno (o `temporada: ["primavera"]`)

**Resultados esperados:**
- Culotte SRX Pro Elite
- Maillot SRX Pro HighTech
- Culote SRX Pro Premier Largo Mujer
- Culote Largo Mujer SRX Pro Aero Race

**Verificar:** que los productos premium SRX aparezcan primero

---

### Q29 — Trail running sin mencionar marca ni producto exacto

**Query:** `necesito hidratarme en carrera de montaña`

**Filtros esperados:** ninguno

**Resultados esperados:**
- Chaleco Hidratación Rayne Trail (5L, reserva de agua ✓)

**Verificar:** precisión del resultado a pesar de la query indirecta

---

### Q30 — Sostenibilidad

**Query:** `busco ropa de ciclismo ecológica y sostenible`

**Filtros esperados:** ninguno

**Resultados esperados:**
- Maillot Bio Nomad Cross Biodegradable (biodegradable, reciclado ✓)

**Verificar:** que el texto enriquecido captura la dimensión de sostenibilidad

---

## 8. Por actividad deportiva

---

### Q31 — MTB / Mountain Bike

**Query:** `equipación para mountain bike`

**Filtros esperados:** ninguno (o `uso: ["outdoor"]`)

**Resultados esperados:**
- Maillot M8 VIBZ MTB Tarida
- Gafas K3 Tech MTB
- Casco HE Trail MTB MIPS
- Zapatillas SX200 MTB

**Verificar:** que el buscador agrupa bien la categoría MTB aunque no es un campo filtrable directo

---

### Q32 — Gravel / cicloaventura

**Query:** `ropa para hacer gravel`

**Filtros esperados:** ninguno

**Resultados esperados:**
- Culotte Rugged Gravel
- Maillot M2 Gravel Manga Larga
- Zapatillas SX200 MTB (compatible con SPD)

---

### Q33 — Trail running

**Query:** `ropa técnica para trail running`

**Filtros esperados:** ninguno (o `uso: ["outdoor"]`)

**Resultados esperados:**
- Chaleco Hidratación Rayne Trail
- Chaqueta Alia Running
- Gafas K3 Tech MTB (también para trail ✓)
- Casco HE Trail MTB MIPS

---

### Q34 — Gym y fitness

**Query:** `ropa para ir al gimnasio`

**Filtros esperados:** ninguno (o `uso: ["casual"]`)

**Resultados esperados:**
- Leggings Tenaz Workout
- Leggings Deportivos Hombre
- Camiseta Volt Sin Mangas Running
- Sujetador Deportivo High Support
- Top Running Sin Mangas Mujer

---

## 9. Lifestyle y casual

---

### Q35 — Post-entreno / casual

**Query:** `sudadera cómoda para después de entrenar`

**Filtros esperados:** `uso: ["post-entrenamiento"]` o `estilo: ["casual"]`

**Resultados esperados:**
- Sudadera Crew Neck Lifestyle (post-entrenamiento ✓)
- Sudadera Splash con Capucha (post-entrenamiento ✓)
- Sudadera Fleece Avoriaz Mujer (post-entrenamiento ✓)
- Camiseta Stoneway Manga Larga Lifestyle (post-entrenamiento ✓)

---

### Q36 — Streetwear / urbano

**Query:** `gorra streetwear`

**Filtros esperados:** `estilo: ["streetwear"]`

**Resultados esperados:**
- Gorra Mavericks 5 Paneles (streetwear ✓, urbano ✓)

---

### Q37 — Camiseta básica para el día a día mujer

**Query:** `camiseta básica mujer para el día a día`

**Filtros esperados:** `genero: ["mujer"]`, `uso: ["casual"]`

**Resultados esperados:**
- Camiseta Lifestyle Mujer (mujer ✓, casual ✓)

---

## 10. Sinónimos, typos y edge cases

---

### Q38 — Typo / sinónimo (mallot → maillot)

**Query:** `mallot de ciclismo`

**Filtros esperados:** ninguno

**Resultados esperados:** los mismos que Q01 (el expansor LLM corrige y amplía)
- Maillot M2 Pinerolo
- Maillot SRX Pro HighTech
- Maillot M2 Yorkshire

**Verificar:** que el LLM en la expansión incluye "maillot" y "jersey" como sinónimos

---

### Q39 — Inglés mezclado

**Query:** `bib shorts cycling`

**Filtros esperados:** ninguno

**Resultados esperados:** culotes (el LLM debe traducir/expandir "bib shorts" → "culote")
- Culotte BX Resistance
- Culotte SRX Pro Elite
- Culote BX Squadra

**Verificar:** que el expansor LLM traduce y expande al español

---

### Q40 — Fuera de catálogo (sin resultados esperados)

**Query:** `pantalón de fútbol`

**Filtros esperados:** ninguno

**Resultados esperados:** ninguno o resultados con score muy bajo (< 0.70)

**Verificar:** que el `score_threshold=0.70` evita falsos positivos; la respuesta debe devolver `results: []` o lista vacía

---

## Resumen de cobertura

| Casuística | Queries | Filtros cubiertos |
|---|---|---|
| Tipo producto directo | Q01–Q05 | vector puro |
| Color | Q06–Q09 | `color` |
| Género | Q10–Q13 | `genero` |
| Temporada | Q14–Q17 | `temporada` |
| Ajuste/estilo | Q18–Q20 | `ajuste`, `estilo` |
| Múltiples filtros | Q21–Q25 | `color+genero`, `genero+temporada`, `talla` |
| Semántica pura | Q26–Q30 | ninguno (embedding) |
| Actividad deportiva | Q31–Q34 | vector puro |
| Lifestyle | Q35–Q37 | `uso`, `estilo`, `genero` |
| Edge cases | Q38–Q40 | typos, inglés, sin resultados |

**Filtros NO cubiertos en esta versión** (pendientes de implementar si se necesitan):
- `precio` (rango: "menos de 50€", "económico")
- `marca` (solo hay Siroko en fixtures)
- `deporte` (campo en payload pero no filtrable actualmente)
