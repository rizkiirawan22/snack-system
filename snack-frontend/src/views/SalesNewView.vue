<template>
  <div class="pos-wrapper" :class="{ 'cart-open': cartExpanded }">
    <!-- Kiri: produk -->
    <div class="pos-products">
      <div class="pos-search">
        <div class="search-row">
          <div class="search-input-wrap" style="flex:1">
            <span class="search-icon">⌕</span>
            <input
              ref="searchInput"
              v-model="search"
              class="form-control"
              placeholder="Cari produk atau scan barcode..."
              @input="filterProducts"
              @keydown="handleBarcodeKeydown"
            />
          </div>
          <button
            class="barcode-toggle"
            :class="{ active: barcodeMode }"
            title="Mode barcode scanner"
            @click="barcodeMode = !barcodeMode"
          >
            ▦ Barcode
          </button>
        </div>
        <div v-if="barcodeMode" class="barcode-hint">
          Mode barcode aktif — arahkan scanner ke produk. Pencarian otomatis by kode produk.
        </div>
        <div class="cat-tabs">
          <button class="cat-tab" :class="{ active: activeCategory === '' }" @click="activeCategory = ''; filterProducts()">Semua</button>
          <button v-for="c in categories" :key="c.id" class="cat-tab" :class="{ active: activeCategory === c.id }" @click="activeCategory = c.id; filterProducts()">
            {{ c.name }}
          </button>
        </div>
      </div>

      <div class="product-grid">
        <div
          v-for="p in filteredProducts"
          :key="p.id"
          class="product-card"
          :class="{ 'out-of-stock': !p.stock || p.stock.quantity === 0 }"
          @click="addToCart(p)"
        >
          <div class="product-img-wrap">
            <img v-if="p.image_url" :src="p.image_url" class="product-img" :alt="p.name" />
            <div v-else class="product-img product-no-img">No Image</div>
          </div>
          <div class="product-cat">{{ p.category?.name }}</div>
          <div class="product-name">{{ p.name }}</div>
          <div class="product-weight">{{ p.weight }}gr</div>
          <div class="product-footer">
            <span class="product-price">{{ formatRp(p.selling_price) }}</span>
            <span class="product-stock" :class="stockClass(p)">{{ p.stock?.quantity ?? 0 }} pack</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Kanan: keranjang -->
    <div class="pos-cart">
      <!-- Mini bar — hanya tampil di mobile saat cart collapsed -->
      <div class="cart-mini-bar" @click="cartExpanded = !cartExpanded">
        <div class="mini-left">
          <span class="mini-count">{{ totalItems }} item</span>
          <span class="mini-total">{{ formatRp(total) }}</span>
        </div>
        <span class="mini-toggle">{{ cartExpanded ? '↓ Tutup' : '↑ Keranjang' }}</span>
      </div>

      <!-- Cart body: header + items + summary -->
      <div class="cart-body">
      <div class="cart-header">
        <h3 class="cart-title">Transaksi Baru</h3>
        <button v-if="cart.length" class="btn btn-ghost btn-sm" @click="cart = []">Kosongkan</button>
      </div>

      <div class="cart-items">
        <div v-if="!cart.length" class="cart-empty">Belum ada produk dipilih</div>
        <div v-for="(item, i) in cart" :key="i" class="cart-item">
          <div class="cart-item-info">
            <div class="cart-item-name">{{ item.name }}</div>
            <div class="cart-item-price">{{ formatRp(item.price) }} / pack</div>
          </div>
          <div class="cart-item-controls">
            <button class="qty-btn" @click="decreaseQty(i)">−</button>
            <span class="qty-val">{{ item.quantity }}</span>
            <button class="qty-btn" @click="increaseQty(i, item)">+</button>
            <button class="qty-remove" @click="cart.splice(i, 1)">✕</button>
          </div>
          <div class="cart-item-sub">{{ formatRp(item.quantity * item.price) }}</div>
        </div>
      </div>

      <!-- Summary -->
      <div class="cart-summary">
        <div class="summary-row"><span>Subtotal</span><span>{{ formatRp(subtotal) }}</span></div>
        <div class="summary-row discount-row">
          <span>Diskon</span>
          <div class="discount-input-wrap">
            <span>Rp</span>
            <input v-model.number="discount" type="number" class="discount-input" min="0" />
          </div>
        </div>
        <div class="summary-row total-row"><span>Total</span><strong>{{ formatRp(total) }}</strong></div>

        <div class="payment-section">
          <div class="form-group">
            <label class="form-label">Metode Pembayaran</label>
            <div class="payment-methods">
              <button v-for="m in methods" :key="m.value" class="payment-method" :class="{ active: paymentMethod === m.value }" @click="paymentMethod = m.value">
                {{ m.label }}
              </button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Bayar</label>
            <input v-model.number="paid" type="number" class="form-control" :min="total" />
          </div>
          <div v-if="change >= 0" class="change-row">
            Kembalian: <strong>{{ formatRp(change) }}</strong>
          </div>
          <div v-else class="change-row error">Pembayaran kurang {{ formatRp(Math.abs(change)) }}</div>
        </div>

        <button class="btn btn-primary checkout-btn" :disabled="!cart.length || change < 0 || saving" @click="checkout">
          {{ saving ? 'Memproses...' : `Bayar ${formatRp(total)}` }}
        </button>

        <RouterLink to="/sales" class="btn btn-ghost" style="width:100%;justify-content:center;margin-top:8px">
          Kembali ke Daftar
        </RouterLink>
      </div>
      </div><!-- /cart-body -->
    </div>
  </div>

  <!-- Struk modal -->
  <AppModal v-model="showStruk" title="Transaksi Berhasil" width="400px">
    <div v-if="lastSale" id="print-area" class="struk">
      <div class="struk-header">
        <div class="struk-brand">SnackKilo</div>
        <div class="struk-tagline">Snack Kiloan Berkualitas</div>
        <div class="struk-divider" />
        <div class="struk-inv">{{ lastSale.invoice_no }}</div>
        <div class="struk-date">{{ formatDateTime(lastSale.date) }}</div>
        <div class="struk-cashier">Kasir: {{ lastSale.user?.name }}</div>
      </div>
      <div class="struk-divider" />
      <div v-for="item in lastSale.items" :key="item.id" class="struk-item">
        <div class="struk-item-name">{{ item.product?.name }}</div>
        <div class="struk-item-detail">
          <span>{{ item.quantity }} x {{ formatRp(item.price) }}</span>
          <span>{{ formatRp(item.subtotal) }}</span>
        </div>
      </div>
      <div class="struk-divider" />
      <div class="struk-row"><span>Subtotal</span><span>{{ formatRp(lastSale.subtotal) }}</span></div>
      <div class="struk-row"><span>Diskon</span><span>{{ formatRp(lastSale.discount) }}</span></div>
      <div class="struk-row total"><span>Total</span><strong>{{ formatRp(lastSale.total) }}</strong></div>
      <div class="struk-row"><span>Bayar ({{ lastSale.payment_method }})</span><span>{{ formatRp(lastSale.paid) }}</span></div>
      <div class="struk-row"><span>Kembalian</span><span>{{ formatRp(lastSale.change) }}</span></div>
      <div class="struk-divider" />
      <div class="struk-thanks">Terima kasih telah berbelanja!</div>
    </div>
    <template #footer>
      <button class="btn btn-ghost" @click="printReceipt">Cetak Struk</button>
      <button class="btn btn-primary" @click="newTransaction">Transaksi Baru</button>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToastStore } from '@/stores/toast'
