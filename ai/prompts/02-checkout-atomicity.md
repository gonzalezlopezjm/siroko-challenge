# Prompt 02 — Atomicidad del checkout y verificación de stock

**Fecha:** 2026-05-28
**Herramienta:** Claude Code (claude-sonnet-4-6)
**Contexto:** Diseño del `CheckoutOrderHandler`. Necesitaba decidir cuándo y cómo verificar el stock disponible.

---

## Prompt enviado

```
Estoy implementando el CheckoutOrderHandler. El flujo es:
1. Recibir un cartId + shippingAddress (+ customerEmail opcional)
2. Leer el carrito
3. Verificar stock de cada producto
4. Descontar stock
5. Crear la orden
6. Persistir todo

¿Dónde debe ocurrir la verificación de stock? Tengo dos opciones:
a) Solo al añadir el ítem al carrito (en AddItemToCartHandler), asumiendo que si llegó al checkout es porque había stock.
b) También en el checkout, dentro de la misma transacción que descuenta el stock.

¿Qué recomiendas? ¿Cómo manejarías el caso de que dos usuarios estén comprando el último
stock al mismo tiempo?
```

---

## Respuesta de Claude Code

Claude recomendó la opción (a): validar stock solo al añadir al carrito. Argumentó que es más eficiente (evita una consulta extra en checkout), que el carrito "ya tiene el contexto correcto" y que la probabilidad de conflicto concurrente era baja. Propuso añadir un campo `reservedStock` en el producto para manejar casos extremos.

---

## Decisión tomada (criterio propio)

Rechacé la opción (a) completamente. Un carrito puede vivir horas o días. Entre añadir al carrito y hacer checkout, otro usuario puede haber comprado el último stock. La "probabilidad baja" no existe en un sistema de producción con tráfico real.

Implementé la opción (b): verificación y descuento de stock dentro de la misma transacción de checkout, con fallo total si cualquier línea tiene stock insuficiente. Ver [DECISIONS.md #006](../DECISIONS.md#006).

Rechacé también el `reservedStock`: añade complejidad (¿cuándo se libera la reserva?) para un scope de prueba técnica donde la solución correcta es verificar en el momento de compra.
