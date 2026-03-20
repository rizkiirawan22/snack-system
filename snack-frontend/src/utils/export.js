/**
 * Export array of objects to CSV and trigger browser download.
 */
export function exportCsv(rows, cols, filename = 'export.csv') {
  const header = cols.map(c => `"${c.label}"`).join(',')
  const body = rows.map(row =>
    cols.map(c => {
      const val = c.key.split('.').reduce((o, k) => o?.[k], row) ?? ''
      return `"${String(val).replace(/"/g, '""')}"`
    }).join(',')
  ).join('\n')

  const csv  = '\uFEFF' + header + '\n' + body
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}

/**
 * Cetak struk transaksi — membuka popup lalu auto-print.
 * @param {object} sale  - Data transaksi dari API (sudah include items.product, user)
 */
export function printStruk(sale) {
  const rp = (v) => 'Rp ' + Number(v || 0).toLocaleString('id-ID')

  const formatDt = (d) => {
    if (!d) return ''
    const dt = new Date(d)
    return dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }) +
      '  ' + dt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
  }

  const itemsHtml = (sale.items ?? []).map(item => `
    <div class="item-name">${item.product?.name ?? '-'}</div>
    <div class="item-detail">
      <span>${item.quantity} x ${rp(item.price)}</span>
      <span>${rp(item.subtotal)}</span>
    </div>`).join('')

  const discountRow = sale.discount > 0
    ? `<div class="row"><span>Diskon</span><span>-${rp(sale.discount)}</span></div>` : ''

  const voidBanner = sale.voided_at
    ? `<div class="void-banner">*** TRANSAKSI DIBATALKAN ***<br>${sale.void_reason ?? ''}</div>` : ''

  const methodLabel = { cash: 'Tunai', transfer: 'Transfer', qris: 'QRIS' }[sale.payment_method] ?? sale.payment_method

  const html = `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Struk ${sale.invoice_no}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Courier New', Courier, monospace;
      font-size: 12px;
      width: 280px;
      margin: 0 auto;
      padding: 12px 8px;
      color: #000;
    }
    .center  { text-align: center; }
    .brand   { font-size: 18px; font-weight: bold; text-align: center; letter-spacing: 1px; }
    .tagline { font-size: 10px; text-align: center; color: #444; margin-bottom: 6px; }
    .meta    { font-size: 11px; text-align: center; color: #333; line-height: 1.6; }
    .dash    { border-top: 1px dashed #000; margin: 7px 0; }
    .item-name   { font-weight: bold; font-size: 12px; margin-top: 5px; }
    .item-detail { display: flex; justify-content: space-between; font-size: 11px; color: #333; margin-bottom: 2px; }
    .row     { display: flex; justify-content: space-between; font-size: 12px; margin: 3px 0; }
    .row.total { font-size: 15px; font-weight: bold; margin: 5px 0; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 4px 0; }
    .thanks  { text-align: center; font-size: 12px; font-weight: bold; margin-top: 10px; }
    .footer  { text-align: center; font-size: 10px; color: #555; margin-top: 4px; }
    .void-banner { text-align: center; font-weight: bold; color: #000; border: 1px solid #000; padding: 4px; margin: 6px 0; font-size: 11px; }
    .no-print { text-align: center; margin-bottom: 10px; }
    @media print { .no-print { display: none; } body { padding: 4px; } }
  </style>
</head>
<body>
  <div class="no-print">
    <button onclick="window.print()" style="padding:6px 18px;cursor:pointer;font-size:12px">🖨 Cetak</button>
  </div>
  <div class="brand">SnackKilo</div>
  <div class="tagline">Snack Kiloan Berkualitas</div>
  <div class="dash"></div>
  <div class="meta">
    ${sale.invoice_no}<br>
    ${formatDt(sale.created_at ?? sale.date)}<br>
    Kasir: ${sale.user?.name ?? '-'}
  </div>
  ${voidBanner}
  <div class="dash"></div>
  ${itemsHtml}
  <div class="dash"></div>
  <div class="row"><span>Subtotal</span><span>${rp(sale.subtotal)}</span></div>
  ${discountRow}
  <div class="row total"><span>TOTAL</span><span>${rp(sale.total)}</span></div>
  <div class="row"><span>Bayar (${methodLabel})</span><span>${rp(sale.paid)}</span></div>
  <div class="row"><span>Kembalian</span><span>${rp(sale.change)}</span></div>
  <div class="dash"></div>
  <div class="thanks">Terima kasih telah berbelanja!</div>
  <div class="footer">Simpan struk ini sebagai bukti pembelian</div>
</body>
</html>`

  const win = window.open('', '_blank', 'width=340,height=620')
  if (!win) { alert('Popup diblokir browser. Izinkan popup untuk mencetak struk.'); return }
  win.document.write(html)
  win.document.close()
  win.focus()
  setTimeout(() => { win.print() }, 400)
}

