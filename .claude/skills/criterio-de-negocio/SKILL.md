---
name: criterio-de-negocio
description: Detectar cuándo un pedido "simple" esconde una decisión de negocio (hardcodear datos que vienen de una fuente de verdad, excepciones a una regla, cambios con consecuencia contractual o de plata) y devolverla al PM con opciones y trade-offs en vez de implementarla en silencio. Usar ANTES de implementar cualquier pedido que toque datos o reglas de negocio.
---

# Criterio de negocio: la decisión escondida en el pedido

Agnóstico de stack. No es sobre cómo escribir código: es sobre darse cuenta de que **el pedido no es una tarea técnica sino una decisión de negocio disfrazada**, y que implementarla sin levantar la mano es elegir por el cliente sin que nadie lo haya decidido.

Caso típico: "en la matriz de coberturas del seguro, mostrá que X está incluido". La implementación obvia es pisar lo que devuelve la API del cotizador para ese caso. Cinco líneas. Pero eso convierte al sistema en una segunda fuente de verdad sobre **qué cubre un seguro** — con consecuencias contractuales — y nadie decidió eso: decidieron "que se vea X", que no es lo mismo.

## Señales de que hay una decisión escondida (cuándo amerita)

Frenar y analizar si el pedido implica **una o más** de estas:

1. **Escribir a mano datos que hoy vienen de una fuente externa** (API, ERP, cotizador, feed, otro sistema). Pisar la fuente = crear una segunda verdad que se desincroniza en silencio.
2. **Una excepción a la regla general** ("solo para este cliente/producto/caso"). Hoy es un caso; el pedido n.º 30 igual a este es un sistema paralelo sin dueño.
3. **Contradecir lo que muestra otro sistema o pantalla.** El usuario ve una cosa acá y otra en el PDF/el portal del proveedor/la factura: se rompe la confianza en todo el producto.
4. **Números o condiciones con consecuencia real**: precios, coberturas, stock, impuestos, permisos. Un dato inventado acá no es un bug visual, es un problema contractual o de plata.
5. **El costo verdadero está en el mantenimiento, no en el desarrollo.** La implementación tarda una hora; la pregunta sin dueño es *¿quién actualiza esto cuando la fuente cambie, y cómo se entera?*

Si no aparece ninguna señal, es una tarea técnica normal: implementá y listo. Este skill no es para burocratizar cada ticket.

## Método

1. **Identificá la fuente de verdad** del dato o regla que el pedido toca. ¿Quién es hoy el dueño de esa información? ¿El pedido la respeta o la pisa?
2. **Separá lo que pidieron de lo que decidieron.** "Mostrá X en la matriz" es el pedido; "nuestro sistema afirma coberturas distintas a las del cotizador" es la decisión implícita. Escribí la decisión implícita en una frase — si suena grave dicha en voz alta, hay que escalarla.
3. **Enumerá las contraindicaciones concretas** de la implementación obvia: desincronización (¿qué pasa cuando la fuente cambie y esto no?), responsabilidad (¿quién responde si el dato mostrado es falso?), escala (¿qué pasa con los próximos pedidos iguales?), confianza (¿dónde más se ve este dato?).
4. **Armá 2-3 opciones con trade-offs**, incluyendo siempre la que pidieron (con su costo real explícito) y al menos una que resuelva la necesidad sin crear la segunda verdad (ej.: que el dato se corrija en la fuente; un campo editable con dueño y fecha de revisión; mostrar la diferencia como anotación en vez de pisar el dato).
5. **Devolvésela al PM en lenguaje de negocio** — ver formato abajo — con recomendación incluida. El entregable de esta tarea es esa nota, no el código.
6. **No implementes hasta que negocio decida.** Si hay una parte del pedido sin decisión escondida, esa sí se puede adelantar; la parte en cuestión espera.

## Formato de la nota al PM

Corta (10-15 líneas), sin jerga técnica — el PM tiene que poder reenviársela a negocio tal cual:

```markdown
## <Pedido> — hay una decisión de negocio antes de implementar

**Qué pidieron** — 1 línea.

**Qué implica en realidad** — la decisión implícita, en una frase.
(ej.: "que nuestro sistema afirme coberturas distintas a las que devuelve el cotizador")

**Riesgos de hacerlo directo** — 2-4 bullets concretos (desincronización, responsabilidad,
próximos casos iguales, dónde más se ve el dato).

**Opciones**
- A) Lo pedido, tal cual — costo real: <mantenimiento/riesgo>.
- B) <Alternativa que resuelve la necesidad sin segunda verdad> — costo: <...>.

**Recomendación** — cuál y por qué, en 1-2 líneas.

**Qué necesita decidir negocio** — la pregunta concreta, cerrada, respondible con sí/no o A/B.
```

## Relación con otros skills/roles

- Si el pedido es una feature completa y ambigua, el rol `spec-writer` la convierte en mini-spec — este skill es el checkpoint fino que aplica **también a pedidos chicos** que jamás pasarían por una spec ("cambiá este texto", "mostrá este dato").
- Si la decisión ya está tomada por negocio y documentada, no se re-litiga: se implementa y se registra dónde quedó decidida (AGENTS.md o el backlog del proyecto, ver `definition-of-done`).
