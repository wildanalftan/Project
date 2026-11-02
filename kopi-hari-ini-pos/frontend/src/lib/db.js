import Dexie from 'dexie'

const db = new Dexie('KopiHariIniPOS')

db.version(1).stores({
  // Pending transactions waiting to sync
  pendingTx: '++id, client_uuid, created_at',
  // Cached product list for offline kasir
  products: 'id, sku, name, is_active',
  // Cached settings
  settings: 'key',
  // Offline log (audit)
  offlineLog: '++id, type, created_at',
})

// ─── Products ────────────────────────────────────────────────────────────────

export async function cacheProducts(products) {
  await db.products.clear()
  await db.products.bulkPut(products)
}

export async function getCachedProducts() {
  return db.products.where('is_active').equals(1).toArray()
}

// ─── Pending Transactions ────────────────────────────────────────────────────

export async function queueOfflineTransaction(txData) {
  const id = await db.pendingTx.add({
    ...txData,
    created_at: new Date().toISOString(),
  })
  return id
}

export async function getPendingTransactions() {
  return db.pendingTx.orderBy('created_at').toArray()
}

export async function removePendingTransaction(id) {
  return db.pendingTx.delete(id)
}

export async function getPendingCount() {
  return db.pendingTx.count()
}

// ─── Settings ─────────────────────────────────────────────────────────────────

export async function cacheSettings(settings) {
  const entries = Object.entries(settings).map(([key, value]) => ({ key, value }))
  await db.settings.bulkPut(entries)
}

export async function getCachedSetting(key) {
  const row = await db.settings.get(key)
  return row?.value ?? null
}

export default db