import { printStruk } from '@/utils/export'
import api from '@/api/axios'
import AppModal from '@/components/AppModal.vue'

const toast = useToastStore()

const allProducts      = ref([])
const filteredProducts = ref([])
const categories       = ref([])
const cart             = ref([])
const search           = ref('')
const activeCategory   = ref('')
const discount         = ref(0)
const paymentMethod    = ref('cash')
const paid             = ref(0)
const saving           = ref(false)
const showStruk        = ref(false)
const lastSale         = ref(null)
const barcodeMode      = ref(false)
const searchInput      = ref(null)

// Barcode scanner: deteksi input cepat (scanner ketik karakter dalam <50ms)
let barcodeBuffer  = ''
let lastKeyTime    = 0

const methods = [{ value: 'cash', label: 'Tunai' }, { value: 'transfer', label: 'Transfer' }, { value: 'qris', label: 'QRIS' }]

const cartExpanded = ref(false)
const subtotal     = computed(() => cart.value.reduce((s, i) => s + i.quantity * i.price, 0))
const total        = computed(() => Math.max(0, subtotal.value - discount.value))
const change       = computed(() => paid.value - total.value)
const totalItems   = computed(() => cart.value.reduce((s, i) => s + i.quantity, 0))

const formatRp       = (v) => 'Rp ' + Number(v || 0).toLocaleString('id-ID')
const formatDate     = (d) => d ? new Date(d).toLocaleDateString('id-ID') : ''
const formatDateTime = (d) => {
  if (!d) return ''
  const dt = new Date(d)
  return dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }) +
    ' ' + dt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

