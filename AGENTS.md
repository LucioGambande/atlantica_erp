# AGENTS.md — Atlantica ERP (Laravel)

> **Template de la agencia.** Copiá este archivo como `AGENTS.md` al proyecto y completá los
> `<PLACEHOLDERS>` (buscá `<` para encontrarlos todos).
> Las convenciones genéricas de Laravel de la agencia NO viven acá: están en las skills
> `.claude/skills/laravel-conventions/` y `.claude/skills/laravel-testing/` (se instalan junto
> con este template).

## Datos del proyecto

- **PHP / Laravel:** 8.2 / 12
- **Frontend:** Filament (panel admin sobre Livewire) + Alpine.js + Tailwind (sin Inertia/React)
- **Base de datos:** PostgreSQL — DB de tests: SQLite (`database/database.sqlite`, ver phpunit.xml)
- **Package manager JS:** npm (lockfile `package-lock.json` — no mezclar con yarn)
- **Entorno local:** Sail — URL: http://localhost
- **Branches protegidos:** ninguno — se commitea y pushea directo a `main`
- **Coverage mínimo:** sin gate (no configurado)

## Comandos

```sh
./scripts/verify.sh                     # GATE ÚNICO (pint + tests + build). Ver skill verify-gate
./vendor/bin/sail up -d                 # levantar entorno
./vendor/bin/sail artisan test          # tests
./vendor/bin/sail php ./vendor/bin/pint # lint/formato
./vendor/bin/sail npm run build         # assets
```

- **Backlog / tickets:** sin definir todavía — completar cuando exista (ver skill `definition-of-done`)

Ninguna tarea se cierra sin el gate en verde.

## Dominio y arquitectura

<!-- Describir lo que NO se deduce del código: qué hace el negocio, los 3-5 conceptos
     centrales del dominio y dónde viven, decisiones de arquitectura tomadas a propósito. -->

- **Qué hace este proyecto:** Sistema de facturación y control de stock, pensado para escalar hacia un CRM propio. Prioridad de diseño: que sea escalable e integrable con otros servicios.
- **Conceptos centrales:** `Invoice`/`PurchaseInvoice` → `app/Models/Invoice.php` y `PurchaseInvoice.php`, lógica en `app/Services/InvoiceService.php`, `InvoiceNumberGenerator.php`, `InvoiceSequenceValidator.php`; expuestos vía Filament (`app/Filament/Resources/InvoiceResource.php`, `PurchaseInvoiceResource.php`).
- **Decisiones deliberadas:** ninguna documentada todavía — se van a ir tomando y registrando acá a medida que el rol `architect` (`.claude/agents/architect.md`) las defina en features no triviales.

## Integraciones y servicios externos

- **HubSpot**: sync de `Customer`/companies (CRM). Client en `app/Integrations/HubSpot/HubSpotClient.php` (+ `HubSpotMapper`, `HubSpotCompanyService`), config en `config/hubspot.php`, credenciales en `.env` (`HUBSPOT_ACCESS_TOKEN`, `HUBSPOT_CLIENT_SECRET`). Sync corre vía jobs (`SyncHubSpotCompaniesJob`, `SyncSingleCompanyJob`) y comando `hubspot:sync-companies`. Webhooks entrantes en `HubSpotWebhookController` → `ProcessHubSpotWebhookJob`, validados por `HubSpotWebhookSignatureValidator`.

## Gotchas del proyecto

<!-- Cosas que ya hicieron perder tiempo: seeds necesarios, orden de migrations,
     colas que hay que levantar, features flags, etc. -->

(ninguno todavía — se agregan a medida que aparezcan)

<!-- ai-skills:begin — generado por install.sh, no editar a mano: se regenera en cada corrida -->
## Base de conocimiento (ai-skills)

Módulos instalados: core + laravel. Repo central: ver `.claude/ai-skills-manifest`.
Este índice es agnóstico a la herramienta: cuando la tarea lo pida, leé el archivo indicado.
Con Claude Code las guías y roles se cargan solos; con otra herramienta/LLM, abrí el archivo
(los roles sirven como system prompt para el agente que uses).

