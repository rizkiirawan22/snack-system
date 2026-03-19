/**
 * Export array of objects to CSV and trigger browser download.
 * @param {Array} rows   - Array of objects
 * @param {Array} cols   - Array of { key, label } defining columns and order
 * @param {string} filename
 */
export function exportCsv(rows, cols, filename = 'export.csv') {
  const header = cols.map(c => `"${c.label}"`).join(',')
  const body = rows.map(row =>
    cols.map(c => {
      const val = c.key.split('.').reduce((o, k) => o?.[k], row) ?? ''
      return `"${String(val).replace(/"/g, '""')}"`
    }).join(',')
  ).join('\n')

  const csv  = '\uFEFF' + header + '\n' + body  // BOM for Excel compatibility
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}

/**
 * Print a section of the page using a print-specific popup window.
 * @param {string} html    - Inner HTML to print
 * @param {string} title
 */
export function printHtml(html, title = 'Cetak Laporan') {
  const win = window.open('', '_blank', 'width=900,height=700')
  win.document.write(`
    <html>
    <head>
      <title>${title}</title>
      <style>
        body { font-family: Arial, sans-serif; font-size: 12px; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 6px 10px; text-align: left; }
        th { background: #f0f0f0; font-weight: bold; }
        h2 { margin-bottom: 4px; }
        .subtitle { color: #888; font-size: 11px; margin-bottom: 16px; }
        @media print { button { display: none; } }
      </style>
    </head>
    <body>
      <button onclick="window.print()" style="margin-bottom:12px;padding:6px 16px;cursor:pointer">Cetak</button>
      ${html}
    </body>
    </html>
  `)
  win.document.close()
}
