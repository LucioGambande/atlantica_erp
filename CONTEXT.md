# Atlántica Terranova ERP — Contexto para asistente IA

> Este archivo existe para dar contexto operativo y de negocio al asistente IA (Cursor).
> La documentación técnica completa está en `PROJECT.md`.
> Última actualización: junio 2026.

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
- El campo `credit_limit` y el accesor `balance` (facturas `issued` − pagos) son centrales para el control de riesgo

---

## Flujo operativo típico

```
Cliente HubSpot → sync → Customer en Laravel
  → Order (con OrderItems + descuentos por línea)
    → Invoice (desde Order, via InvoiceService)
      → Payment (PaymentMethod + detalle polimórfico; marca factura paid)
```

Métodos de pago configurables en **Métodos de pago** (Filament). Ej.: transferencia bancaria exige `transaction_number`. Registro desde factura emitida → **Registrar pago**.

Stock se descuenta automáticamente al completar el pedido (`StockService`). Los movimientos quedan en `StockMovement`.

---

## Roles y acceso

| Rol | Acceso |
|-----|--------|
| `admin` | Todo el panel Filament |
| `sales` | Pedidos, clientes, facturas venta |
| `warehouse` | Stock, movimientos, facturas compra |

Permisos Spatie: `manage customers`, `manage products`, `manage orders`, `manage invoices`, `manage stock`.

Hoy Filament solo controla acceso por rol `admin` global. La restricción por recurso con permisos Spatie está pendiente (deuda técnica).

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

---

## Deuda técnica conocida (no romper sin discutir)

1. `StockMovement.reference_type/id` — polimórfico manual, sin `morphTo` en modelo
2. Permisos Spatie no aplicados por recurso en Filament (solo rol admin global)
3. API autenticada con guard `web` (sesión), no Sanctum/tokens
4. `shouldPreserveManualValue()` en HubSpotCompanySyncService — lógica de protección de campos pendiente
5. Breeze + vistas Blade legacy; rutas redirigen a Filament
6. `FacturasSeeder`: facturas `paid` sin registros en `payments`
7. Tipos de detalle de pago: agregar uno nuevo requiere código (tabla + modelo), no solo CRUD
8. i18n Filament parcial (labels autogenerados en inglés)

Detalle ampliado de fallas y backlog: ver `PROJECT.md` → secciones **Fallas conocidas** y **Backlog técnico**.

---

## Próximos pasos del proyecto

Ver backlog priorizado en `PROJECT.md`. Resumen:

1. Worker permanente (Supervisor) en producción
2. Backfill pagos para facturas históricas del seeder
3. Permisos Spatie por recurso + roles `superadmin`/`operator`/`viewer`
4. Pagos parciales y reversión de estado al eliminar pago
5. i18n completo del panel (`lang/es/erp.php`)
6. ERP → HubSpot bidireccional (futuro)
7. Shopify como canal ecommerce (futuro)
8. Despliegue Laravel Cloud + PostgreSQL

---

## Convenciones del proyecto

- Servicios de negocio en `app/Services/` — no poner lógica de negocio en controladores ni modelos
- Recursos Filament en `app/Filament/Resources/` con nombre `{Entidad}Resource`
- Integraciones externas en `app/Integrations/{Proveedor}/`
- Jobs en `app/Jobs/`, siempre con retry y backoff
- Soft deletes en todos los modelos de dominio
- Migraciones con nombre descriptivo (`add_hubspot_fields_to_customers_table`, no `update_customers`)

---

## Lo que NO hacer

- No usar `php artisan` directo fuera de Sail (falla resolución `pgsql`)
- No crear lógica de negocio en controladores API
- No romper el flujo Order → Invoice → Payment sin revisar `InvoiceService` (evita duplicados por `order_id`)
- No tocar `HubSpotMapper` sin actualizar el test de mapeo
- No añadir campos a `Customer` sin evaluar si HubSpot los sobreescribiría en el próximo sync
