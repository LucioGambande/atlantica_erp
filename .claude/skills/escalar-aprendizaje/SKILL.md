---
name: escalar-aprendizaje
description: Escala aprendizajes generalizables desde este proyecto al repo central ai-skills de la agencia (commit + push) para que todos los proyectos futuros los hereden. Usar cuando descubras un gotcha de plataforma, un patrón que funcionó, o el usuario te corrija algo que aplica más allá de este proyecto.
---

# Escalar aprendizaje al repo central

Los proyectos con estos skills instalados no solo consumen conocimiento: lo devuelven. Cuando algo aprendido acá le serviría al próximo proyecto, se sube al repo central.

## 1. Clasificar el aprendizaje

- **Específico de ESTE proyecto** (una ruta, un dato del cliente, una decisión local) → va al `AGENTS.md` del proyecto. No escalar.
- **Genérico del stack** (un gotcha de Shopify/VTEX/Laravel/NestJS, un patrón que funcionó, un error de plataforma y su solución) → va a la skill del módulo correspondiente en el repo central.
- **Genérico de la agencia** (proceso, QA, arquitectura, revisión) → va a `core/` en el repo central.
- Si dudás entre proyecto y stack, preguntate: *¿el próximo proyecto de este stack se beneficia?* Si no está claro, preguntale al usuario antes de escalar.

## 2. Ubicar el repo central

Leé `.claude/ai-skills-manifest` en la raíz del proyecto: `source=` es la ruta local del repo central (de quien lo instaló), `remote=` su URL git y `modules=` los módulos instalados. En esta máquina:

1. Si `source=` existe localmente, usalo.
2. Si no (sos otro miembro del equipo u otra máquina), probá `~/Sites/ai-skills`.
3. Si tampoco está, cloná `remote=` en `~/Sites/ai-skills` (confirmando con el usuario) y seguí desde ahí.

## 3. Escribir el aprendizaje

- Encontrá el archivo correcto: la `SKILL.md` del módulo si es una regla operativa corta; su `references/*.md` si es material extenso. Gotchas van a la sección de gotchas si existe.
- **Antes de escribir, buscá duplicados** (grep por palabras clave en el repo central): si ya existe, mejorá la entrada existente en vez de duplicar.
- Respetá el formato y el idioma del archivo. Entrada concisa: el qué, el porqué (síntoma que lo delató) y el cómo se resuelve. Sin datos del cliente (nombres, URLs privadas, credenciales) — el aprendizaje se generaliza, el ejemplo se anonimiza.

## 4. Commit + push

En el repo central:

```sh
git -C <repo-central> pull --rebase
git -C <repo-central> add <archivos tocados>
git -C <repo-central> commit -m "learn(<modulo>): <resumen corto>"
git -C <repo-central> push
```

- Si el push falla (sin red, sin permisos, conflicto), dejá el commit local hecho y avisale al usuario para que lo empuje después — el aprendizaje no se pierde.
- Al terminar, decile al usuario **qué se escaló y a qué archivo**, en una línea.

## Reglas

- Escalá aprendizajes **validados** (lo viste fallar/funcionar, o el usuario te corrigió) — no hipótesis. Una regla equivocada en el repo central contamina todos los proyectos.
- Correcciones explícitas del usuario que aplican al stack: escalalas directamente. Inferencias propias: mostrá el texto propuesto y confirmá antes de pushear.
- Los demás proyectos reciben lo nuevo al re-correr `./install.sh` — pero los archivos ya instalados no se pisan, así que el canal principal de distribución es el repo central mismo; mantenerlo limpio importa más que mantenerlo grande.
