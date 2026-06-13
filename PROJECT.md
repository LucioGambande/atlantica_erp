# Atlantica ERP — Documentación técnica del proyecto

Handoff para desarrollo e iteración (IA/humanos). Última actualización: abril 2026.

## Resumen

ERP comercial B2B para **Atlantica Terranova**. Laravel 12 es la **fuente de verdad** de datos operativos. La interfaz principal es **Filament** en `/admin`. HubSpot alimenta clientes (companies) de forma unidireccional HubSpot → Laravel mediante colas.

| Capa | Tecnología |
|------|------------|
| Framework | Laravel 12, PHP 8.2+ |
| Base de datos | PostgreSQL (Sail/Docker) |
| Panel admin | Filament 3 |
| Auth | Sesión Laravel (`guard web`) + Breeze (secundario) |
| Permisos | Spatie Laravel Permission |
| Colas | `database` (`QUEUE_CONNECTION=database`) |
| Integración CRM | HubSpot Companies API (HTTP Client) |
| Entorno local | Laravel Sail (`docker-compose.yml`) |

---

## URLs y flujo de entrada

| Ruta | Comportamiento |
|------|----------------|
| `/` | Autenticado → `/admin`; invitado → `/admin/login` |
| `/admin` | Panel Filament (productos, clientes, pedidos, etc.) |
| `/admin/login` | Login Filament |
| `/dashboard` | Redirige a `/admin` (legacy Breeze) |
| `/login`, `/register` | Auth Breeze; tras login redirige a `/admin` |
| `/api/*` | API JSON protegida con `auth` + permisos Spatie |

**Decisión de producto:** el sistema es principalmente backend/admin. Breeze existe pero no es la superficie operativa.

---

## Infraestructura local (Sail)

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
```

| Servicio | Contenedor | Puerto host |
|----------|------------|-------------|
| App | `erp_app` | `8081` (APP_PORT) |
| PostgreSQL | `erp_postgres` | `5433` (FORWARD_DB_PORT) |

**Importante:** con `DB_HOST=pgsql` en `.env`, los comandos `php artisan` deben ejecutarse **dentro de Sail** (`./vendor/bin/sail artisan ...`). Fuera del contenedor falla la resolución del host `pgsql`.

### Colas (obligatorio para HubSpot)

```bash
# Terminal 1 — worker permanente
./vendor/bin/sail artisan queue:work --tries=3 -v

