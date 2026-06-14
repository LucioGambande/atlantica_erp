# Atlantica ERP — Documentación técnica del proyecto

Handoff para desarrollo e iteración (IA/humanos). Última actualización: junio 2026.

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
| Integración CRM | HubSpot Companies API + webhooks |
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
| `hubspot_properties` | json nullable (snapshot propiedades HubSpot) |
| Soft deletes | sí |

Relaciones: `orders`, `invoices`, `payments`. Accesor `balance` = facturas `issued` − pagos.

### Product

`sku` (unique), `purchase_price`, `sale_price`, `stock`. Soft deletes. Relaciones: `orderItems`, `invoiceItems`, `stockMovements`, `purchaseInvoiceItems`.

### Supplier

Datos fiscales/contacto. Soft deletes. `hasMany` `purchaseInvoices`.

### Order / OrderItem

- Order: `customer_id`, `status` (`pending`|`completed`|`cancelled`), `total_amount`.
- OrderItem: `product_id`, `quantity`, `discount_percent`, `unit_price`, `total_price`.
- Accesor `discounted_total` y helper `LineItemTotals`.
- Método `Order::recalculateTotalFromItems()` (suma con descuento por línea).

### Invoice / InvoiceItem

- Invoice: `customer_id`, `order_id` (opcional), `invoice_number` (unique), `status` (`draft`|`issued`|`paid`), `total_amount`, `issued_at`.
- Métodos: `recalculateTotalFromItems()`, `paidAmount()`, `remainingAmount()`, `canRegisterPayment()`.
- InvoiceItem: `product_id`, `description`, `quantity`, `unit_price`, `discount_percent`, `total_price`.
- Accesor `discounted_total`.

### PaymentMethod / Payment (polimórfico)

**PaymentMethod** (`payment_methods`): `name`, `slug` (unique), `detail_type`, `is_active`, `sort_order`.

Tipos de detalle (`detail_type` → morph map):

| Tipo | Tabla / modelo | Campos clave |
|------|----------------|--------------|
| `bank_transfer` | `BankTransferPaymentDetail` | `transaction_number` (obligatorio), `bank_reference` |
| `card` | `CardPaymentDetail` | `authorization_code`, `card_last_four` |
| `cash` | `CashPaymentDetail` | `notes` |
| `bizum` | `BizumPaymentDetail` | `operation_code`, `phone` |
| `cheque` | `ChequePaymentDetail` | `cheque_number` (obligatorio), `bank_name` |
| `generic` | `GenericPaymentDetail` | `notes` |

**Payment** (`payments`): `customer_id`, `invoice_id`, `payment_method_id`, `detail_type`, `detail_id` (morph), `amount`, `paid_at`.

- El campo legacy `payment_method` (string) fue reemplazado por `payment_method_id` + detalle polimórfico.
- Registro vía `PaymentService::registerPayment()` / `registerInvoicePayment()`.
- En Filament: acción **Registrar pago** en facturas emitidas; CRUD **Métodos de pago**; formularios dinámicos según `detail_type` (`PaymentDetailForm`).

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
| `PaymentService` | Registrar pago + detalle polimórfico; marcar factura `paid` si suma cubre total |
| `PaymentDetailService` | Crear detalle según `PaymentMethod.detail_type`; resumen legible |
| `HubSpotCompanySyncService` | Upsert customers desde HubSpot (full/incremental/webhook) |

---

## API REST (`routes/api.php`)

Todas requieren `auth` (sesión web; no Sanctum configurado).

| Método | Ruta | Permiso |
|--------|------|---------|
| POST | `/api/orders` | `manage orders` |
| POST | `/api/invoices/orders/{orderId}` | `manage invoices` |
| POST | `/api/payments` | `manage invoices` — requiere `payment_method_id` + `detail` opcional |

Controladores: `OrderController`, `InvoiceController`, `PaymentController`.

---

## Filament (`/admin`)

Provider: `app/Providers/Filament/AdminPanelProvider.php` — `path('admin')`, branding Atlantica Terranova, CSS custom (logo, cabeceras, repeater de líneas de pedido).

