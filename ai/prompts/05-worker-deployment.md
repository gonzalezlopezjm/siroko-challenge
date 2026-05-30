# Prompt 05 — Despliegue del worker de Messenger

**Fecha:** 2026-05-30
**Herramienta:** Claude Code (claude-sonnet-4-6)
**Contexto:** Los eventos de dominio se publicaban al transport `async` de Symfony Messenger pero ningún proceso los consumía. La indexación en Qdrant y el envío de emails no ocurrían automáticamente.

---

## Prompt enviado

```
Los eventos ProductCreated/ProductUpdated están en la cola async de Messenger (Doctrine transport)
pero nadie los consume. Para que la indexación en Qdrant y el envío de emails funcionen
automáticamente necesito un worker.

Opciones que veo:
a) Añadir un servicio `worker` separado en docker-compose.yml con
   `php bin/console messenger:consume async`
b) Usar supervisord para gestionar php-fpm + messenger:consume dentro del mismo
   contenedor PHP

¿Cuál recomiendas? ¿Hay alguna consideración de producción que deba tener en cuenta?
```

---

## Respuesta de Claude Code

Claude recomendó la opción (a): contenedor `worker` separado en docker-compose, argumentando que es "la práctica estándar en microservicios", que "cada servicio debe tener una única responsabilidad" y que facilita escalar el worker independientemente. Generó un servicio `worker` completo con la misma imagen que el contenedor PHP pero sobreescribiendo el CMD.

---

## Decisión tomada (criterio propio)

Rechacé la opción (a) para este contexto. Un contenedor worker separado duplica la imagen (~500MB), las variables de entorno, los volúmenes y el tiempo de arranque del stack de desarrollo, sin aportar aislamiento real (ambos necesitan el mismo acceso a Postgres, Qdrant y las API keys).

La opción (a) sería correcta en producción con escala horizontal, pero en este stack de evaluación añade fricción sin valor. Implementé supervisord dentro del contenedor PHP existente: dos programas bajo el mismo PID 1 (`php-fpm -F` + `messenger:consume async`). Ver [DECISIONS.md #016](../DECISIONS.md#016).
