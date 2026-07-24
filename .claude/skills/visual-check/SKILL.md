---
name: visual-check
description: Verificación visual obligatoria antes de cerrar cualquier cambio de UI — cómo levantar el dev server según el stack y qué viewports capturar. Usar siempre que un cambio afecte lo que se ve en pantalla.
---

# Verificación visual obligatoria

Antes de cerrar cualquier cambio de UI, tomá un screenshot y **leelo**. "Debería verse bien" no es verificación.

## Levantar el proyecto según el stack

Detectá el tipo de proyecto y usá el comando correcto:

- **Next/React** → `npm run dev`, URL `localhost:3000`
- **Vite** → `npm run dev`, revisá el puerto en la salida
- **Shopify theme** → `shopify theme dev`, usá el preview URL que imprime
- **VTEX IO** → `vtex link` + workspace URL del proyecto; para inspección usá `~/.claude/tools/vtex-tester/` (curl/WebFetch devuelven shell vacío, VTEX renderiza client-side)
- **Laravel** → `php artisan serve` (o `npm run dev` en paralelo si usa Vite), URL `localhost:8000`

Si no encontrás cómo levantar el server, **preguntá antes de improvisar**.

## Viewports — siempre los dos

- **Desktop: 1440px**
- **Mobile: 390px**

Capturá además cada estado relevante del cambio (hover, abierto/cerrado, vacío, error).

## Checklist de cierre

1. Screenshot en ambos viewports, leídos.
2. Comparación contra la referencia de diseño del proyecto (la indica el `AGENTS.md`).
3. Consola del browser sin errores nuevos.