Recursos CRUD (grupo navegación **ERP**):

| Resource | Entidad |
|----------|---------|
| `ProductResource` | Productos (+ soft delete avanzado) |
| `CustomerResource` | Clientes (+ botón sync HubSpot) |
| `SupplierResource` | Proveedores |
| `OrderResource` | Pedidos (Repeater con descuento y cálculo reactivo) |
| `InvoiceResource` | Facturas venta + items + pagos + acción **Registrar pago** |
| `PaymentMethodResource` | Métodos de pago (CRUD, define tipo de detalle) |
| `PaymentResource` | Pagos (alta + vista; sin edición de detalle) |
| `PurchaseInvoiceResource` | Facturas compra + items |
| `StockMovementResource` | Movimientos stock (manual) |

Relation managers destacados: `InvoiceItemsRelationManager`, `PaymentsRelationManager`.

**i18n:** `APP_LOCALE=es`, traducciones Filament en `lang/vendor/`. Labels de campos en recursos aún parciales (muchos autogenerados en inglés si no tienen `->label()`).

---

## Integración HubSpot

**Dirección actual:** HubSpot → Laravel (unidireccional). Laravel será maestro a futuro. Doble ingreso: cron incremental + webhook + sync manual desde Filament.

### Configuración

- `config/hubspot.php` — field map, `erp_only_fields`, webhook, `client_secret`
- `.env`: `HUBSPOT_ACCESS_TOKEN` (debe empezar por `pat-eu1-` o `pat-na1-`), `HUBSPOT_CLIENT_SECRET`, `HUBSPOT_SKIP_WEBHOOK_SIGNATURE`, `HUBSPOT_BASE_URL`, `HUBSPOT_PAGE_LIMIT`, `HUBSPOT_INCREMENTAL_CACHE_KEY`
- Logs: canal `hubspot` → `storage/logs/hubspot-YYYY-MM-DD.log`
- Errores de jobs también pueden aparecer en `storage/logs/laravel.log`

### Estructura (`app/Integrations/HubSpot/`)

| Clase | Rol |
|-------|-----|
| `HubSpotClient` | HTTP wrapper, validación token, retry/backoff, mensajes 401/403 |
| `HubSpotCompanyService` | Paginación full e incremental |
| `HubSpotMapper` | Company → Customer (config-driven desde `config/hubspot.php`) |
| `HubSpotCompanyPropertyList` | Propiedades sincronizadas |
| `HubSpotWebhookSignatureValidator` | Validación firma webhook v3 |

### Sync (`app/Services/HubSpotCompanySyncService.php`)

- `syncAllCompanies()` / `syncIncremental()` / `syncByHubSpotCompanyId()`
- `upsertFromHubSpot()`: match por `hubspot_company_id`, fallback `website`, guarda `hubspot_properties` JSON
- `shouldPreserveManualValue()` preparado para campos `erp_only_fields` (lógica aún mínima)

### Jobs, comandos y webhook

| Componente | Descripción |
|------------|-------------|
| `SyncHubSpotCompaniesJob` | Pagina HubSpot, despacha `SyncSingleCompanyJob` por company |
| `SyncSingleCompanyJob` | Upsert individual; 5 reintentos; log en canal `hubspot` por intento |
| `SyncSingleCompanyByIdJob` | Fetch + upsert de una company por ID |
| `ProcessHubSpotWebhookJob` | Procesa eventos webhook → `SyncSingleCompanyByIdJob` |
| `hubspot:sync-companies` | `--full` o `--incremental` |
| `hubspot:health-check` | Valida token + acceso API companies |
| `POST /api/webhooks/hubspot` | Webhook HubSpot (firma + cola) |

### Flujo operativo

```
Manual/cron: hubspot:sync-companies → SyncHubSpotCompaniesJob → N × SyncSingleCompanyJob
Webhook: POST /api/webhooks/hubspot → ProcessHubSpotWebhookJob → SyncSingleCompanyByIdJob
Filament: botón "Sincronizar desde HubSpot" en CustomerResource → SyncHubSpotCompaniesJob
```

