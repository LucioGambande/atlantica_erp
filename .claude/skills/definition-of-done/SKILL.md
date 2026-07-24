---
name: definition-of-done
description: Cómo entra y cómo se cierra el trabajo en la agencia — criterios de aceptación verificables ANTES de escribir código, y qué significa "done". Usar al arrancar cualquier feature o bug, y al decidir si una tarea está terminada.
---

# Definition of Done

Ninguna feature o bug arranca sin criterios de aceptación escritos, y nada está "done" hasta cumplir la checklist de cierre. Esto evita construir lo que no se pidió y descubrir en QA lo que faltó.

## Antes de escribir código: criterios de aceptación

1. Convertí el pedido en **2-5 criterios verificables** — observables y binarios (se cumplen o no), no intenciones:
   - ❌ "mejorar el flujo de checkout"
   - ✅ "un cliente B2B con net terms puede completar el checkout sin ingresar tarjeta"
   - ✅ "si el ERP no responde, la orden queda en `pending_sync` y se reintenta; nunca se pierde"
2. Incluí el caso negativo/borde relevante (qué pasa cuando falla, cuando está vacío, cuando llega dos veces) — ahí viven los bugs.
3. **Registralos donde el proyecto lo defina** (`AGENTS.md`: BACKLOG.md, ticket de Jira/Linear, descripción del PR). Si el proyecto no define lugar, proponé `BACKLOG.md` (patrón de la casa: ítem con criterios, se marca done cuando se cumplen).
4. Si el pedido es ambiguo y los criterios podrían ir para dos lados, **confirmalos con el usuario antes de construir** — 2 minutos de pregunta ahorran una iteración entera. Si son obvios, escribilos y seguí.
5. Anotá también qué queda **fuera de alcance** cuando el pedido lo insinúe — evita el scope creep silencioso.

## Para bugs, además

- Primero **reproducir** (test en rojo o pasos verificados), después arreglar. Un fix sin reproducción es una hipótesis.
- El criterio de aceptación de un bug siempre incluye su test de regresión.

## Checklist de cierre — "done" significa TODO esto

1. ✅ Cada criterio de aceptación verificado (no "debería andar": lo ejercitaste).
2. ✅ Gate de verificación del proyecto en verde (skill `verify-gate`).
3. ✅ Si hay UI: verificación visual hecha (skill `visual-check`).
4. ✅ Test nuevo cubriendo lo agregado (y regresión si era bug).
5. ✅ Commiteado/pusheado según el workflow del proyecto (branch + PR si hay branches protegidos).
6. ✅ Si aprendiste algo generalizable en el camino: escalado (skill `escalar-aprendizaje`).

Al reportar el cierre, listá los criterios y cómo se verificó cada uno — no "listo ✅" a secas.

## Regla de proporcionalidad

Para un typo o un cambio trivial, no burocratices: el criterio es implícito y el gate alcanza. La DoD completa aplica a features y bugs con comportamiento — usá criterio, no ceremonia.