# Terminal 2 — sync HubSpot
./vendor/bin/sail artisan hubspot:sync-companies --full
```

Scheduler incremental (cada 15 min) definido en `routes/console.php`:

```php
Schedule::command('hubspot:sync-companies --incremental')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
```

Requiere `schedule:work` o cron con `schedule:run`.

---

## Autenticación y permisos

- Un solo modelo `User`, un solo guard `web` (sesión).
- Filament y Breeze comparten la misma sesión; no son dos sistemas de identidad distintos.
- Acceso al panel: `User::canAccessPanel()` → solo rol `admin`.
- Permisos Spatie (guard `web`):
  - `manage customers`, `manage products`, `manage orders`, `manage invoices`, `manage stock`
- Roles: `admin`, `sales`, `warehouse` (seed en `RolesAndPermissionsSeeder`).
- API usa middleware `permission:*` por endpoint; Filament hoy no aplica permisos por recurso (solo rol admin global).

Usuario admin esperado en seed: `admin@atlanticaterranova.com` (debe existir y tener rol `admin`).

---

## Modelos de dominio (`app/Models`)

### Customer (empresas B2B)

| Campo | Tipo / notas |
|-------|----------------|
| `name` | string |
| `tax_id`, `email`, `phone` | nullable |
| `website`, `city`, `postal_code`, `country` | nullable (mapeo HubSpot) |
| `address` | text nullable |
| `customer_type` | enum: `horeca`, `individual` |
| `credit_limit` | decimal, default 0 |
| `hubspot_company_id` | string nullable, **unique** |
| `hubspot_last_modified_at`, `last_synced_at` | timestamp nullable |
| Soft deletes | sí |

Relaciones: `orders`, `invoices`, `payments`. Accesor `balance` = facturas `issued` − pagos.

### Product

`sku` (unique), `purchase_price`, `sale_price`, `stock`. Soft deletes. Relaciones: `orderItems`, `invoiceItems`, `stockMovements`, `purchaseInvoiceItems`.

### Supplier

Datos fiscales/contacto. Soft deletes. `hasMany` `purchaseInvoices`.

### Order / OrderItem

- Order: `customer_id`, `status` (`pending`|`completed`|`cancelled`), `total_amount`.
- OrderItem: `product_id`, `quantity`, `discount_percent`, `unit_price`, `total_price`.
- Método `Order::recalculateTotalFromItems()`.

### Invoice / InvoiceItem

- Invoice: `customer_id`, `order_id` (opcional), `invoice_number` (unique), `status` (`draft`|`issued`|`paid`), `total_amount`, `issued_at`.
- InvoiceItem: `product_id` (nullable on delete), `description`, cantidades e importes.

### Payment

`customer_id`, `invoice_id` (opcional), `amount`, `payment_method`, `paid_at`.

### PurchaseInvoice / PurchaseInvoiceItem

Compras a proveedores. Estados: `draft`, `received`, `paid`.

### StockMovement

`product_id`, `type` (`in`|`out`), `quantity`, `reference_type`/`reference_id` (referencia polimórfica manual, sin `morphTo` en modelo).

### User

Auth estándar + `HasRoles` + `FilamentUser`.

---

## Servicios de negocio (`app/Services`)

| Servicio | Responsabilidad |
|----------|-----------------|
| `OrderService` | Crear pedido + líneas, calcular totales y descuentos |
| `StockService` | Descontar stock por pedido, validar existencias, registrar movimiento `out` |
| `InvoiceService` | Factura desde pedido, evita duplicado por `order_id`, marca pedido `completed` |
| `PaymentService` | Registrar pago, marcar factura `paid` si suma cubre total |
| `HubSpotCompanySyncService` | Upsert customers desde HubSpot (full/incremental) |

---

## API REST (`routes/api.php`)

Todas requieren `auth` (sesión web; no Sanctum configurado).

| Método | Ruta | Permiso |
|--------|------|---------|
| POST | `/api/orders` | `manage orders` |
| POST | `/api/invoices/orders/{orderId}` | `manage invoices` |
| POST | `/api/payments` | `manage invoices` |

Controladores: `OrderController`, `InvoiceController`, `PaymentController`.

---

## Filament (`/admin`)

Provider: `app/Providers/Filament/AdminPanelProvider.php` — `path('admin')`, branding Atlantica Terranova, CSS custom (logo, cabeceras, repeater de líneas de pedido).

Recursos CRUD (grupo navegación **ERP**):

| Resource | Entidad |
|----------|---------|
| `ProductResource` | Productos (+ soft delete avanzado) |
| `CustomerResource` | Clientes |
| `SupplierResource` | Proveedores |
| `OrderResource` | Pedidos (Repeater con cálculo reactivo de totales) |
| `InvoiceResource` | Facturas venta + `InvoiceItemsRelationManager` |
| `PurchaseInvoiceResource` | Facturas compra + items |
| `PaymentResource` | Pagos |
| `StockMovementResource` | Movimientos stock (manual) |

No hay páginas/widgets custom en `app/Filament/Pages` ni `Widgets`; usa dashboard/widgets base de Filament.

---

## Integración HubSpot

**Dirección actual:** HubSpot → Laravel (unidireccional). Laravel será maestro a futuro; la estructura permite bidireccionalidad.

### Configuración

- `config/hubspot.php`
- `.env`: `HUBSPOT_ACCESS_TOKEN`, `HUBSPOT_BASE_URL`, `HUBSPOT_PAGE_LIMIT`, `HUBSPOT_INCREMENTAL_CACHE_KEY`
- Logs: canal `hubspot` → `storage/logs/hubspot.log`

### Estructura (`app/Integrations/HubSpot/`)

| Clase | Rol |
|-------|-----|
| `HubSpotClient` | HTTP wrapper: `getCompanies()`, `getCompanyById()`, paginación `after`, retry/backoff, búsqueda incremental por `hs_lastmodifieddate` |
| `HubSpotCompanyService` | Paginación full e incremental |
| `HubSpotMapper` | Company → Customer (`name`, `phone`, `domain`→`website`, `city`, `address`, `zip`→`postal_code`, `country`, timestamps HubSpot) |

### Sync (`app/Services/HubSpotCompanySyncService.php`)

- `syncAllCompanies()` / `syncIncremental()`
- `upsertFromHubSpot(array $companyData)`:
  1. Buscar por `hubspot_company_id`
  2. Si no existe, match opcional por `website` sin `hubspot_company_id`
  3. Create o update selectivo
  4. Hook `shouldPreserveManualValue()` preparado para no sobrescribir campos editados en ERP (hoy retorna `false`)

Timestamp global incremental en cache (`hubspot.companies.last_incremental_sync_at`).

### Jobs y comando

| Componente | Descripción |
|------------|-------------|
| `SyncHubSpotCompaniesJob` | Pagina HubSpot, despacha un `SyncSingleCompanyJob` por company |
| `SyncSingleCompanyJob` | Upsert individual; retries con backoff |
| `hubspot:sync-companies` | `--full` o `--incremental` (default incremental si no se pasa `--full`) |

### Flujo operativo

```
hubspot:sync-companies
  → SyncHubSpotCompaniesJob (cola DB)
    → N × SyncSingleCompanyJob
      → HubSpotCompanySyncService::upsertFromHubSpot()