**Sin `queue:work` activo los jobs se encolan pero no se procesan.**

### Troubleshooting HubSpot

1. Docker/Sail: `./vendor/bin/sail up -d`
2. Worker: `./vendor/bin/sail artisan queue:restart && ./vendor/bin/sail artisan queue:work -v`
3. Token: Private App `pat-eu1-...` / `pat-na1-...` (no Developer API key `eu1-...`)
4. Scope: `crm.objects.companies.read` mínimo
5. Migraciones: `add_hubspot_fields_to_customers_table` + `add_hubspot_properties_to_customers_table`
6. `./vendor/bin/sail artisan hubspot:health-check` debe pasar antes de sync
7. Cola: `DB::table('jobs')->count()` / `queue:failed`
8. Logs: `storage/logs/hubspot-*.log` y `laravel.log`

---

## Migraciones relevantes

- `create_customers_table`, `add_hubspot_fields_to_customers_table`, `add_hubspot_properties_to_customers_table`
- `create_products_table`, `create_orders_table`, `create_order_items_table`, `add_discount_percent_to_order_items_table`
- `create_invoices_table`, `create_invoice_items_table`, `add_discount_percent_to_invoice_items_table`
- `create_payments_table`, `create_payment_methods_and_details_tables`, `add_payment_method_polymorphism_to_payments_table`
- `create_suppliers_table`, `create_purchase_invoices_table`, `create_purchase_invoice_items_table`
- `create_stock_movements_table`, `create_permission_tables` (Spatie)
- Tablas Laravel: `users`, `sessions`, `jobs`, `failed_jobs`, `cache`, etc.

---

## Seeders

| Seeder | Uso |
|--------|-----|
| `DatabaseSeeder` | `RolesAndPermissionsSeeder`, `AdminUserSeeder`, `CustomersSeeder`, `ProductsSeeder` |
| `AdminUserSeeder` | Usuario admin Filament |
| `PaymentMethodSeeder` | Métodos de pago por defecto (también se crean en migración) |
| `FacturasSeeder` | Facturas históricas HORECA (manual: `db:seed --class=FacturasSeeder`) |
| `ProductosAtamisqueSeeder` | Catálogo Atamisque (manual) |

`FacturasSeeder` no está en `DatabaseSeeder` por defecto.

---

## Estructura de directorios (resumen)

```
app/
├── Console/Commands/          # hubspot:sync-companies, hubspot:health-check
├── Filament/
│   ├── Forms/PaymentDetailForm.php
│   └── Resources/             # 9 recursos CRUD + relation managers
├── Http/Controllers/          # API + Auth + HubSpotWebhookController
├── Integrations/HubSpot/
├── Jobs/                      # Sync*, ProcessHubSpotWebhookJob
├── Models/
│   └── PaymentDetails/        # Detalles polimórficos de pago
├── Providers/Filament/
├── Services/                  # Order, Invoice, Payment, PaymentDetail, Stock, HubSpot
└── Support/                   # LineItemTotals, PaymentDetailType

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

HUBSPOT_ACCESS_TOKEN=          # pat-eu1-... o pat-na1-...
HUBSPOT_CLIENT_SECRET=         # firma webhooks
HUBSPOT_SKIP_WEBHOOK_SIGNATURE=false
HUBSPOT_BASE_URL=https://api.hubapi.com
HUBSPOT_PAGE_LIMIT=100
```

---

## Fallas conocidas y riesgos operativos

Problemas que ya ocurrieron o pueden repetirse en desarrollo/producción.

### Infraestructura y entorno

| Síntoma | Causa probable | Qué hacer |
|---------|----------------|-----------|
| `could not translate host name "pgsql"` | `php artisan` fuera de Sail | Usar `./vendor/bin/sail artisan ...` |
| `iconv: iconv_open` en terminal | Shell/macOS locale | Ignorar; no afecta Laravel |
| Jobs en cola pero nada cambia | Sin `queue:work` | `./vendor/bin/sail artisan queue:work -v` |
| `failed_jobs` vacío pero worker muestra FAIL | Reintentos pendientes (`$tries` > 1) | Esperar o revisar `hubspot-*.log` / `laravel.log` |
| Migraciones no aplicadas | `migrate` no corrido tras pull | `./vendor/bin/sail artisan migrate` |

