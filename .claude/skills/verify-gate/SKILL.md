---
name: verify-gate
description: El gate de verificación único del proyecto — el comando que prueba que "terminé" es verdad. Usar antes de dar por cerrada CUALQUIER tarea de código (feature, bug, refactor), y al arrancar en un proyecto que todavía no tiene gate.
---

# Gate de verificación único

"Terminé" no es una opinión: es el resultado de UN comando. Ninguna tarea de código se cierra con el gate rojo, y ningún push va con el gate sin correr.

## Correr el gate

1. El comando está en el `AGENTS.md` del proyecto (sección Comandos, `COMANDO_VERIFY`). Convención: `./scripts/verify.sh`.
2. Correlo ANTES de declarar terminada la tarea. Si falla, la tarea sigue abierta — iterá hasta verde. Nunca reportes éxito con el gate rojo ni "casi verde".
3. Si el gate está roto por algo ajeno a tu cambio, no lo saltees en silencio: reportalo y acordá con el usuario cómo seguir.
4. Reglas de la casa asociadas: no pushear con gate rojo; en proyectos con branches protegidos, el PR no se abre sin gate verde.

## Si el proyecto NO tiene gate: crealo

Un `scripts/verify.sh` con `set -e` que encadene los pasos del stack. Defaults por stack (ajustar a los comandos reales del `AGENTS.md`):

| Stack | Pasos del gate |
|---|---|
| Laravel | `composer pint --test` (o pint) → `sail artisan test` (+ coverage si el proyecto define mínimo) → `yarn/npm build` (tsc estricto) → `lint` del frontend |
| NestJS | typecheck (`tsc --noEmit`) → lint → build → tests (unit/e2e; en bots, la batería determinística de conversaciones) |
| App Shopify | lint → tests de lógica pura (`node --test`/vitest) → build |
| Theme Shopify | `shopify theme check` con **0 offenses** → verificación visual (skill `visual-check`) |
| VTEX | build de `vtex link` sin errores → checklist pre-delivery con `vtex-tester` → verificación visual |

- El gate debe poder correrlo cualquiera (dev nuevo, CI, agente) sin pasos manuales previos no documentados.
- Al crearlo: agregarlo al `AGENTS.md` como `COMANDO_VERIFY` y commitearlo. Si el gate nuevo revela deuda (tests rotos preexistentes), documentarla — no bajar el gate para que pase.

## Qué NO es el gate

- No reemplaza la verificación funcional: si el cambio tiene superficie visible o de runtime, además hay que ejercitarlo (levantar la app, probar el flujo, skill `visual-check` si hay UI).
- No es negociable por apuro: un gate que se saltea "solo esta vez" deja de ser gate.