function stockClass(p) {
  const qty = p.stock?.quantity ?? 0
  if (qty === 0) return 'stock-out'
  if (qty <= p.min_stock) return 'stock-low'
  return 'stock-ok'
}

function filterProducts() {
  filteredProducts.value = allProducts.value.filter((p) => {
    const q = search.value.toLowerCase()
    const matchSearch = !q || p.name.toLowerCase().includes(q) || (p.code || '').toLowerCase().includes(q)
    const matchCat    = !activeCategory.value || p.category_id === activeCategory.value
    return matchSearch && matchCat && p.is_active
  })
}

// Deteksi barcode scanner: scanner mengirim karakter sangat cepat + Enter
function handleBarcodeKeydown(e) {
  if (!barcodeMode.value) return
  const now = Date.now()
  if (e.key === 'Enter') {
    const code = barcodeBuffer.trim()
    barcodeBuffer = ''
    if (code) addByCode(code)
    e.preventDefault()
    return
  }
  if (now - lastKeyTime < 80) {
    barcodeBuffer += e.key
  } else {
    barcodeBuffer = e.key
  }
  lastKeyTime = now
}

function addByCode(code) {
  const product = allProducts.value.find(p => p.code === code && p.is_active)
  if (!product) {
    toast.error(`Produk dengan kode "${code}" tidak ditemukan.`)
    search.value = ''
    return
  }
  search.value = ''
  filterProducts()
  addToCart(product)
}

function addToCart(p) {
  if (!p.stock || p.stock.quantity === 0) return
  const existing = cart.value.find((i) => i.product_id === p.id)
  if (existing) {
    if (existing.quantity >= p.stock.quantity) return
    existing.quantity++
  } else {
    cart.value.push({ product_id: p.id, name: p.name, price: parseFloat(p.selling_price), quantity: 1, maxQty: p.stock.quantity })
  }
  paid.value = total.value
}

function increaseQty(i, item) {
  if (cart.value[i].quantity < item.maxQty) cart.value[i].quantity++
  paid.value = total.value
}

function decreaseQty(i) {
  if (cart.value[i].quantity > 1) cart.value[i].quantity--
  else cart.value.splice(i, 1)
  paid.value = total.value
}

async function checkout() {
  saving.value = true
  try {
    const { data } = await api.post('/sales', {
      date:           new Date().toISOString().slice(0, 10),
      discount:       discount.value,
      paid:           paid.value,
      payment_method: paymentMethod.value,
      items:          cart.value.map((i) => ({ product_id: i.product_id, quantity: i.quantity, price: i.price })),
    })
    lastSale.value  = data
    showStruk.value = true
  } catch(e) {
    toast.error(e.response?.data?.message || 'Transaksi gagal.')
  } finally {
    saving.value = false
  }
}

function printReceipt() {
  if (lastSale.value) printStruk(lastSale.value)
}

function newTransaction() {
  cart.value      = []
  discount.value  = 0
  paid.value      = 0
  showStruk.value = false
  fetchProducts()
}

async function fetchProducts() {
  const { data } = await api.get('/products', { params: { is_active: 1, per_page: 200 } })
  allProducts.value = data.data
  filterProducts()
}

async function fetchCategories() {
  const { data } = await api.get('/categories', { params: { per_page: 100 } })
  categories.value = data.data ?? data
}

onMounted(() => { fetchProducts(); fetchCategories() })
</script>

<style scoped>
.pos-wrapper { display: grid; grid-template-columns: 1fr 360px; gap: 0; min-height: calc(100vh - 64px); margin: -32px; }

/* Products panel */
.pos-products { padding: 24px; overflow-y: auto; background: var(--bg-page); }
.pos-search   { margin-bottom: 20px; }
.search-row   { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; }

