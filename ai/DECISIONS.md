# DECISIONS.md — Decisiones frente a la IA

> Registro de momentos donde la IA propuso algo y se decidió lo contrario
> basándose en criterio senior. Formato: decisión + propuesta de IA + razonamiento.

---

## #001 — Money como enteros, no float

**Fecha:** 2026-05-28
**Contexto:** Modelado del Value Object `Price` / `Money`.

**Propuesta de la IA:** Usar `float` para representar el precio (e.g., `19.99`).

**Decisión:** Usar `int` en céntimos (e.g., `1999`) con un `Currency` enum separado.

**Razonamiento:** Los floats introducen errores de representación en IEEE 754 (`0.1 + 0.2 !== 0.3`). En contexto financiero esto es inaceptable. La representación decimal es responsabilidad de la capa de presentación (DTO), no del dominio. Esta es una regla estándar en cualquier sistema de pagos serio.

---

## #002 — Doctrine mappings en XML, no annotations en la entidad

**Fecha:** 2026-05-28
**Contexto:** Persistencia de la entidad `Product` con Doctrine ORM.

**Propuesta de la IA:** Añadir `#[ORM\Entity]`, `#[ORM\Column]` directamente a la clase `Product` en el dominio.

**Decisión:** Mappings en ficheros XML en `Infrastructure/Persistence/Mapping/`, entidad de dominio sin ninguna referencia a Doctrine.

**Razonamiento:** Las annotations de Doctrine en la entidad acoplan el dominio a la infraestructura de persistencia, violando el principio de Arquitectura Hexagonal. Si mañana cambia el ORM, el dominio no debería verse afectado. El XML mapping es más verboso pero mantiene el dominio puro.

---

## #003 — Fallback LLM en Application layer, no en Infrastructure

**Fecha:** 2026-05-28
**Contexto:** Lógica de fallback en la búsqueda semántica (VectorDB → LLM).

**Propuesta de la IA:** Implementar el fallback dentro del adaptador de Qdrant (si falla, llama al LLM desde el mismo adaptador).

**Decisión:** La lógica de fallback reside en el `SearchProductsHandler` (Application layer), coordinando dos puertos distintos: `SemanticSearchPort` y `LanguageModelPort`.

**Razonamiento:** El adaptador de Qdrant tiene una única responsabilidad: hablar con Qdrant. Meter lógica de orquestación de fallback dentro de la infraestructura viola SRP y hace el comportamiento imposible de testear unitariamente. En Application layer, el handler puede ser testeado mockeando ambos puertos independientemente.

---

## #004 — IDs generados en Application layer, no en Domain

**Fecha:** 2026-05-28
**Contexto:** Generación de UUIDs para nuevas entidades.

**Propuesta de la IA:** Generar el UUID dentro del constructor de la entidad de dominio usando `Ramsey\Uuid\Uuid::uuid4()`.

**Decisión:** El Handler (Application layer) genera el UUID antes de construir la entidad y lo pasa como parámetro al constructor.

**Razonamiento:** Si la entidad genera su propio ID, el dominio depende de una librería externa (Ramsey UUID), que es infraestructura. Además, dificulta los tests (no se puede predecir el ID generado). Con el ID inyectado desde fuera, los tests son deterministas y el dominio permanece puro.

---

## #005 — ProductSnapshot en CartItem, no referencia viva al Product

**Fecha:** 2026-05-28
**Contexto:** Modelado de `CartItem` dentro del `Cart` aggregate.

**Propuesta de la IA:** Guardar solo el `ProductId` en `CartItem` y resolver el producto en runtime cuando se necesite.

**Decisión:** Guardar un `ProductSnapshot` (nombre + precio) en el momento de añadir el ítem al carrito.

**Razonamiento:** Si el precio del producto cambia después de que el usuario lo añadió al carrito, el carrito debe reflejar el precio en el momento de añadir (comportamiento esperado en e-commerce). Resolver el producto en runtime crearía inconsistencias temporales y acopla el Cart BC al Catalog BC en el modelo de dominio.

---