/**
 * Export a report as a printable PDF-ready page.
 * @param {object} opts
 * @param {string}   opts.title      - Judul laporan
 * @param {string}   opts.subtitle   - Keterangan (misal: periode tanggal)
 * @param {Array}    opts.summary    - Array { label, value } untuk stat cards
 * @param {Array}    opts.columns    - Array { label, key, align? } untuk tabel
 * @param {Array}    opts.rows       - Data rows
 * @param {Function} opts.cellValue  - Optional fn(key, row) => string untuk format sel
 */
export function exportPdf({ title, subtitle, summary = [], columns, rows, cellValue }) {
  const now = new Date().toLocaleString('id-ID', {
    day: '2-digit', month: 'long', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })

  const summaryHtml = summary.length ? `
    <div class="summary-grid">
      ${summary.map(s => `
        <div class="summary-card">
          <div class="sv">${s.value}</div>
          <div class="sl">${s.label}</div>
        </div>`).join('')}
    </div>` : ''

  const thead = columns.map(c =>
    `<th style="text-align:${c.align ?? 'left'}">${c.label}</th>`
  ).join('')

  const tbody = rows.map((row, i) => `
    <tr class="${i % 2 === 0 ? 'even' : ''}">
      ${columns.map(c => {
        const raw = c.key.split('.').reduce((o, k) => o?.[k], row) ?? ''
        const val = cellValue ? (cellValue(c.key, row) ?? raw) : raw
        return `<td style="text-align:${c.align ?? 'left'}">${val}</td>`
      }).join('')}
    </tr>`).join('')

  const html = `
    <div class="header">
      <div class="shop">Snack Kiloan</div>
      <div class="report-title">${title}</div>
      <div class="period">${subtitle}</div>
    </div>
    ${summaryHtml}
    <table>
      <thead><tr>${thead}</tr></thead>
      <tbody>${tbody}</tbody>
    </table>
    <div class="footer">Dicetak pada ${now}</div>`

  const win = window.open('', '_blank', 'width=960,height=720')
  win.document.write(`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>${title}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; font-size: 12px; color: #222; padding: 24px 32px; }

    .header { margin-bottom: 20px; }
    .shop   { font-size: 18px; font-weight: bold; color: #0d9488; }
    .report-title { font-size: 15px; font-weight: 700; margin: 4px 0 2px; }
    .period { font-size: 11px; color: #666; }

    .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 20px; }
    .summary-card { border: 1px solid #e0e0e0; border-radius: 6px; padding: 10px 14px; }
    .sv { font-size: 15px; font-weight: 700; }
    .sl { font-size: 10px; color: #888; margin-top: 2px; }

    table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 16px; }
    thead tr { background: #0d9488; color: #fff; }
    th, td { padding: 7px 10px; border: 1px solid #ddd; }
    tbody tr.even { background: #f9f9f9; }

    .footer { font-size: 10px; color: #aaa; text-align: right; margin-top: 8px; }

    .no-print { margin-bottom: 16px; }
    @media print {
      .no-print { display: none; }
      body { padding: 12px; }
    }
  </style>
</head>
<body>
  <div class="no-print">
    <button onclick="window.print()" style="padding:8px 20px;background:#0d9488;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px">
      🖨 Cetak / Simpan PDF
    </button>
  </div>
  ${html}
</body>
</html>`)
  win.document.close()
}
