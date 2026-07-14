# Atlántica Terranova ERP — Contexto para asistente IA

> Este archivo existe para dar contexto operativo y de negocio al asistente IA (Cursor).
> La documentación técnica completa está en `PROJECT.md`.
> **Mantener actualizado** al completar cambios funcionales (ver regla `.cursor/rules/maintain-context-md.mdc`).
> Última actualización: 14 de julio 2026.

---

## Qué es este proyecto

ERP comercial B2B para **Atlántica Terranova 1908 SL**, empresa importadora y distribuidora de vinos argentinos en España (Málaga). Importa exclusivamente vinos de **Bodega Atamisque** (Mendoza). El negocio opera entre un hub logístico en Bélgica y clientes HORECA + distribuidores regionales en España.

Este ERP es la fuente de verdad operativa del negocio. No es un proyecto de agencia — es el sistema interno de la empresa.

---

## Stack y entorno

- **Laravel 12** + **PHP 8.2+**
- **PostgreSQL** vía Laravel Sail (Docker)
- **Filament 3** como panel admin principal (`/admin`)
- **Spatie Laravel Permission** para roles y permisos
- **HubSpot** como CRM (sync unidireccional HubSpot → Laravel, por ahora)
- **Colas database** para jobs de sync

Entorno local: `./vendor/bin/sail up -d`. Puerto app: `8081`. Puerto PostgreSQL: `5433`.

Panel Filament: menú lateral en 4 grupos (**Facturación**, **Inventario**, **Clientes**, **Compras**), colapsable tipo hamburguesa en desktop (`sidebarFullyCollapsibleOnDesktop`). Listados con selector de columnas visibles (`toggleable` en todas las columnas).

> Todos los comandos artisan deben correr dentro de Sail: `./vendor/bin/sail artisan ...`

---

## SKUs y precios (datos reales de negocio)

| SKU | Nombre | Costo ex-IVA (hub Bélgica) | PVP c/IVA España |
|-----|--------|---------------------------|-----------------|
| serbal | Serbal Malbec | €4.83 | €7.60 |
| catalpa | Catalpa Malbec | €7.12 | €11.20 |
| atamisque | Atamisque Malbec | €9.72 | €15.30 |
| assemblage | Assemblage | €13.54 | €21.30 |

**Catalpa es el SKU de entrada** — margen ajustado, pero abre la puerta al cliente. Se recupera margen en Atamisque y Assemblage.

Flete de referencia (Mendoza → Barcelona vía Valparaíso): 20' ~€4,243 / 40' ~€4,934 (Hillebrand Gori).

---

## Clientes y segmentación

- `customer_type`: `horeca` (restaurantes, hoteles, bares) o `individual`
- Estrategia actual: transición de distribuidor a **importadora directa**, apuntando a **distribuidores regionales** mientras se retienen clientes HORECA directos
- Los clientes entran desde HubSpot (Companies) y se sincronizan automáticamente
- **`price_list_id`**: cada cliente puede tener lista de precios (`PriceList` / `PriceListItem`); resolución en `PriceResolutionService`
- **`balance`**: saldo en cuenta corriente (`customers.balance`), mantenido por `ledger_entries` vía observers de `Invoice` y `Payment`
- **`credit_limit`**: control de riesgo comercial

---

## Flujo operativo típico

```
Cliente HubSpot → sync → Customer en Laravel
  → Order (OrderItems + descuentos; precios desde lista del cliente)
    → Invoice (desde pedido vía InvoiceService::createFromOrder)
      → Payment (PaymentMethod + detalle polimórfico; marca factura paid)
      → LedgerEntry (automático al emitir/pagar)
```

### Facturación

