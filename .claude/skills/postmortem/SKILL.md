---
name: postmortem
description: Registro de 10 líneas después de resolver un bug de producción o incidente — síntoma, causa raíz, fix, prevención — conectado al learning loop de la agencia. Usar SIEMPRE después de arreglar algo que llegó a producción o costó más de una iteración diagnosticar.
---

# Postmortem de 10 líneas

Cada bug de producción se paga una vez si deja registro, o infinitas veces si no. Esto no es burocracia: es el insumo del learning loop.

## Cuándo aplica

- Todo bug que llegó a **producción** (aunque el fix haya sido trivial).
- Todo bug que costó **más de una iteración** diagnosticar (el costo estuvo en encontrarlo — eso es lo que hay que registrar).
- Incidentes de infraestructura/deploy aunque no haya habido bug de código.

## El registro

En `docs/postmortems.md` del proyecto (crearlo si no existe), una entrada nueva ARRIBA del archivo:

```markdown
## AAAA-MM-DD — <título corto del incidente>

- **Síntoma:** qué se veía (y quién lo detectó: cliente, monitoreo, dev).
- **Causa raíz:** la causa real, no el síntoma intermedio. "El webhook llegaba duplicado" es síntoma; "el handler no era idempotente" es causa.
- **Fix:** qué se cambió (commit/PR).
- **Detección:** por qué no lo atrapamos antes — ¿faltó un test? ¿un paso del gate? ¿una alerta?
- **Prevención:** qué queda instalado para que no se repita (test de regresión, check en el gate, alerta, regla nueva).
```

Cinco bullets, honestos y concretos. Sin culpas a personas: la causa raíz es siempre del sistema (faltaba un test, un check, una regla), no de quien tipeó.

## Después de escribirlo — el paso que importa

1. **¿La prevención ya existe de verdad?** Un postmortem cuya prevención es "tener más cuidado" no previene nada. Test de regresión commiteado, check agregado al `verify.sh`, o alerta creada — algo ejecutable.
2. **¿La lección es generalizable** (aplica al stack o a la agencia, no solo a este proyecto)? → dispará `escalar-aprendizaje` para subirla al repo central. Los mejores contenidos de las skills de la casa salieron de postmortems reales.
3. Si el incidente revela una pregunta de arquitectura abierta (¿por qué no había cola acá?), anotala para el rol `architect` — no la resuelvas de apuro dentro del postmortem.