## #006 — Verificación de stock en checkout, no solo al añadir al carrito

**Fecha:** 2026-05-28
**Contexto:** Flujo de creación de `Order` en el `CheckoutHandler`.

**Propuesta de la IA:** Validar stock únicamente al añadir el ítem al carrito (en `AddItemToCartHandler`). Asumir que si el carrito fue creado con éxito, el stock es suficiente.

**Decisión:** Verificar el stock **también** en el momento exacto de crear la orden, dentro de la misma transacción que descuenta el stock. Si cualquier línea no tiene stock suficiente, la operación falla completamente y la orden no se crea.

**Razonamiento:** Entre el momento de añadir al carrito y el checkout pueden pasar minutos u horas. Otro usuario puede haber comprado el último stock. El carrito es estado transitorio sin garantías. La única verificación que importa para la consistencia del negocio es la del momento de la compra. Sin esto tendríamos órdenes sobre-vendidas.

---

## #007 — LLM extrae filtros estructurados, no rankea productos

**Fecha:** 2026-05-28
**Contexto:** Diseño del fallback en `SearchProductsHandler` cuando Qdrant no devuelve resultados.

**Propuesta de la IA:** Enviar al LLM la query + la lista de productos del catálogo y pedirle que rankee cuáles son más relevantes.

**Decisión:** El LLM recibe **solo la query en lenguaje natural** y devuelve `SearchFilters` estructurado (`{category, brand, colors[], keywords[]}`). La búsqueda real la ejecuta el Catalog BC con un query SQL usando esos filtros.

**Razonamiento:** Enviar el catálogo al LLM tiene coste O(n) en tokens — con 10.000 productos es inviable. El LLM es excelente en NLP/extracción de entidades pero innecesario para la búsqueda en sí. Separar "entender la intención" (LLM) de "buscar" (SQL) es más robusto, más barato, más testeable y más rápido. El resultado del LLM es un VO (`SearchFilters`) puro, sin efectos laterales.

---

## #008 — Endpoints de escritura de producto requieren auth; el resto no

**Fecha:** 2026-05-28
**Contexto:** Diseño de seguridad de la API.

**Propuesta de la IA:** Autenticar toda la API con JWT (usuarios en base de datos, login endpoint, refresh tokens).

**Decisión:** API Key estática (`Authorization: Bearer <ADMIN_API_KEY>`) solo para `POST/PATCH/DELETE /api/products`. El resto de la API es pública. Sin usuarios en base de datos, sin JWT.

**Razonamiento:** El scope de la prueba es el diseño de dominio y arquitectura, no una solución de IAM completa. JWT + users añadiría ~5 tablas, ~8 endpoints y ~20h de trabajo sin aportar valor al criterio de evaluación. La API Key es suficiente para demostrar que se entiende el principio de autorización. Se documenta explícitamente como simplificación deliberada.

---

## #009 — Indexación automática vía eventos; sin endpoint /index manual

**Fecha:** 2026-05-28
**Contexto:** Estrategia de indexación de productos en Qdrant.

**Propuesta de la IA:** Exponer un endpoint `POST /api/products/{id}/index` para que el cliente decida cuándo indexar.

**Decisión:** La indexación se dispara automáticamente al procesar los eventos `ProductCreated` y `ProductUpdated` mediante Symfony Messenger. No existe endpoint de indexación manual.

**Razonamiento:** Un endpoint de indexación manual crea inconsistencias (producto existe en catálogo pero no en índice hasta que alguien llame al endpoint). La indexación es un efecto secundario de negocio de crear/actualizar un producto, no una operación independiente. Con eventos, el sistema es eventual-consistent de forma automática y el cliente HTTP no asume ninguna responsabilidad de sincronización.

---

## #010 — Degradación silenciosa sin token LLM, sin error

**Fecha:** 2026-05-28
**Contexto:** Comportamiento del sistema cuando `LANGUAGE_MODEL_API_KEY` no está configurada.

**Propuesta de la IA:** Lanzar una excepción o devolver un error 503 si el LLM está configurado como fallback pero no hay credenciales disponibles.

