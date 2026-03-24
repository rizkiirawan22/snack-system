<template>
  <div class="login-page">
    <div class="login-card">
      <div class="login-brand">
        <div class="brand-logo-wrap">
          <AppLogo size="lg" :show-text="false" />
        </div>
        <h1 class="brand-name">SnackKilo</h1>
        <p class="brand-sub">Sistem Manajemen Toko Snack</p>
      </div>

      <form @submit.prevent="handleLogin" class="login-form">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input v-model="form.email" type="email" class="form-control" :class="{ error: errors.email }" placeholder="email@contoh.com" />
          <p v-if="errors.email" class="form-error">{{ errors.email }}</p>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input v-model="form.password" type="password" class="form-control" placeholder="••••••••" />
        </div>
        <p v-if="globalError" class="global-error">{{ globalError }}</p>
        <button type="submit" class="btn btn-primary login-btn" :disabled="loading">
          {{ loading ? 'Masuk...' : 'Masuk' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppLogo from '@/components/AppLogo.vue'

const auth        = useAuthStore()
const router      = useRouter()
const loading     = ref(false)
const globalError = ref('')
const errors      = reactive({})
const form        = reactive({ email: '', password: '' })

async function handleLogin() {
  loading.value     = true
  globalError.value = ''
  try {
    await auth.login(form.email, form.password)
    router.push('/')
  } catch (e) {
    const data = e.response?.data
    if (data?.errors) Object.assign(errors, data.errors)
    else globalError.value = data?.message || 'Terjadi kesalahan.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f5f0;
  padding: 20px;
}

.login-card {
  width: 100%;
  max-width: 420px;
  background: #fff;
  border-radius: 20px;
  padding: 40px;
  border: 1px solid #ebebeb;
  box-shadow: 0 8px 32px rgba(0,0,0,0.08);
}

.login-brand     { text-align: center; margin-bottom: 32px; }
.brand-logo-wrap { display: flex; justify-content: center; margin-bottom: 14px; }
.brand-name      { font-size: 24px; font-weight: 700; color: #1a1a1a; }
.brand-sub       { font-size: 14px; color: #888; margin-top: 4px; }

.login-form  { display: flex; flex-direction: column; }
.login-btn   { width: 100%; justify-content: center; padding: 12px; font-size: 15px; margin-top: 8px; }
.global-error { font-size: 13px; color: #ff4444; text-align: center; margin-bottom: 12px; }
</style>