| Guía | Cuándo leerla |
|---|---|
| `.claude/skills/criterio-de-negocio/SKILL.md` | Detectar cuándo un pedido "simple" esconde una decisión de negocio (hardcodear datos que vienen de una fuente de verdad, excepciones a una regla, cambios con consecuencia contractual o de plata) y devolverla al PM con opciones y trade-offs en vez de implementarla en silencio. Usar ANTES de implementar cualquier pedido que toque datos o reglas de negocio. |
| `.claude/skills/definition-of-done/SKILL.md` | Cómo entra y cómo se cierra el trabajo en la agencia — criterios de aceptación verificables ANTES de escribir código, y qué significa "done". Usar al arrancar cualquier feature o bug, y al decidir si una tarea está terminada. |
| `.claude/skills/escalar-aprendizaje/SKILL.md` | Escala aprendizajes generalizables desde este proyecto al repo central ai-skills de la agencia (commit + push) para que todos los proyectos futuros los hereden. Usar cuando descubras un gotcha de plataforma, un patrón que funcionó, o el usuario te corrija algo que aplica más allá de este proyecto. |
| `.claude/skills/laravel-conventions/SKILL.md` | Convenciones Laravel de la agencia — Services/Repositories/DTOs, Eloquent, migrations, Inertia+React, workflow con Sail y PRs. Usar al escribir o modificar código en un proyecto Laravel. |
| `.claude/skills/laravel-testing/SKILL.md` | Cómo escribir y correr tests en proyectos Laravel de la agencia — PHPUnit clásico, factories, RefreshDatabase, qué testear. Usar al escribir tests o al cerrar cualquier cambio de backend en Laravel. |
| `.claude/skills/postmortem/SKILL.md` | Registro de 10 líneas después de resolver un bug de producción o incidente — síntoma, causa raíz, fix, prevención — conectado al learning loop de la agencia. Usar SIEMPRE después de arreglar algo que llegó a producción o costó más de una iteración diagnosticar. |
| `.claude/skills/resilient-architecture/SKILL.md` | Checklist de arquitectura resiliente y performante de la agencia — idempotencia, colas, reintentos, timeouts, caching, N+1, observabilidad. Usar al diseñar features con integraciones/colas/webhooks, al revisar arquitectura, o cuando algo "se pierde", se duplica o anda lento en producción. |
| `.claude/skills/verify-gate/SKILL.md` | El gate de verificación único del proyecto — el comando que prueba que "terminé" es verdad. Usar antes de dar por cerrada CUALQUIER tarea de código (feature, bug, refactor), y al arrancar en un proyecto que todavía no tiene gate. |
| `.claude/skills/visual-check/SKILL.md` | Verificación visual obligatoria antes de cerrar cualquier cambio de UI — cómo levantar el dev server según el stack y qué viewports capturar. Usar siempre que un cambio afecte lo que se ve en pantalla. |

| Rol (prompt reutilizable) | Para qué |
|---|---|
| `.claude/agents/architect.md` | Diseña o revisa arquitectura con foco en resiliencia y performance — integraciones, marketplaces, automatizaciones, cualquier sistema con colas, webhooks o APIs externas. Usar ANTES de construir una feature no trivial, o para auditar un diseño/sistema existente. |
| `.claude/agents/code-reviewer.md` | Revisa un diff, branch o PR buscando bugs reales y desvíos de las convenciones del proyecto. Usar antes de dar por cerrada cualquier tarea no trivial, o cuando el usuario pida "revisá esto". |
| `.claude/agents/qa-visual.md` | Verifica visualmente cambios de UI levantando el proyecto, tomando screenshots en desktop y mobile, y comparando contra el diseño de referencia. Usar antes de cerrar cualquier cambio que afecte lo que se ve en pantalla. |
| `.claude/agents/spec-writer.md` | Convierte un pedido de cliente (o interno) en una mini-spec — objetivo, criterios de aceptación, fuera de alcance, preguntas abiertas — antes de que nadie escriba código. Usar cuando llega un pedido de feature no trivial, especialmente si viene en lenguaje de cliente ("quiero que la tienda haga X"). |

Aprendizaje continuo: si descubrís algo generalizable (gotcha de plataforma, patrón validado,
corrección aplicable a futuros proyectos), seguí `.claude/skills/escalar-aprendizaje/SKILL.md`
para subirlo al repo central. Lo específico de este proyecto se documenta acá, en este archivo.
<!-- ai-skills:end -->