- Botón **Facturar pedido** en pedido (`InvoiceService::createFromOrder`)
- Si falla la facturación desde pedido (por ejemplo, stock insuficiente), el panel muestra el motivo exacto y evita 500. Para facturar sin descontar inventario, desmarcar **Genera movimiento de stock** en la acción de facturar pedido.
- **Crear factura manual** en `Facturas → Crear`: incluye repetidor de **líneas** (producto obligatorio, cantidad, precio, dto.) que calcula `total_amount`; las líneas se crean en `CreateInvoice::afterCreate` y se recalcula el total. Editar líneas posteriores desde el relation manager de la ficha.
- Numeración correlativa: `{prefix}{año}-{secuencia}` — ej. `HORECA2025-00082` (`InvoiceNumberGenerator`, config en `config/invoices.php`)
- Validación número/fecha al emitir (`InvoiceSequenceValidator`)
- `issued_at` y `ordered_at` default `now()` al crear
- Facturas **no eliminables**; **Cancelar factura** crea nota de crédito (`InvoiceService::cancelInvoice`)
- Stock en factura: checkbox `generates_stock_movement` (default `true`); al pasar a `issued` aplica `StockService::applyStockFromInvoice`
- Pestaña **Pagos** de la factura lista `paymentAllocations` (imputaciones), no `payments`: así aparecen también los cobros multi-factura (`invoice_id` nulo)

### Pagos

- Métodos configurables en **Métodos de pago** (Filament)
- Registro desde factura emitida → **Registrar pago** (admite **pago parcial**)
- Cobro multi-factura desde cuenta corriente → **Registrar cobro** con imputaciones (`payment_allocations`)
- Selector **Facturas a liquidar** (multi-select): al elegir una o más facturas pendientes, completa automáticamente importe del cobro e imputaciones al 100% del saldo de cada una (`PaymentAllocationForm`)
- Transferencia bancaria: `transaction_number` es **opcional** (columna nullable)
- Selector de facturas a imputar (dropdown de pagos) muestra **número · fecha — pendiente** (`InvoiceLabel::withPendingAmount`); en listados y cuenta corriente se muestra solo el número de factura
- En tablas Filament, **número de factura** y **nombre de cliente** son enlaces clicables a la ficha (`TableUi::invoiceLink`, `TableUi::customerLink`)

### Cuenta corriente

- Página `/admin/customers/{id}/statement` (`CustomerStatement`)
- Widget saldo en edición de cliente
- Botón **Importar movimientos** si hay facturas/pagos pero ledger vacío
- Rebuild manual: `./vendor/bin/sail artisan ledger:rebuild`
- Filtro **Ocultar facturas liquidadas**: excluye del listado las facturas cobradas al 100%. También se refleja en la impresión de cuenta corriente (`exclude_settled=1`).

### Stock

- Reporte `/admin/stock-report` (`StockReport` page)
- Sanitizar movimientos alineados solo a facturas: `./vendor/bin/sail artisan stock:sanitize`
- Se permite stock negativo al facturar con movimiento de stock: si no hay unidades suficientes, la factura igual descuenta y el producto puede quedar con saldo `< 0`.
- Recalculo desde movimientos (`StockService::recalculateProductStockFromMovements`) conserva saldos negativos (no los clampa a 0).

### Impresión de facturas

- Formato tipo factura estándar: logo arriba a la derecha, emisor/cliente en dos columnas, vencimiento +21 días, IBAN
- **Líneas en neto (sin IVA):** la columna "Precio" muestra el importe de línea sin impuestos; se mantiene la columna informativa de tipo de IVA (21%)
- **Desglose fiscal** abajo a la derecha (entre tabla y total): `Base imponible` (suma neta) → `IVA (21%)` (importe nominal) → `TOTAL` (con IVA). El total impreso equivale a `invoices.total_amount` (que ya se almacena en bruto, con IVA)
- Cálculo en `InvoicePrintService::buildPrintData()`: `subtotal` = suma de líneas netas, `vat_amount` = subtotal × tasa, `total` = subtotal + IVA
- **PDF por defecto** vía `barryvdh/laravel-dompdf` (`?format=html` para vista previa en navegador)
- Config emisor/IVA/plazo/logo: `config/invoices.php` y variables `INVOICE_ISSUER_*`, `INVOICE_LOGO_PATH`
- Página rango: `/admin/print-invoices` (Filament `PrintInvoices`)
- Cuentas corrientes (listado): `/admin/customer-accounts` (`CustomerAccountsReport`)
- Impresión individual: botón en listado y en vista/edición de factura
- Rutas web:
  - `/admin/invoices/{id}/print`
  - `/admin/invoices/print/range?from=HORECA2025-00001&to=HORECA2025-00099`
- Servicios: `InvoicePrintService`, `InvoicePrintAuthorization`
- Vistas: `resources/views/invoices/pdf.blade.php`, `partials/document.blade.php`
- Solo facturas `issued` o `paid` son imprimibles

