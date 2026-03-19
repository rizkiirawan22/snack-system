<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Pengguna</h1>
        <p class="page-subtitle">Kelola akun admin dan pegawai</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">+ Tambah Pengguna</button>
    </div>

    <AppTable :columns="columns" :data="users" :loading="loading">
      <template #default="{ row }">
        <td>
          <div class="user-row">
            <div class="user-avatar">{{ row.name.charAt(0) }}</div>
            <div>
              <div class="user-name">{{ row.name }}</div>
              <div class="user-email">{{ row.email }}</div>
            </div>
          </div>
        </td>
        <td><span class="badge" :class="row.role === 'admin' ? 'badge-blue' : 'badge-gray'">{{ row.role }}</span></td>
        <td><span class="badge" :class="row.is_active ? 'badge-green' : 'badge-red'">{{ row.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
        <td>
          <div class="action-btns">
            <button class="btn btn-ghost btn-sm" @click="openEdit(row)">Edit</button>
            <button class="btn btn-danger btn-sm" :disabled="row.id === auth.user?.id" @click="openDelete(row)">Hapus</button>
          </div>
        </td>
      </template>
    </AppTable>

    <AppModal v-model="showModal" :title="editId ? 'Edit Pengguna' : 'Tambah Pengguna'" width="480px">
      <div class="form-group">
        <label class="form-label">Nama <span class="required">*</span></label>
        <input v-model="form.name" class="form-control" placeholder="Nama lengkap" />
      </div>
      <div class="form-group">
        <label class="form-label">Email <span class="required">*</span></label>
        <input v-model="form.email" type="email" class="form-control" placeholder="email@contoh.com" />
      </div>
      <div class="form-group">
        <label class="form-label">Password {{ editId ? '(kosongkan jika tidak diubah)' : '*' }}</label>
        <input v-model="form.password" type="password" class="form-control" placeholder="••••••••" />
      </div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Role <span class="required">*</span></label>
          <select v-model="form.role" class="form-control">
            <option value="pegawai">Pegawai</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select v-model="form.is_active" class="form-control">
            <option :value="true">Aktif</option>
            <option :value="false">Nonaktif</option>
          </select>
        </div>
      </div>

      <template #footer>
        <button class="btn btn-ghost" @click="showModal = false">Batal</button>
        <button class="btn btn-primary" :disabled="saving" @click="saveUser">
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </button>
      </template>
    </AppModal>

    <ConfirmDialog
      v-model="showConfirm"
      :message="`Hapus pengguna '${deleteTarget?.name}'?`"
      :loading="deleting"
      @confirm="deleteUser"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import api from '@/api/axios'
import AppTable from '@/components/AppTable.vue'
import AppModal from '@/components/AppModal.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const auth  = useAuthStore()
const toast = useToastStore()

const users       = ref([])
const loading     = ref(false)
const saving      = ref(false)
const deleting    = ref(false)
const showModal   = ref(false)
const showConfirm = ref(false)
const editId      = ref(null)
const deleteTarget = ref(null)

const defaultForm = () => ({ name: '', email: '', password: '', role: 'pegawai', is_active: true })
const form = reactive(defaultForm())

const columns = [
  { key: 'name', label: 'Pengguna' },
  { key: 'role', label: 'Role', width: '90px' },
  { key: 'status', label: 'Status', width: '90px' },
  { key: 'actions', label: '', width: '140px' },
]

async function fetchData() {
  loading.value = true
  const { data } = await api.get('/users')
  users.value   = data.data ?? data
  loading.value = false
}

function openCreate() {
  editId.value = null
  Object.assign(form, defaultForm())
  showModal.value = true
}

function openEdit(row) {
  editId.value = row.id
  Object.assign(form, { name: row.name, email: row.email, password: '', role: row.role, is_active: row.is_active })
  showModal.value = true
}

function openDelete(row) {
  deleteTarget.value = row
  showConfirm.value  = true
}

async function saveUser() {
  saving.value = true
  try {
    const payload = { ...form }
    if (!payload.password) delete payload.password
    if (editId.value) await api.put(`/users/${editId.value}`, payload)
    else              await api.post('/users', payload)
    toast.success('Pengguna berhasil disimpan.')
    showModal.value = false
    fetchData()
  } catch(e) {
    toast.error(e.response?.data?.message || 'Gagal menyimpan.')
  } finally {
    saving.value = false
  }
}

async function deleteUser() {
  deleting.value = true
  try {
    await api.delete(`/users/${deleteTarget.value.id}`)
    toast.success('Pengguna dihapus.')
    showConfirm.value = false
    fetchData()
  } catch(e) {
    toast.error('Gagal menghapus.')
  } finally {
    deleting.value = false
  }
}

onMounted(fetchData)
</script>

<style scoped>
.user-row    { display: flex; align-items: center; gap: 10px; }
.user-avatar { width: 34px; height: 34px; background: #b8f000; color: #1a1a1a; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; flex-shrink: 0; }
.user-name   { font-size: 14px; font-weight: 500; }
.user-email  { font-size: 12px; color: #aaa; }
.action-btns { display: flex; gap: 6px; }
</style>