**Decisión:** Si la API key no está configurada, se registra un `NullLanguageModelAdapter` en el contenedor. El `SearchProductsHandler` detecta la ausencia del adaptador real y omite el fallback silenciosamente. La respuesta HTTP es siempre 200 con `meta.fallback_available: false`.

**Razonamiento:** El LLM es un **enhancement opcional**, no un componente crítico. El sistema debe funcionar correctamente (con búsqueda vectorial) sin él. Errores 503 por ausencia de una feature opcional son una mala experiencia de usuario y dificultan el despliegue incremental. El patrón Null Object permite desactivar el componente vía configuración sin cambiar código.

---

## #011 — `docs/openapi.yaml` como contrato de API de referencia

**Fecha:** 2026-05-28
**Contexto:** Implementación de los controllers HTTP del Catalog BC.

**Decisión:** `docs/openapi.yaml` es la fuente de verdad para los contratos HTTP. Cualquier discrepancia entre la implementación y el spec debe resolverse adaptando el código al spec, no al revés.

**Implicaciones concretas:**
- Requests de creación/actualización de producto usan `price: { amount, currency }` (objeto anidado), no campos planos `priceAmount`/`priceCurrency`.
- La respuesta de `GET /api/products` usa la clave `data` (no `items`) para el array de productos.
- Los campos `required` en requests siguen exactamente el spec (ej. `price` requerido, no sus subcampos por separado).

**Razonamiento:** Un spec OpenAPI es un contrato externo que puede ser consumido por clientes, generadores de código y herramientas de testing. Si el código se desvía del spec, se rompe ese contrato. La fuente de verdad debe ser el spec, y el código debe adaptarse a él.

---

## #012 — Expansión de query como ficha técnica de catálogo, no como explicación genérica

**Fecha:** 2026-05-30
**Contexto:** El buscador devolvía 0 resultados para todas las queries. La causa raíz: el expansor de query generaba texto genérico ("una prenda para ciclistas que...") mientras que los productos estaban indexados con descripciones técnicas de catálogo. La similitud coseno entre ambos era ~0.63, por debajo del umbral 0.70.

**Propuesta inicial:** Bajar el `score_threshold` de 0.70 a 0.60 para capturar más resultados.

**Decisión:** Mantener el umbral en 0.70 y mejorar los prompts para que tanto la expansión de query como el enrichment de productos generen textos con vocabulario técnico de catálogo específico del dominio (materiales, tecnologías, sinónimos de prenda). El objetivo es que ambos vectores estén en el mismo espacio semántico.

**Razonamiento:** Bajar el umbral es un workaround que aumenta falsos positivos. El problema real es un desajuste de estilo entre la query expandida y los documentos indexados. Al hacer que la expansión genere texto "como una descripción de producto" (con vocabulario como Breathlock+, Strato+, bib shorts/culote, Elastic Interface) en lugar de texto explicativo genérico, la similitud coseno sube a 0.73-0.83 para las queries esperadas.

---

## #013 — Filtros del LLM como restricciones duras: errar hacia vacío, no hacia filtrar

**Fecha:** 2026-05-30
**Contexto:** El expansor LLM infería filtros incorrectos: `ajuste: ["slim fit"]` para "maillot de ciclismo", `ajuste: ["holgado"]` para "sudadera cómoda", `brand: "Siroko"` para cualquier query (porque el sistema prompt menciona Siroko). Estos filtros son AND lógicos en Qdrant: un filtro incorrecto elimina todos los resultados válidos.

**Propuesta inicial de la IA:** Mantener la extracción de ajuste/brand si el LLM los infería con confianza.

**Decisión:** Regla estricta en el system prompt: los filtros solo se extraen cuando el usuario los menciona **literalmente** en su query. Ejemplos negativos explícitos: "camiseta para correr" → `ajuste: []`; "sudadera cómoda" → `ajuste: []`; cualquier query sin "Siroko" → `brand: null`. La ambigüedad siempre resuelve hacia campo vacío.