---

## Roles y acceso

| Rol | Acceso Filament |
|-----|-----------------|
| `admin` | Todo |
| `sales` | Clientes, pedidos, facturas venta, pagos, listas de precios |
| `warehouse` | Productos, stock, movimientos |
| `accountant` | **Solo** listado de facturas + imprimir (individual y por rango) |

Permisos Spatie: `manage customers`, `manage products`, `manage orders`, `manage invoices`, `manage stock`, **`print invoices`**.

Acceso al panel (`User::canAccessPanel`): roles `admin`, `sales`, `warehouse`, `accountant`.

Restricción por recurso en Filament vía `canViewAny()` en cada Resource + `ErpAuthorization` / `InvoicePrintAuthorization`. Proveedores y facturas de compra: solo `admin`.

### Crear usuario contador

```bash
./vendor/bin/sail artisan db:seed --class=RolesAndPermissionsSeeder
./vendor/bin/sail artisan tinker
```

```php
$user = \App\Models\User::create([...]);
$user->assignRole('accountant');
```

---

## Integración HubSpot

- **Dirección:** HubSpot → Laravel (unidireccional). Laravel será maestro a futuro.
- Sync completo: `./vendor/bin/sail artisan hubspot:sync-companies --full`
- Sync incremental (cada 15 min vía scheduler): `--incremental`
- Health check: `./vendor/bin/sail artisan hubspot:health-check`
- Webhook: `POST /api/webhooks/hubspot` (requiere `HUBSPOT_CLIENT_SECRET` y worker)
- Token válido: `pat-eu1-...` o `pat-na1-...` (no Developer API key `eu1-...`)
- **Sin `queue:work` activo, los jobs se encolan pero no se ejecutan.**
- Match de clientes: primero por `hubspot_company_id`, fallback por `website`.
- Campos fiscales en `customers`: `fiscal_name` ← HubSpot `nombre_fiscal` / `razon_social`; `fiscal_address` ← HubSpot `address2` (Dirección 2); `tax_id` ← HubSpot `nif` / `hs_tax_id`.
- Sync manual por cliente: botón **Sincronizar desde HubSpot** en edición de cliente (`EditCustomer` → `HubSpotCompanySyncService::syncCustomer`). Requiere `hubspot_company_id`.
- **Facturación impresa:** usa `fiscal_name` y `fiscal_address` del cliente (`Customer::billingName()` / `billingAddress()`), con fallback a `name` / `address` si fiscales vacíos.
- Backfill dirección fiscal vacía: `php artisan customers:backfill-fiscal-address` (copia `address` → `fiscal_address` solo si `fiscal_address` está vacío; `--dry-run` para simular).

---

## Archivos clave por área

| Área | Paths |
|------|-------|
| Facturas | `InvoiceResource`, `InvoiceService`, `InvoiceNumberGenerator`, `InvoiceSequenceValidator` |
| Impresión | `InvoicePrintService`, `InvoicePrintController`, `PrintInvoices`, `config/invoices.php` |
| Listas de precios | `PriceListResource`, `PriceResolutionService` |
| Cuenta corriente | `AccountStatementService`, `CustomerStatement`, `ledger:rebuild` |
| Stock | `StockService`, `StockReport`, `stock:sanitize` |
| Panel Filament | `AdminPanelProvider`, `TableUi`, `app/Filament/Widgets/`, `app/Filament/Pages/Dashboard.php` |
| Permisos | `RolesAndPermissionsSeeder`, `ErpAuthorization`, `InvoicePrintAuthorization` |

### Tablas del panel

- **Ordenar:** clic en la flecha del encabezado (no en el texto).
- **Filtrar select:** clic en el título de columna con desplegable (Estado, Tipo, etc.); sincronizado con el panel de filtros superior.
- **Buscar texto:** campo bajo el encabezado de cada columna buscable (sin barra global).
- **Fechas y filtros complejos:** panel colapsable encima de la tabla.
- Helper: `TableUi::headerSelectFilter()` + override de `header-cell.blade.php`.

### Dashboard (`/admin`)