```

**Sin `queue:work` activo los jobs se encolan pero no se procesan.** El log solo registra dispatch del comando y fallos; no hay log de “job started” por defecto.

### Troubleshooting HubSpot

1. Docker/Sail corriendo: `./vendor/bin/sail up -d`
2. Worker: `./vendor/bin/sail artisan queue:work -v`
3. Token válido en `HUBSPOT_ACCESS_TOKEN`
4. Migración HubSpot aplicada: `add_hubspot_fields_to_customers_table`
5. Ver cola: `DB::table('jobs')->count()`
6. Fallos: `./vendor/bin/sail artisan queue:failed`
7. Clientes: `Customer::count()`

---

## Migraciones relevantes

- `create_customers_table` — base clientes
- `add_hubspot_fields_to_customers_table` — campos HubSpot + `website`, `city`, `postal_code`, `country`
- `create_products_table`, `create_orders_table`, `create_order_items_table`, `add_discount_percent_to_order_items_table`
- `create_invoices_table`, `create_invoice_items_table`, `create_payments_table`
- `create_suppliers_table`, `create_purchase_invoices_table`, `create_purchase_invoice_items_table`
- `create_stock_movements_table`
- `create_permission_tables` (Spatie)
- Tablas Laravel: `users`, `sessions`, `jobs`, `failed_jobs`, `cache`, etc.

---

## Seeders

`DatabaseSeeder` ejecuta:

1. `RolesAndPermissionsSeeder`
2. `CustomersSeeder`
3. `ProductsSeeder`

---

## Estructura de directorios (resumen)

```
app/
├── Console/Commands/SyncHubSpotCompaniesCommand.php
├── Filament/Resources/          # 8 recursos CRUD
├── Http/Controllers/            # API + Auth (Breeze) + Profile
├── Integrations/HubSpot/        # Cliente, mapper, company service
├── Jobs/                        # SyncHubSpotCompaniesJob, SyncSingleCompanyJob
├── Models/                      # 11 modelos dominio + User
├── Providers/Filament/          # AdminPanelProvider
└── Services/                    # Order, Invoice, Payment, Stock, HubSpot sync

config/hubspot.php
routes/web.php, api.php, auth.php, console.php
database/migrations/, seeders/
```

---

## Variables de entorno clave

```env
APP_URL=http://localhost:8081
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_DATABASE=erp_db
DB_USERNAME=erp_user
DB_PASSWORD=erp_password

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

HUBSPOT_ACCESS_TOKEN=
HUBSPOT_BASE_URL=https://api.hubapi.com
HUBSPOT_PAGE_LIMIT=100
```

---

## Deuda técnica / puntos de atención

- `StockMovement`: `reference_type/id` sin relación `morphTo` explícita.
- Permisos Spatie no aplicados por recurso en Filament (solo `canAccessPanel` con rol `admin`).
- API autenticada con guard `web` (sesión), no token API.
- HubSpot: logs mínimos en éxito; mejorar trazabilidad si hace falta operación.
- `shouldPreserveManualValue()` listo pero sin lógica de protección de campos aún.
- Breeze sigue en el proyecto; rutas legacy redirigen a Filament.

---

## Comandos útiles

```bash
# Desarrollo
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan queue:work -v
./vendor/bin/sail artisan schedule:work

# HubSpot
./vendor/bin/sail artisan hubspot:sync-companies --full
./vendor/bin/sail artisan hubspot:sync-companies --incremental
./vendor/bin/sail artisan queue:failed

# Calidad
./vendor/bin/sail artisan test
./vendor/bin/sail artisan pint
```

---

## Próximos pasos sugeridos

1. Worker permanente (Supervisor) en contenedor/producción.
2. Comando `hubspot:health-check` (token + DB + jobs pendientes).
3. Logs de progreso en jobs HubSpot (páginas, upserts, errores por company).
4. Política real de campos protegidos en sync bidireccional futuro.
5. Restricción Filament por permisos Spatie además de rol `admin`.
6. Sanctum/API tokens si la API se consume fuera del browser.