**Razonamiento:** Un filtro incorrecto es mucho más dañino que no filtrar: en el primer caso el usuario no ve ningún resultado (experiencia rota); en el segundo, ve resultados ligeramente menos precisos (experiencia aceptable). El LLM tiene tendencia a "completar" inferencias que el usuario no hizo. La única defensa efectiva son ejemplos negativos explícitos en el prompt.

---

## #014 — Normalización de "invierno suave" en el adaptador de Qdrant, no en el payload

**Fecha:** 2026-05-30
**Contexto:** El fixture `Camiseta Running Manga Larga Hombre` tiene `temporada: ["primavera", "otoño", "invierno suave"]`. El filtro de Qdrant `match: {any: ["invierno"]}` no coincide con "invierno suave" (comparación de string exacta). Resultado: la camiseta técnica de running no aparecía para queries de "frío" o "invierno".

**Propuesta inicial:** Re-indexar todos los productos normalizando "invierno suave" → "invierno" en el `ProductPayloadBuilder`.

**Decisión:** Expandir el filtro en el `QdrantAdapter`: cuando el filtro aplicado incluye "invierno", se añade automáticamente "invierno suave" a los valores de match. El payload original se preserva sin modificación.

**Razonamiento:** Normalizar el payload requiere re-indexar 55 productos y perder el matiz semántico ("invierno suave" implica temperaturas más suaves que "invierno"). La expansión en el adaptador es transparente para el resto del sistema, preserva la información original y resuelve el problema en el único lugar donde importa: el filtrado. Es el cambio mínimo de superficie con el efecto correcto.

---

## #015 — Enrichment orientado a frases de búsqueda, no solo a descripción técnica

**Fecha:** 2026-05-30
**Contexto:** Los productos de categoría FITNESS y APPAREL consistentemente obtenían scores ~0.65 para queries de gimnasio, lifestyle y trail, por debajo del umbral 0.70. El enrichment LLM generaba buenas descripciones técnicas del producto pero no incluía las frases coloquiales que los usuarios usan para buscar.

**Decisión:** El prompt de enriquecimiento pide explícitamente incluir en el último párrafo frases literales de búsqueda de usuario: "Si buscas [frase_de_búsqueda_1], [frase_de_búsqueda_2]...". El texto enriquecido sirve dos propósitos: descripción técnica de catálogo + vocabulario de búsqueda coloquial.

**Razonamiento:** La similitud coseno entre un vector de búsqueda ("ropa para ir al gimnasio") y un vector de descripción técnica ("leggings con tecnología 4-way stretch y compresión media") es ~0.65. Al incluir la frase literal "ropa para ir al gimnasio" en el texto enriquecido, la similitud sube a ~0.68-0.71. El embedding captura la presencia del concepto en el documento, no solo la presencia de la palabra.

**Límite observado:** Esta estrategia tiene rendimientos decrecientes cuando las frases de búsqueda son muy genéricas (colores, ajustes de fit). Queries como "ropa naranja" o "camiseta holgada" trabajan con atributos de baja carga semántica que no suben suficientemente la similitud coseno con `text-embedding-3-small`. Son limitaciones del modelo, no del enfoque.

## #016 — Supervisord dentro del contenedor PHP, no un contenedor worker separado

**Fecha:** 2026-05-30
**Contexto:** Los eventos `ProductCreated` y `ProductUpdated` se enrutaban al transport `async` de Symfony Messenger (Doctrine queue), pero ningún proceso los consumía. La indexación en Qdrant solo ocurría mediante el comando manual `app:search:reindex`.

**Propuesta inicial:** Crear un servicio `worker` separado en `docker-compose.yml` que ejecutara `php bin/console messenger:consume async` como proceso único.

**Decisión:** Instalar `supervisor` en el contenedor PHP existente y gestionar dos programas bajo el mismo PID 1:
- `php-fpm -F` (foreground, para que supervisord lo controle)
- `messenger:consume async --time-limit=3600 --memory-limit=128M`

