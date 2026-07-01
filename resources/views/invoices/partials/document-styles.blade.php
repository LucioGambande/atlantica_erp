<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
        font-size: 11px;
        color: #111;
        line-height: 1.45;
    }
    .invoice-page {
        width: 100%;
    }
    .header-title {
        font-size: 16px;
        font-weight: 700;
        margin: 0 0 8px;
    }
    .logo {
        max-width: 180px;
        max-height: 56px;
    }
    .meta-table,
    .parties-table,
    .items-table,
    .total-table {
        width: 100%;
        border-collapse: collapse;
    }
    .meta-table td {
        padding: 2px 0;
        vertical-align: top;
    }
    .meta-label {
        width: 38%;
        font-weight: 700;
    }
    .parties-table td {
        width: 50%;
        vertical-align: top;
        padding: 10px 12px 10px 0;
    }
    .party-name {
        font-weight: 700;
        margin-bottom: 6px;
    }
    .items-table {
        margin-top: 14px;
    }
    .items-table th,
    .items-table td {
        border: 1px solid #222;
        padding: 6px 5px;
    }
    .items-table th {
        background: #f5f5f5;
        font-size: 10px;
        font-weight: 700;
    }
    .num {
        text-align: right;
        white-space: nowrap;
    }
    .total-table {
        margin-top: 10px;
        width: 100%;
    }
    .total-table td {
        padding: 4px 5px;
    }
    .total-label {
        text-align: right;
        font-weight: 700;
        padding-right: 8px;
    }
    .total-value {
        width: 16%;
        text-align: right;
        font-weight: 700;
        border: 1px solid #222;
    }
    .footer {
        margin-top: 24px;
    }
</style>
