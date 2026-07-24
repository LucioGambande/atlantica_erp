---
name: laravel-conventions
description: Convenciones Laravel de la agencia — Services/Repositories/DTOs, Eloquent, migrations, Inertia+React, workflow con Sail y PRs. Usar al escribir o modificar código en un proyecto Laravel.
---

# Convenciones Laravel de la agencia

Basadas en los proyectos reales de la agencia (Laravel 12, PHP 8.2, Inertia+React). El `AGENTS.md` del proyecto define lo variable (package manager, DB, coverage); ante conflicto, gana el proyecto.

## Layering — dónde vive cada cosa

- **Controllers finos**: validan, delegan al service, devuelven respuesta Inertia o JSON. Nada de lógica de negocio en el controller.
- **Services** (`app/Services/`) son el corazón: clases `*Service` inyectadas por constructor, **organizadas en sub-namespaces por dominio o vendor** (`Services/Quote/`, `Services/Sancor/`, `Services/Integration/`). Integraciones externas SIEMPRE en `Services/<Vendor>/`, con clase base abstracta si hay variantes (`AbstractSancorService`).
- **Repositories** (`app/Repositories/`) para acceso a datos no trivial; en proyectos que lo usan, interfaz + implementación `Eloquent/` bindeada en un provider. Mirar qué hace el proyecto antes de introducirlos.
- **DTOs** para pasar datos a services: `app/DTOs/` o `Services/<Dominio>/Dto/` según el proyecto. Datos tipados, no arrays asociativos sueltos.
- **Enums nativos backed** para estados; con helpers `label()` (etiquetas en español) donde el proyecto los usa. Ubicación según el proyecto (`app/Enums/` o `app/Domain/<X>/`).
- Complementos estándar: `Observers/`, `Policies/` (+ spatie/laravel-permission donde hay roles), `Traits/`, `Jobs/`, commands de consola para operaciones e imports.

## Validación

- **Inline `$request->validate([...])` es aceptable y común** en la casa. Form Requests para validaciones largas, reutilizadas o con autorización propia — criterio, no dogma. Seguir el estilo del controller vecino.

## Eloquent

- Relaciones y casts declarados en el modelo; `$fillable` explícito.
- Prevenir N+1: eager loading en cada query que itera relaciones.
- Scopes para queries repetidas (ej. real de la casa: `withoutSyntheticDefault()`); raw SQL solo con bindings y necesidad real.

## Migrations

- Nunca editar una migration corrida en otro entorno: crear una nueva.
- Foreign keys con `constrained()` y política de borrado explícita.
- Factories y seeders actualizados en el mismo cambio que la migration.

## Colas y jobs

- Lo lento (imports de ERP, cotizaciones a APIs de terceros, syncs) va a jobs, con colas separadas por duración cuando hay jobs largos (`high/default/low` + cola dedicada para lo pesado).
- **Regla de oro Redis**: `retry_after` de la conexión > `--timeout` del worker > timeout del job más largo — si no, dos workers duplican jobs a mitad de ejecución. Los jobs largos fijan su conexión en el constructor.

## Frontend — Inertia + React + TypeScript

- El stack fijo de la casa: **Inertia + React 18 + TypeScript + shadcn/ui (Radix) + Tailwind**, Vite con SSR, React Hook Form + Zod, TanStack Query/Table. No introducir Blade-UI, Livewire ni Vue.
- Con Inertia no hay REST API separada: los controllers `Api/` se llaman desde forms Inertia dentro del stack web.
- El gate de tipos del frontend es el build (`tsc` estricto vía `build`); ESLint + Prettier (organize-imports, tailwindcss).
- **Texto visible al usuario en español.**

## Workflow

- **Sail para todo comando local** (`sail artisan ...`, `sail npm/yarn ...`). Package manager según el proyecto (mirar el lockfile — no mezclar npm/yarn).
- **Nunca commit/push directo a `main` ni `production`**: branch `feature/<TICKET>` o `feature/<slug>`, integración por PR.
- **Conventional Commits con referencia al ticket**: `feat(BL-123): ...`, `fix: ...`.
- Formato: **Pint preset laravel** (`composer pint` o `./vendor/bin/pint`) antes de cerrar. No hay PHPStan en la base de la casa; no lo agregues sin pedirlo.
- Antes de dar por terminado: tests en verde + Pint + build del frontend en verde.