### HubSpot

| Síntoma | Causa probable | Qué hacer |
|---------|----------------|-----------|
| 401 en sync | Token incorrecto (`eu1-...` en vez de `pat-eu1-...`) | Private App → Auth → Show token |
| `hubspot:health-check` falla formato | Developer API key en `.env` | Reemplazar por access token Private App |
| 127 jobs FAIL sin log en `hubspot` | Error en `laravel.log` (ej. columna faltante) | `migrate`; revisar `laravel.log` |
| Clientes no actualizan en tiempo real | Webhook no registrado en HubSpot o worker parado | Configurar webhook + `queue:work` |
| Webhook 401/403 | `HUBSPOT_CLIENT_SECRET` incorrecto o firma deshabilitada mal | Revisar secret y `HUBSPOT_SKIP_WEBHOOK_SIGNATURE` |

### Pagos y facturas

| Síntoma | Causa probable | Qué hacer |
|---------|----------------|-----------|
| Factura `paid` sin registro en `payments` | `FacturasSeeder` marca status sin crear pagos | Backfill manual o comando de reconciliación (pendiente) |
| No aparece botón **Registrar pago** | Factura en `draft` o ya `paid` | Emitir factura primero (`issued`) |
| Error al pagar con transferencia | Falta `transaction_number` | Completar campo en modal de pago |
| `payment_method` column not found | Migración polimórfica pendiente | `./vendor/bin/sail artisan migrate` |
| `balance` del cliente incorrecto | Suma todos los pagos vs solo facturas `issued` | Revisar lógica en `Customer::getBalanceAttribute()` |

### Datos y seeders

| Síntoma | Causa probable | Qué hacer |
|---------|----------------|-----------|
| Facturas omitidas en seeder | Cliente sin `hubspot_company_id` | Sync HubSpot antes de `FacturasSeeder` |
| Duplicados al re-seed facturas | Seeder evita duplicar por `invoice_number` | Normal; borrar datos si se quiere reimportar |
| Descuentos mal en facturas viejas | Antes estaban en texto `(desc 5%)` en descripción | Re-seed tras fix de `discount_percent` |

### UI / Filament

| Síntoma | Causa probable | Qué hacer |
|---------|----------------|-----------|
| Labels en inglés (Tax id, Created at) | Campos sin `->label()` en resources | Centralizar en `lang/es/erp.php` o labels explícitos |
| Widget Filament info en inglés | Widget por defecto de Filament | Quitar `FilamentInfoWidget` del panel |
| Pagos no editables | Diseño intencional (auditoría) | Solo crear/ver/eliminar |

---

## Backlog técnico

Priorizado por impacto. No es roadmap de producto; es deuda y mejoras de ingeniería.

### P0 — Estabilidad operativa

- [ ] **Supervisor / worker permanente** en Docker y producción (`queue:work` + `schedule:work`)
- [ ] **Comando backfill pagos** para facturas `paid` del seeder sin `payments` asociados
- [ ] **Verificar migraciones** `payment_methods` aplicadas en todos los entornos
- [ ] **Registrar webhooks HubSpot** apuntando a `/api/webhooks/hubspot` en producción
- [ ] **Health-check pre-deploy**: `hubspot:health-check`, `migrate:status`, worker activo

### P1 — Integridad de datos y negocio

- [ ] **Pagos parciales** (múltiples pagos hasta cubrir total; hoy el modal registra el saldo completo)
- [ ] **Revertir estado factura** si se elimina un pago (`paid` → `issued`)
- [ ] **Protección campos ERP** en sync HubSpot (`erp_only_fields` + `shouldPreserveManualValue()` real)
- [ ] **Validar stock** antes de completar pedido / emitir factura (hoy parcial en `StockService`)
- [ ] **Unificar cálculo de balance** cliente (facturas emitidas vs pagadas, pagos sin factura)
- [ ] **Tests** para `PaymentService`, `LineItemTotals`, `HubSpotMapper`, descuentos por línea