- **DashboardStatsWidget:** cobrado/facturado del mes, clientes con deuda (`withDebt`), sobre límite de crédito. Las 4 métricas agregadas se cachean 300s con clave `dashboard_stats:YYYY-MM` (se refrescan solas o al vaciar caché).
- **PendingInvoicesWidget:** facturas `issued` ordenadas por antigüedad (sin `due_date`).
- **LowStockWidget:** productos con stock ≤ `StockReportService::LOW_STOCK_THRESHOLD` (10).

### Rendimiento (optimizaciones aplicadas jul-2026)

- **Eager loading en tablas Filament** (evita N+1): `getEloquentQuery()` con `->with(...)` en Invoice, Payment, Order, Customer, StockMovement, PurchaseInvoice, Product.
- **Agregados pre-cargados:** `InvoiceResource`/`PaymentResource` usan `withSum(...)`. `Invoice::paidAmount()` y `Payment::allocatedAmount()` leen el atributo pre-cargado (`payment_allocations_sum_amount` / `allocations_sum_amount`) si existe, con fallback a query en vivo (misma exactitud en flujos de escritura).
- **Índices** (migración aditiva `add_performance_indexes`, no altera datos): `invoices(status, issued_at, document_type)`, `payments(paid_at)`, `customers(tax_id, balance)`.
- **Octane:** NO instalado. Cambia el modelo de ejecución (estado entre requests) y requiere configurar workers en Laravel Cloud + validación en staging; pendiente de hacer aparte.
- **Orden por defecto:** Facturas, Pedidos y Pagos listan de más reciente a más antiguo (`defaultSort('id','desc')`).

### Importación legacy

- Comando: `php artisan import:legacy-data {--dry-run}`
- CSVs en la raíz del proyecto (`clientes.csv`, `facturas.csv`, `lineas_factura.csv`; `pagos.csv` opcional)
- Idempotencia vía `customers.legacy_id`, `invoices.legacy_invoice_number`, `invoice_items.legacy_line_id`, `payments.legacy_payment_id`
- Servicio: `App\Services\LegacyDataImporter`

---

## Deuda técnica conocida (no romper sin discutir)

1. `StockMovement.reference_type/id` — polimórfico manual, sin `morphTo` en modelo
2. API autenticada con guard `web` (sesión), no Sanctum/tokens
3. `shouldPreserveManualValue()` en HubSpotCompanySyncService — lógica de protección de campos pendiente
4. Breeze + vistas Blade legacy; rutas redirigen a Filament
5. `FacturasSeeder`: facturas `paid` sin registros en `payments`
6. Tipos de detalle de pago: agregar uno nuevo requiere código (tabla + modelo), no solo CRUD
7. i18n Filament parcial (labels autogenerados en inglés)

Detalle ampliado: ver `PROJECT.md` → **Fallas conocidas** y **Backlog técnico**.

---

## Próximos pasos del proyecto

Ver backlog priorizado en `PROJECT.md`. Resumen:

1. Worker permanente (Supervisor) en producción
2. Backfill pagos para facturas históricas del seeder
3. Pagos parciales y reversión de estado al eliminar pago
4. i18n completo del panel (`lang/es/erp.php`)
5. ERP → HubSpot bidireccional (futuro)
6. Shopify como canal ecommerce (futuro)
7. Despliegue Laravel Cloud + PostgreSQL

---

## Convenciones del proyecto

- Servicios de negocio en `app/Services/` — no poner lógica de negocio en controladores ni modelos
- Recursos Filament en `app/Filament/Resources/` con nombre `{Entidad}Resource`
- Integraciones externas en `app/Integrations/{Proveedor}/`
- Jobs en `app/Jobs/`, siempre con retry y backoff
- Soft deletes en todos los modelos de dominio
- Migraciones con nombre descriptivo (`add_hubspot_fields_to_customers_table`, no `update_customers`)
- Blade: no usar `use` dentro de `@php` (usar FQCN o imports en el componente PHP)

---

## Lo que NO hacer

- No usar `php artisan` directo fuera de Sail (falla resolución `pgsql`)
- No crear lógica de negocio en controladores API
- No romper el flujo Order → Invoice → Payment sin revisar `InvoiceService` (evita duplicados por `order_id`)
- No tocar `HubSpotMapper` sin actualizar el test de mapeo
- No añadir campos a `Customer` sin evaluar si HubSpot los sobreescribiría en el próximo sync
- No eliminar facturas; usar cancelación → nota de crédito
