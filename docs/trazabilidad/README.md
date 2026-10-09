# Dossier de trazabilidad por lotes

Material para acreditar ante la autoridad sanitaria cómo el ERP identifica el lote de cada
producto en todas las fases de la distribución.

## Entregable

**`Trazabilidad-por-lotes-Atlantica-Terranova.pdf`** — 8 páginas, 9 capturas del sistema en
funcionamiento. Es el documento que se presenta.

Se acompaña de dos documentos reales generados por el sistema, citados en el apartado 6:

- `factura-con-lote.pdf` — factura de venta con la columna «Lote» impresa.
- `albaran-con-lote.pdf` — albarán de entrega de la misma expedición.

## Fuentes

| Archivo | Para qué |
|---|---|
| `tutorial-lotes.html` | Fuente del PDF. Editar aquí y regenerar. |
| `capturas/` | Las 9 capturas, numeradas en el orden en que aparecen. |

## Regenerar el PDF

```sh
cd docs/trazabilidad
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
  --headless --disable-gpu --no-pdf-header-footer \
  --print-to-pdf="$(pwd)/Trazabilidad-por-lotes-Atlantica-Terranova.pdf" \
  "file://$(pwd)/tutorial-lotes.html"
```

Chrome respeta el CSS de impresión del documento (A4, saltos de página, flexbox). Abrir el HTML
e imprimir a PDF desde el navegador da el mismo resultado.

## Reconstruir el escenario de ejemplo

Las cifras del documento (240 unidades recibidas, 168 entregadas, 72 en almacén) corresponden a
los datos reales de la base local. Para rehacerlo desde cero:

```sh
./vendor/bin/sail artisan db:seed --class=RolesAndPermissionsSeeder   # permisos del panel
./vendor/bin/sail artisan db:seed --class=TrazabilidadDemoSeeder      # proveedor, lotes, compras y ventas
```

El panel está en **http://localhost:8081/admin**.

> **Solo en local.** El seeder crea proveedor, clientes, pedidos y facturas con numeración real.
> No ejecutarlo contra producción.

## Si cambian los datos

Si se rehace el escenario, las cifras del documento dejan de coincidir con las capturas. Hay que
actualizar, en `tutorial-lotes.html`: la tabla «Caso real» (apartado 2), los pies de las capturas
1, 4, 7 y 9, y la tabla «Recorrido completo del lote» (apartado 9).