.barcode-toggle {
  padding: 8px 12px;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  background: #fff;
  font-size: 12px;
  cursor: pointer;
  white-space: nowrap;
  color: #555;
  transition: all 0.15s;
}
.barcode-toggle.active { background: #1a1a1a; color: #0d9488; border-color: #1a1a1a; }

.barcode-hint {
  font-size: 11px;
  color: #888;
  background: #fffbe6;
  border: 1px solid #ffe58f;
  border-radius: 6px;
  padding: 6px 10px;
  margin-bottom: 10px;
}

.cat-tabs     { display: flex; gap: 8px; margin-top: 4px; flex-wrap: wrap; }
.cat-tab      { padding: 6px 14px; border-radius: 20px; border: 1px solid var(--border-input); background: var(--bg-surface); font-size: 13px; cursor: pointer; color: var(--text-2); transition: all 0.15s; }
.cat-tab:hover { border-color: var(--brand); }
.cat-tab.active { background: var(--text-1); color: var(--brand); border-color: var(--text-1); font-weight: 600; }

.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }

.product-card {
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 14px;
  cursor: pointer;
  transition: all 0.15s;
}

.product-card:hover:not(.out-of-stock) { border-color: var(--brand); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
.product-card.out-of-stock { opacity: 0.5; cursor: not-allowed; }

.product-img-wrap { margin-bottom: 8px; }
.product-img { width: 100%; height: 90px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border); display: block; }
.product-no-img {
  display: flex; align-items: center; justify-content: center;
  background: var(--bg-surface-3); color: var(--text-4);
  font-size: 10px; font-weight: 600; letter-spacing: 0.03em;
}
.product-cat    { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-4); margin-bottom: 4px; }
.product-name   { font-size: 13px; font-weight: 600; color: var(--text-1); margin-bottom: 4px; line-height: 1.3; }
.product-weight { font-size: 11px; color: var(--text-3); margin-bottom: 10px; }
.product-footer { display: flex; justify-content: space-between; align-items: center; }
.product-price  { font-size: 13px; font-weight: 700; color: var(--text-1); }
.product-stock  { font-size: 10px; padding: 2px 6px; border-radius: 10px; font-weight: 600; }

