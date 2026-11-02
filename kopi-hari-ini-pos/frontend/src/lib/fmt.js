/**
 * Format angka menjadi Rupiah: Rp 142.500
 * @param {number} amount
 * @returns {string}
 */
export function fmt(amount) {
  if (amount === null || amount === undefined || isNaN(amount)) return '—'
  return 'Rp\u00A0' + Math.round(amount).toLocaleString('id-ID')
}

/**
 * Format selisih: +Rp 1.000 / -Rp 500
 */
export function fmtDiff(amount) {
  if (amount === null || amount === undefined || isNaN(amount)) return '—'
  const sign = amount >= 0 ? '+' : ''
  return sign + fmt(amount)
}

/**
 * Persentase: "42,5%"
 */
export function pct(value, decimals = 1) {
  if (value === null || value === undefined || isNaN(value)) return '—'
  return value.toFixed(decimals).replace('.', ',') + '%'
}

/**
 * Format tanggal ke "2 Nov 2026"
 */
export function fmtDate(dateStr) {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

/**
 * Format tanggal + waktu: "2 Nov 2026, 14:30"
 */
export function fmtDateTime(dateStr) {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/**
 * Format kuantitas: "10 pcs", "2,500 kg"
 */
export function fmtQty(qty, unit) {
  if (qty === null || qty === undefined) return '—'
  const n = parseFloat(qty)
  const formatted = Number.isInteger(n) ? n : n.toFixed(3).replace(/\.?0+$/, '')
  return formatted + (unit ? `\u00A0${unit}` : '')
}

/**
 * Badge stok: status text
 */
export function stockStatus(product) {
  const qty = parseFloat(product.stock_qty ?? 0)
  const threshold = parseFloat(product.low_stock_threshold ?? 10)
  if (qty <= 0) return { label: 'Habis', cls: 'badge-habis' }
  if (qty < threshold) return { label: 'Menipis', cls: 'badge-menipis' }
  return { label: 'Aman', cls: 'badge-aman' }
}

/**
 * Margin persen dari HPP dan harga jual
 */
export function calcMargin(sellingPrice, cost) {
  if (!cost || cost <= 0 || !sellingPrice) return null
  return ((sellingPrice - cost) / sellingPrice) * 100
}

/**
 * Singkat angka besar: 1.200.000 -> "1,2 Jt"
 */
export function fmtShort(amount) {
  if (!amount && amount !== 0) return '—'
  if (Math.abs(amount) >= 1_000_000_000) return (amount / 1_000_000_000).toFixed(1).replace('.', ',') + '\u00A0M'
  if (Math.abs(amount) >= 1_000_000)     return (amount / 1_000_000).toFixed(1).replace('.', ',') + '\u00A0Jt'
  if (Math.abs(amount) >= 1_000)         return (amount / 1_000).toFixed(0) + '\u00A0Rb'
  return fmt(amount)
}