### P2 — Permisos, roles y seguridad

- [ ] **Roles finales**: `superadmin` / `operator` / `viewer` (hoy: `admin`, `sales`, `warehouse`)
- [ ] **Permisos Spatie por recurso Filament** (no solo `canAccessPanel` con rol `admin`)
- [ ] **Sanctum / API tokens** si la API se consume fuera del browser
- [ ] **Políticas** `PaymentPolicy`, `InvoicePolicy` para acciones sensibles (registrar pago, anular)
- [ ] **Auditoría** de quién registró un pago (columna `created_by` / activity log)

### P3 — HubSpot y CRM

- [ ] **Logs de progreso** en sync (páginas procesadas, created/updated/failed por job)
- [ ] **Dashboard sync** en Filament (último sync, jobs pendientes, errores)
- [ ] **Reducir o coordinar** cron 15 min vs webhooks (evitar sync redundante)
- [ ] **Bidireccional Laravel → HubSpot** cuando ERP sea maestro
- [ ] **MCP HubSpot** solo para exploración en dev; producción sigue REST + webhooks

### P4 — UX Filament e i18n

- [ ] **`lang/es/erp.php`** — diccionario central de campos, estados, navegación
- [ ] **Labels completos** en todos los resources y relation managers
- [ ] **`formatStateUsing`** en badges de estado (`pending`, `issued`, `horeca`, etc.)
- [ ] **Quitar `FilamentInfoWidget`** y widgets innecesarios del dashboard
- [ ] **Fechas Carbon** en formato español consistente en tablas

### P5 — Pagos avanzados

- [ ] **Nuevos tipos polimórficos** sin migración manual (hoy cada tipo = tabla + modelo + entrada en `PaymentDetailType`)
- [ ] **Edición controlada** de detalle de pago (solo admin, con auditoría)
- [ ] **Conciliación bancaria** importando CSV y matcheando `transaction_number`
- [ ] **Integración TPV / pasarela** (futuro ecommerce Shopify)

### P6 — Limpieza y arquitectura

- [ ] **`StockMovement`**: relación `morphTo` explícita en `reference`
- [ ] **Retirar Breeze** o documentar como solo fallback de auth
- [ ] **Vistas Blade legacy** (`invoices/index`, `show`) — actualizar API pagos o deprecar
- [ ] **Módulo remitos** (precursor: `Order`; no existe aún)
- [ ] **Despliegue Laravel Cloud** + PostgreSQL gestionado
- [ ] **Shopify** como canal ecommerce secundario (futuro)

---

## Deuda técnica resumida (quick reference)

- `StockMovement`: `reference_type/id` sin `morphTo` en modelo.
- Permisos Spatie no aplicados por recurso en Filament.
- API con guard `web` (sesión), no tokens.
- Facturas seeder: `paid` sin `payments` correspondientes.
- Tipos de pago polimórficos: extensión requiere código (no solo CRUD).
- Breeze + vistas Blade legacy coexisten con Filament.
- i18n parcial en UI de administración.

---

## Comandos útiles

```bash
# Desarrollo
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
./vendor/bin/sail artisan queue:work -v
./vendor/bin/sail artisan schedule:work

# HubSpot
./vendor/bin/sail artisan hubspot:health-check
./vendor/bin/sail artisan hubspot:sync-companies --full
./vendor/bin/sail artisan hubspot:sync-companies --incremental
./vendor/bin/sail artisan queue:restart
./vendor/bin/sail artisan queue:failed

# Datos
./vendor/bin/sail artisan db:seed --class=PaymentMethodSeeder
./vendor/bin/sail artisan db:seed --class=FacturasSeeder

# Calidad
./vendor/bin/sail artisan test
./vendor/bin/sail artisan pint
```