.stock-ok  { background: #d1fae5; color: #065f46; }
.stock-low { background: #fff3cc; color: #886600; }
.stock-out { background: #ffe0e0; color: #aa2200; }

/* Cart panel */
.pos-cart {
  background: var(--bg-surface);
  border-left: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  height: calc(100vh);
  position: sticky;
  top: 0;
  transition: background var(--transition), border-color var(--transition);
}

.cart-header { padding: 20px 20px 0; display: flex; justify-content: space-between; align-items: center; }
.cart-title  { font-size: 16px; font-weight: 700; color: var(--text-1); }

.cart-items  { flex: 1; overflow-y: auto; padding: 12px 20px; }
.cart-empty  { text-align: center; color: var(--text-4); font-size: 13px; padding: 40px 0; }

.cart-item { display: flex; align-items: center; gap: 8px; padding: 10px 0; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
.cart-item-info  { flex: 1; min-width: 100px; }
.cart-item-name  { font-size: 13px; font-weight: 500; color: var(--text-1); }
.cart-item-price { font-size: 11px; color: var(--text-4); }
.cart-item-controls { display: flex; align-items: center; gap: 4px; }
.qty-btn    { width: 24px; height: 24px; border: 1px solid var(--border-input); background: var(--bg-surface); border-radius: 6px; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; color: var(--text-1); }
.qty-btn:hover { background: var(--bg-surface-2); }
.qty-val    { font-size: 13px; font-weight: 600; min-width: 24px; text-align: center; color: var(--text-1); }
.qty-remove { width: 20px; height: 20px; border: none; background: none; color: var(--text-4); cursor: pointer; font-size: 11px; margin-left: 2px; }
.qty-remove:hover { color: #ff4444; }
.cart-item-sub { font-size: 13px; font-weight: 600; text-align: right; color: var(--text-1); }

/* Summary */
.cart-summary { padding: 16px 20px 20px; border-top: 1px solid var(--border); }
.summary-row  { display: flex; justify-content: space-between; font-size: 14px; color: var(--text-2); margin-bottom: 8px; }
.total-row    { font-size: 16px; font-weight: 700; color: var(--text-1); border-top: 1px dashed var(--border-input); padding-top: 8px; margin-top: 4px; }

.discount-row { align-items: center; }
.discount-input-wrap { display: flex; align-items: center; gap: 4px; font-size: 13px; }
.discount-input { width: 90px; padding: 4px 8px; border: 1px solid var(--border-input); border-radius: 6px; font-size: 13px; background: var(--bg-surface); color: var(--text-1); }

.payment-section { margin: 12px 0; }
.payment-methods { display: flex; gap: 6px; margin-top: 6px; }
.payment-method  { flex: 1; padding: 7px 0; border: 1px solid var(--border-input); border-radius: 8px; background: var(--bg-surface); cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.15s; color: var(--text-2); }
.payment-method.active { background: var(--text-1); color: var(--brand); border-color: var(--text-1); }

.change-row { font-size: 14px; color: var(--text-2); text-align: center; margin: 8px 0; }
.change-row.error { color: #ff4444; }
.change-row strong { color: #1a1a1a; font-weight: 700; }

.checkout-btn { width: 100%; justify-content: center; padding: 13px; font-size: 15px; margin-top: 8px; }

/* Struk */
.struk { font-size: 13px; }
.struk-header { text-align: center; margin-bottom: 8px; }
.struk-brand  { font-size: 18px; font-weight: 700; margin-bottom: 2px; }
.struk-tagline { font-size: 11px; color: #aaa; margin-bottom: 8px; }
.struk-inv    { font-family: monospace; font-size: 12px; color: #888; }
.struk-date, .struk-cashier { color: #aaa; font-size: 12px; }
.struk-item   { margin: 6px 0; }
.struk-item-name { font-weight: 600; font-size: 13px; }
.struk-item-detail { display: flex; justify-content: space-between; font-size: 12px; color: #666; }
.struk-divider { border-top: 1px dashed #e0e0e0; margin: 10px 0; }
.struk-row    { display: flex; justify-content: space-between; padding: 4px 0; color: #555; }
.struk-row.total { font-size: 15px; color: #1a1a1a; font-weight: 700; border-top: 1px solid #e0e0e0; padding-top: 8px; margin-top: 4px; }
.struk-thanks { text-align: center; margin-top: 16px; font-size: 14px; font-weight: 600; }

/* Mini bar — hanya di mobile */
.cart-mini-bar { display: none; }

/* Responsive ≤900px */
@media (max-width: 900px) {
  .pos-wrapper { grid-template-columns: 1fr; margin: -16px; }

  .pos-cart {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    height: auto;
    border-left: none;
    border-top: 2px solid var(--border);
    z-index: 100;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.12);
    transition: max-height 0.3s ease;
    /* Default collapsed: hanya tampil mini bar */
    max-height: 56px;
    overflow: hidden;
  }

  /* Saat cart expanded */
  .pos-wrapper.cart-open .pos-cart {
    max-height: 72vh;
    overflow-y: auto;
  }

  /* Cart body: tersembunyi saat collapsed */
  .cart-body { display: none; }
  .pos-wrapper.cart-open .cart-body { display: block; }

  /* Mini bar tampil di mobile */
  .cart-mini-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    height: 56px;
    cursor: pointer;
    background: var(--bg-surface);
    flex-shrink: 0;
    user-select: none;
  }
  .cart-mini-bar:active { background: var(--bg-surface-2); }

  .mini-left   { display: flex; align-items: center; gap: 12px; }
  .mini-count  { font-size: 13px; color: var(--text-3); }
  .mini-total  { font-size: 16px; font-weight: 700; color: var(--text-1); }
  .mini-toggle {
    font-size: 13px; font-weight: 600;
    color: var(--brand);
    background: color-mix(in srgb, var(--brand) 12%, transparent);
    padding: 6px 12px; border-radius: 20px;
  }

  /* Products: padding bawah cukup untuk mini bar saja */
  .pos-products { padding-bottom: 72px; }
  /* Saat cart expanded: kasih ruang lebih */
  .pos-wrapper.cart-open .pos-products { padding-bottom: 75vh; }

  .product-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
}

/* Responsive ≤480px */
@media (max-width: 480px) {
  .pos-products { padding: 12px; padding-bottom: 72px; }
  .pos-wrapper.cart-open .pos-products { padding-bottom: 75vh; }
  .product-grid { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px; }
  .product-img  { height: 70px; }
  .product-name { font-size: 12px; }
}
</style>
