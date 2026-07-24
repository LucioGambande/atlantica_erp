---
name: laravel-testing
description: Cómo escribir y correr tests en proyectos Laravel de la agencia — PHPUnit clásico, factories, RefreshDatabase, qué testear. Usar al escribir tests o al cerrar cualquier cambio de backend en Laravel.
---

# Testing en Laravel

Todo cambio de backend se cierra con tests en verde. Comando: `sail artisan test` (coverage y DB de test según el `AGENTS.md` del proyecto).

## Framework: PHPUnit clásico — NO Pest

La casa usa **PHPUnit con clases** (`class QuoteTest extends Tests\TestCase`) y métodos `test_snake_case` descriptivos (`test_guest_cannot_create_quotes`). No escribas tests estilo Pest (`it()/test()` funcional) aunque veas `pest-plugin` en `allow-plugins` del composer.json — es vestigial, Pest no está instalado.

## Estructura

- `tests/Feature/` — tests HTTP: `actingAs($user)`, `$this->post(...)`, asserts de status, sesión, redirect y DB (`assertDatabaseHas`). Es el tipo de test que más bugs reales atrapa.
- `tests/Unit/` — services testeados directo: `new QuoteService(...)` en `setUp()`, casos borde de la lógica de negocio.
- Fixtures compartidos en `tests/fixtures/` si el proyecto los tiene.

## Base de datos

- `RefreshDatabase` en todo test que toque DB.
- **Datos por factories siempre** (`Model::factory()->create()`, estados custom como `->withPersonalTeam()`), nunca inserts a mano ni dependencia de seeders de producción.
- La DB de test es **del proyecto** (hay proyectos con MySQL real y otros con SQLite `:memory:` + `SCOUT_DRIVER=collection`): mirar `phpunit.xml`, no asumir.

## Qué testear (prioridad)

1. **Endpoints**: comportamiento observable — status, redirect/props de Inertia, efectos en DB, jobs despachados (`Queue::fake()`, `Event::fake()`, `Mail::fake()`).
2. **Services**: reglas de negocio con casos borde y estados inválidos, no solo happy path. Especial atención a lógica de dominio delicada documentada en el `AGENTS.md` (ej. variantes sintéticas, scoping por equipo/rol).
3. **Autorización**: por endpoint protegido, al menos un test de acceso denegado (roles/permisos de spatie donde aplique).
4. **Integraciones externas**: SIEMPRE fakeadas (`Http::fake()` o fake del service). Ningún test pega a un ERP o aseguradora real.

## Reglas

- Coverage: si el proyecto define mínimo (ej. `--coverage --min=70`), respetarlo — está en el `AGENTS.md`/CI.
- Deterministas: tiempo con `Carbon::setTestNow()`/`travel()`; nada dependiente de la hora real.
- Al arreglar un bug: primero el test que lo reproduce (rojo), después el fix. Queda como regresión.
- Frontend: el gate es `build` (tsc estricto) + `lint` (+ `test:js` si el proyecto lo tiene).