El CMD del contenedor pasa de `["php-fpm"]` a `["supervisord", "-c", "/etc/supervisord.conf"]`. El entrypoint existente no requiere cambios estructurales: su `exec "$@"` final lanza supervisord en lugar de php-fpm directamente.

**Razonamiento:** Un contenedor worker separado duplica la imagen, las variables de entorno, los volúmenes y el tiempo de arranque, sin aportar aislamiento real (ambos necesitan acceso a la misma base de datos, Qdrant y claves de API). Para el volumen de trabajo actual (≤ 55 productos, indexación esporádica), la colocación en el mismo contenedor es la solución mínima que funciona. Si el worker necesitara escalar horizontalmente o aislarse, el refactor a servicio separado sería trivial porque el comando de consumo no cambia.

**Opciones de `--time-limit` y `--memory-limit`:** El worker se reinicia cada hora o al superar 128 MB. Supervisord con `autorestart=true` lo relanza inmediatamente. Esto previene fugas de memoria en workers de larga vida sin añadir complejidad de monitorización.

## #017 — Emails de transición via eventos de dominio + Messenger, no llamada directa al mailer

**Fecha:** 2026-05-30
**Contexto:** Implementación de emails de confirmación y cancelación de pedido.

**Propuesta descartada:** Llamar a `MailerInterface::send()` directamente dentro de `CheckoutOrderHandler` y `CancelOrderHandler`, justo después de persistir la orden.

**Decisión:** Publicar eventos de dominio (`OrderCreated`, `OrderCancelled`) desde los handlers. Los email handlers (`SendOrderCreatedEmailHandler`, `SendOrderCancelledEmailHandler`) escuchan esos eventos como `#[AsMessageHandler]` en el transport `async` y llaman al mailer de forma desacoplada.

**Razonamiento:** Llamar al mailer dentro del handler de checkout acopla un efecto secundario (envío de email, llamada de red externa) a la transacción del negocio. Si el servidor SMTP no responde, el checkout falla. El patrón de evento desacopla completamente ambas responsabilidades: el checkout siempre termina en < 50ms y el email se envía en background por el worker. El mismo evento puede alimentar otros consumidores en el futuro (notificación push, CRM) sin tocar el handler de negocio.

---

## #018 — customerEmail persiste en la tabla `orders`, no solo viaja en el comando

**Fecha:** 2026-05-30
**Contexto:** El email del cliente es necesario para la notificación de cancelación, que ocurre en un momento diferente al checkout.

**Propuesta descartada:** Pasar `customerEmail` solo en `CheckoutOrderCommand` y guardarlo únicamente en el evento `OrderCreated`, sin columna en base de datos.

**Decisión:** Añadir columna `customer_email VARCHAR(255) NULL` a la tabla `orders` y exponerlo en el agregado `Order` como `customerEmail(): ?string`.

**Razonamiento:** `CancelOrderHandler` solo recibe el `orderId`. Para construir el evento `OrderCancelled` con el email del destinatario, necesita leer el agregado completo del repositorio. Si el email no se persiste, la notificación de cancelación no tiene destinatario. Persistirlo en el agregado es la solución mínima y honesta; no hacerlo sería un workaround que rompería en cuanto se use el email en cualquier otro contexto post-checkout.

---

## #019 — Mailpit como receptor de emails en desarrollo, dentro del stack Docker

**Fecha:** 2026-05-30
**Contexto:** Necesidad de un servidor SMTP local para interceptar los emails transaccionales sin enviarlos a cuentas reales durante el desarrollo.

**Decisión:** Añadir [Mailpit](https://github.com/axllent/mailpit) como servicio en `docker-compose.yml`. El PHP container apunta a `smtp://mailpit:1025`. La UI web está disponible en `http://localhost:8025`.

**Razonamiento:** Mailpit es el sucesor activo de Mailhog (que está sin mantenimiento desde 2022). Ofrece imagen Docker oficial ligera, interfaz web moderna con preview HTML, búsqueda y API REST para verificar emails en tests automatizados. No requiere configuración adicional: el servicio levanta junto con el resto del stack con `docker-compose up`.

<!-- Añadir nuevas entradas durante la implementación -->
