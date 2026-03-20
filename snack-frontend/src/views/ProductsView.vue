<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Produk</h1>
        <p class="page-subtitle">Kelola daftar snack kiloan</p>
      </div>
      <button v-if="auth.isAdmin" class="btn btn-primary" @click="openCreate">+ Tambah Produk</button>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
      <div class="search-input-wrap">
        <span class="search-icon">⌕</span>
        <input v-model="filters.search" class="form-control" placeholder="Cari nama / kode..." @input="fetchData" />
      </div>
      <select v-model="filters.category_id" class="form-control" style="width:160px" @change="fetchData">
        <option value="">Semua Kategori</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <select v-model="filters.is_active" class="form-control" style="width:140px" @change="fetchData">
        <option value="">Semua Status</option>
        <option value="1">Aktif</option>
        <option value="0">Nonaktif</option>
      </select>
    </div>

    <AppTable :columns="columns" :data="products" :loading="loading">
      <template #default="{ row }">
        <td>
          <div class="prod-thumb-wrap">
            <img v-if="row.image_url" :src="row.image_url" class="prod-thumb" :alt="row.name" />
            <div v-else class="prod-thumb prod-no-img">No Image</div>
            <div>
              <div class="prod-name">{{ row.name }}</div>
              <div class="prod-code">{{ row.code }}</div>
            </div>
          </div>
        </td>
        <td><span class="badge badge-blue">{{ row.category?.name }}</span></td>
        <td>{{ row.weight }}gr</td>
        <td>{{ formatRp(row.selling_price) }}</td>
        <td>
          <span class="badge" :class="stockBadge(row)">
            {{ row.stock?.quantity ?? 0 }} pack
          </span>
        </td>
        <td>
          <span class="badge" :class="row.is_active ? 'badge-green' : 'badge-gray'">
            {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
          </span>
        </td>
        <td>
          <div class="action-btns">
            <button class="btn btn-ghost btn-sm" @click="openMutations(row)" title="Riwayat Mutasi Stok">Riwayat</button>
            <template v-if="auth.isAdmin">
              <button class="btn btn-ghost btn-sm" @click="openEdit(row)">Edit</button>
              <button class="btn btn-danger btn-sm" @click="openDelete(row)">Hapus</button>
            </template>
          </div>
        </td>
      </template>
    </AppTable>

    <AppPagination :meta="meta" @change="fetchData" />

    <!-- Modal form -->
    <AppModal v-model="showModal" :title="editId ? 'Edit Produk' : 'Tambah Produk'" width="580px">
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Kode <span class="required">*</span></label>
          <input v-model="form.code" class="form-control" placeholder="MNS-001" />
        </div>
        <div class="form-group">
          <label class="form-label">Kategori <span class="required">*</span></label>
          <select v-model="form.category_id" class="form-control">
            <option value="">-- Pilih Kategori --</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Nama Produk <span class="required">*</span></label>
        <input v-model="form.name" class="form-control" placeholder="Coklat Kiloan 250gr" />
      </div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Berat (gram) <span class="required">*</span></label>
          <input v-model.number="form.weight" type="number" class="form-control" placeholder="250" />
        </div>
        <div class="form-group">
          <label class="form-label">Stok Minimum</label>
          <input v-model.number="form.min_stock" type="number" class="form-control" placeholder="10" />
        </div>
      </div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Harga Beli</label>
          <input v-model.number="form.purchase_price" type="number" class="form-control" placeholder="0" />
        </div>
        <div class="form-group">
          <label class="form-label">Harga Jual <span class="required">*</span></label>
          <input v-model.number="form.selling_price" type="number" class="form-control" placeholder="0" />
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Deskripsi</label>
        <textarea v-model="form.description" class="form-control" rows="2" />
      </div>
      <div class="form-group">
        <label class="form-label">Gambar Produk</label>
        <div class="img-upload-area" @click="$refs.imgInput.click()" @dragover.prevent @drop.prevent="onDrop">
          <img v-if="imagePreview" :src="imagePreview" class="img-preview" alt="preview" />
          <div v-else-if="form.existingImage" class="img-existing">
            <img :src="form.existingImage" class="img-preview" alt="existing" />
          </div>
          <div v-else class="img-placeholder">
            <span class="img-icon">&#128247;</span>
            <span>Klik atau seret gambar di sini</span>
            <span class="img-hint">JPG, PNG, WebP — maks 2MB</span>
          </div>
          <input ref="imgInput" type="file" accept="image/*" style="display:none" @change="onImagePick" />
        </div>
        <button v-if="imagePreview || form.existingImage" class="btn btn-ghost btn-sm" style="margin-top:6px" @click.stop="clearImage">Hapus Gambar</button>
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:10px">
        <input v-model="form.is_active" type="checkbox" id="is_active" />
        <label for="is_active" class="form-label" style="margin:0">Produk Aktif</label>
      </div>

      <template #footer>
        <button class="btn btn-ghost" @click="showModal = false">Batal</button>
        <button class="btn btn-primary" :disabled="saving" @click="saveProduct">
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </button>
      </template>
    </AppModal>

    <!-- Confirm delete -->
    <ConfirmDialog
      v-model="showConfirm"
      :message="`Hapus produk '${deleteTarget?.name}'?`"
      :loading="deleting"
      @confirm="deleteProduct"
    />

    <!-- Mutations Modal -->
    <AppModal v-model="showMutations" :title="`Riwayat Stok — ${mutationProduct?.name ?? ''}`" width="640px">
      <div class="mutations-summary" v-if="mutationProduct">
        <span>Stok sekarang: <strong>{{ mutationProduct.stock?.quantity ?? 0 }} pack</strong></span>
      </div>
      <div v-if="loadingMutations" style="text-align:center;padding:40px;color:#aaa">Memuat...</div>
      <template v-else>
        <div v-if="!mutations.length" class="empty-mutations">Belum ada riwayat mutasi stok.</div>
        <div v-else>
          <!-- Desktop table -->
          <div class="mut-table">
            <div class="mut-head">
              <span>Tanggal</span>
              <span>Tipe</span>
              <span>Sebelum</span>
              <span>Perubahan</span>
              <span>Sesudah</span>
              <span>Oleh</span>
              <span>Keterangan</span>
            </div>
            <div v-for="m in mutations" :key="m.id" class="mut-row">
              <span class="mut-date">{{ formatDateTime(m.created_at) }}</span>
              <span><span class="badge" :class="typeBadge(m.type)">{{ typeLabel(m.type) }}</span></span>
              <span>{{ m.quantity_before }}</span>
              <span :class="m.quantity_change > 0 ? 'qty-plus' : 'qty-minus'">
                {{ m.quantity_change > 0 ? '+' : '' }}{{ m.quantity_change }}
              </span>
              <span><strong>{{ m.quantity_after }}</strong></span>
              <span>{{ m.user?.name }}</span>
              <span class="mut-notes">{{ m.notes }}</span>
            </div>
          </div>
          <!-- Mobile cards -->
          <div class="mut-cards">
            <div v-for="m in mutations" :key="m.id" class="mut-card">
              <div class="mut-card-top">
                <span class="badge" :class="typeBadge(m.type)">{{ typeLabel(m.type) }}</span>
                <span class="mut-card-date">{{ formatDateTime(m.created_at) }}</span>
              </div>
              <div class="mut-card-stocks">
                <span>{{ m.quantity_before }}</span>
                <span class="mut-card-arrow">→</span>
                <span :class="m.quantity_change > 0 ? 'qty-plus' : 'qty-minus'">
                  {{ m.quantity_change > 0 ? '+' : '' }}{{ m.quantity_change }}
                </span>
                <span class="mut-card-arrow">→</span>
                <strong>{{ m.quantity_after }}</strong>
              </div>
              <div class="mut-card-by">Oleh: {{ m.user?.name ?? '-' }}</div>
              <div v-if="m.notes" class="mut-card-notes">{{ m.notes }}</div>
            </div>
          </div>
        </div>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import api from '@/api/axios'
import AppTable from '@/components/AppTable.vue'
import AppPagination from '@/components/AppPagination.vue'
import AppModal from '@/components/AppModal.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const auth    = useAuthStore()
const toast   = useToastStore()

const products    = ref([])
const categories  = ref([])
const meta        = ref(null)
const loading     = ref(false)
const saving      = ref(false)
const deleting    = ref(false)
const showModal   = ref(false)
const showConfirm = ref(false)
const editId      = ref(null)
const deleteTarget = ref(null)

const imagePreview = ref(null)
const imgInput     = ref(null)   // template ref ke <input type="file">

const showMutations   = ref(false)
const loadingMutations = ref(false)
const mutationProduct  = ref(null)
const mutations        = ref([])

const filters = reactive({ search: '', category_id: '', is_active: '' })

const defaultForm = () => ({ code: '', category_id: '', name: '', weight: 250, min_stock: 10, purchase_price: 0, selling_price: 0, description: '', is_active: true, unit: 'gram', existingImage: null })
const form = reactive(defaultForm())

const columns = [
  { key: 'name', label: 'Produk' },
  { key: 'category', label: 'Kategori', width: '110px' },
  { key: 'weight', label: 'Berat', width: '80px' },
  { key: 'selling_price', label: 'Harga Jual', width: '130px' },
  { key: 'stock', label: 'Stok', width: '100px' },
  { key: 'is_active', label: 'Status', width: '90px' },
  { key: 'actions', label: '', width: '200px' },
]

const formatDateTime = (d) => d ? new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '-'

function typeLabel(t) {
  return { stock_in: 'Masuk', sale: 'Jual', void: 'Void', adjustment: 'Koreksi' }[t] || t
}

function typeBadge(t) {
  return { stock_in: 'badge-green', sale: 'badge-blue', void: 'badge-red', adjustment: 'badge-yellow' }[t] || 'badge-gray'
}

async function openMutations(row) {
  mutationProduct.value = row
  mutations.value = []
  showMutations.value = true
  loadingMutations.value = true
  try {
    const { data } = await api.get(`/stock-mutations/${row.id}`)
    mutations.value = data.data
  } finally {
    loadingMutations.value = false
  }
}

function stockBadge(p) {
  const qty = p.stock?.quantity ?? 0
  if (qty === 0) return 'badge-red'
  if (qty <= p.min_stock) return 'badge-yellow'
  return 'badge-green'
}

function formatRp(val) {
  return 'Rp ' + Number(val || 0).toLocaleString('id-ID')
}

async function fetchData(page = 1) {
  loading.value = true
  const { data } = await api.get('/products', { params: { ...filters, page } })
  products.value = data.data
  meta.value     = data.meta
  loading.value  = false
}

async function fetchCategories() {
  const { data } = await api.get('/categories', { params: { per_page: 100 } })
  categories.value = data.data ?? data
}

function resetImage() {
  imagePreview.value = null
  if (imgInput.value) imgInput.value.value = ''  // clear file input DOM
}

function onImagePick(e) {
  const file = e.target.files[0]
  if (!file) return
  imagePreview.value = URL.createObjectURL(file)
}

function onDrop(e) {
  const file = e.dataTransfer.files[0]
  if (!file || !file.type.startsWith('image/')) return
  // Set file ke input DOM secara manual via DataTransfer
  const dt = new DataTransfer()
  dt.items.add(file)
  imgInput.value.files = dt.files
  imagePreview.value = URL.createObjectURL(file)
}

function clearImage() {
  resetImage()
  form.existingImage = null
}

function openCreate() {
  editId.value = null
  Object.assign(form, defaultForm())
  resetImage()
  showModal.value = true
}

function openEdit(row) {
  editId.value = row.id
  Object.assign(form, { ...row, category_id: row.category_id, existingImage: row.image_url })
  resetImage()
  showModal.value = true
}

function openDelete(row) {
  deleteTarget.value = row
  showConfirm.value  = true
}

async function saveProduct() {
  saving.value = true
  try {
    const fd = new FormData()
    const fields = ['code','category_id','name','unit','weight','min_stock','purchase_price','selling_price','description']
    fields.forEach(k => fd.append(k, form[k] ?? ''))
    fd.append('is_active', form.is_active ? '1' : '0')
    // Baca file langsung dari DOM — hindari Vue reactive proxy
    const rawFile = imgInput.value?.files?.[0]
    if (rawFile) fd.append('image', rawFile)

    if (editId.value) {
      fd.append('_method', 'PUT')
      await api.post(`/products/${editId.value}`, fd)
    } else {
      await api.post('/products', fd)
    }
    toast.success(editId.value ? 'Produk berhasil diperbarui.' : 'Produk berhasil ditambahkan.')
    showModal.value = false
    fetchData()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal menyimpan.')
  } finally {
    saving.value = false
  }
}

async function deleteProduct() {
  deleting.value = true
  try {
    await api.delete(`/products/${deleteTarget.value.id}`)
    toast.success('Produk dihapus.')
    showConfirm.value = false
    fetchData()
  } catch (e) {
    toast.error('Gagal menghapus.')
  } finally {
    deleting.value = false
  }
}

onMounted(() => { fetchData(); fetchCategories() })
</script>

<style scoped>
.prod-thumb-wrap { display: flex; align-items: center; gap: 10px; }
.prod-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 8px; flex-shrink: 0; border: 1px solid var(--border); }
.prod-no-img {
  display: flex; align-items: center; justify-content: center;
  background: var(--bg-surface-3); color: var(--text-4);
  font-size: 9px; font-weight: 600; text-align: center; letter-spacing: 0.02em;
  border-radius: 8px; flex-shrink: 0;
}
.prod-name { font-weight: 500; font-size: 14px; }
.prod-code { font-size: 12px; color: #aaa; margin-top: 2px; font-family: monospace; }
.action-btns { display: flex; gap: 6px; flex-wrap: wrap; }

/* Image upload */
.img-upload-area {
  border: 2px dashed var(--border-input);
  border-radius: 10px;
  padding: 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100px;
  transition: border-color 0.15s;
}
.img-upload-area:hover { border-color: var(--brand); }
.img-preview { width: 120px; height: 120px; object-fit: cover; border-radius: 8px; }
.img-existing { display: flex; }
.img-placeholder { display: flex; flex-direction: column; align-items: center; gap: 4px; color: var(--text-3); }
.img-icon { font-size: 28px; }
.img-hint { font-size: 11px; color: var(--text-4); }

.mutations-summary { font-size: 14px; color: #555; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #ebebeb; }
.empty-mutations { text-align: center; padding: 40px; color: #bbb; font-size: 14px; }

/* Desktop mutation table */
.mut-table { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.mut-head { display: grid; grid-template-columns: 130px 80px 60px 80px 60px 90px minmax(100px,1fr); gap: 8px; font-size: 11px; color: #888; font-weight: 600; text-transform: uppercase; padding: 8px 0; border-bottom: 1px solid #ebebeb; min-width: 600px; }
.mut-row  { display: grid; grid-template-columns: 130px 80px 60px 80px 60px 90px minmax(100px,1fr); gap: 8px; font-size: 13px; padding: 8px 0; border-bottom: 1px solid #f5f5f5; align-items: center; min-width: 600px; }
.mut-date { font-size: 11px; color: #888; }
.mut-notes { font-size: 11px; color: #888; word-break: break-word; white-space: normal; }

/* Mobile mutation cards */
.mut-cards { display: none; flex-direction: column; gap: 10px; }
.mut-card { background: var(--bg-surface-2); border-radius: 10px; padding: 12px; display: flex; flex-direction: column; gap: 6px; }
.mut-card-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.mut-card-date { font-size: 11px; color: #888; }
.mut-card-stocks { display: flex; align-items: center; gap: 6px; font-size: 13px; }
.mut-card-arrow { color: #bbb; font-size: 11px; }
.mut-card-by { font-size: 11px; color: #aaa; }
.mut-card-notes { font-size: 12px; color: #666; border-top: 1px solid var(--border); padding-top: 6px; word-break: break-word; }

@media (max-width: 600px) {
  .mut-table { display: none; }
  .mut-cards  { display: flex; }
}

.qty-plus  { color: #065f46; font-weight: 700; }
.qty-minus { color: #cc3333; font-weight: 700; }

@media (max-width: 480px) {
  .action-btns { flex-direction: column; gap: 4px; }
  .action-btns .btn { width: 100%; justify-content: center; }
  .prod-thumb-wrap { gap: 8px; }
  .prod-thumb { width: 34px; height: 34px; }
}
</style>