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
    .totals-table {
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
    .totals-table {
        margin-top: 12px;
    }
    .totals-spacer {
        width: 58%;
    }
    .totals-box {
        width: 42%;
        vertical-align: top;
    }
    .totals-box table td {
        border: 1px solid #222;
    }
    .totals-label {
        text-align: left;
        padding: 5px 8px;
        font-weight: 700;
        background: #f5f5f5;
    }
    .totals-amount {
        text-align: right;
        padding: 5px 8px;
        white-space: nowrap;
    }
    .totals-grand .totals-label,
    .totals-grand .totals-amount {
        font-size: 12px;
        font-weight: 700;
        background: #eaeaea;
    }
    .footer {
        margin-top: 24px;
    }
</style>
