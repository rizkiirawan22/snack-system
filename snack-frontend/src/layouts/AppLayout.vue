<template>
  <div class="app-wrapper">
    <!-- Mobile backdrop -->
    <Transition name="fade">
      <div v-if="mobileMenuOpen" class="mobile-backdrop" @click="mobileMenuOpen = false" />
    </Transition>

    <!-- Sidebar -->
    <aside class="sidebar" :class="{ collapsed: sidebarCollapsed, 'mobile-open': mobileMenuOpen }">
      <div class="sidebar-header">
        <div class="logo">
          <span class="logo-icon">🍿</span>
          <span class="logo-text">SnackKilo</span>
        </div>
        <button class="collapse-btn desktop-only" @click="sidebarCollapsed = !sidebarCollapsed">
          <span>{{ sidebarCollapsed ? '›' : '‹' }}</span>
        </button>
        <button class="collapse-btn mobile-only" @click="mobileMenuOpen = false">✕</button>
      </div>

      <nav class="sidebar-nav">
        <RouterLink to="/" class="nav-item" exact-active-class="active">
          <span class="nav-icon">⊞</span>
          <span class="nav-label">Dashboard</span>
        </RouterLink>
        <RouterLink to="/products" class="nav-item" active-class="active">
          <span class="nav-icon">◫</span>
          <span class="nav-label">Produk</span>
        </RouterLink>
        <RouterLink to="/stock-in" class="nav-item" active-class="active">
          <span class="nav-icon">↓</span>
          <span class="nav-label">Stok Masuk</span>
        </RouterLink>
        <RouterLink to="/stock-adjustments" class="nav-item" active-class="active">
          <span class="nav-icon">⊕</span>
          <span class="nav-label">Koreksi Stok</span>
        </RouterLink>
        <RouterLink to="/sales" class="nav-item" active-class="active">
          <span class="nav-icon">◈</span>
          <span class="nav-label">Penjualan</span>
        </RouterLink>
        <RouterLink to="/cash-closing" class="nav-item" active-class="active">
          <span class="nav-icon">◎</span>
          <span class="nav-label">Tutup Kasir</span>
        </RouterLink>
        <template v-if="auth.isAdmin">
          <div class="nav-divider"><span>Admin</span></div>
          <RouterLink to="/suppliers" class="nav-item" active-class="active">
            <span class="nav-icon">⊛</span>
            <span class="nav-label">Supplier</span>
          </RouterLink>
          <RouterLink to="/reports" class="nav-item" active-class="active">
            <span class="nav-icon">▦</span>
            <span class="nav-label">Laporan</span>
          </RouterLink>
          <RouterLink to="/users" class="nav-item" active-class="active">
            <span class="nav-icon">◉</span>
            <span class="nav-label">Pengguna</span>
          </RouterLink>
        </template>
      </nav>

      <div class="sidebar-footer">
        <div class="user-info">
          <div class="avatar">{{ auth.user?.name?.charAt(0) }}</div>
          <div class="user-detail">
            <div class="user-name">{{ auth.user?.name }}</div>
            <div class="user-role">{{ auth.user?.role }}</div>
          </div>
        </div>
        <button class="theme-btn" :title="theme.isDark ? 'Light Mode' : 'Dark Mode'" @click="theme.toggle()">
          {{ theme.isDark ? '☀' : '☾' }}
        </button>
        <button class="logout-btn" title="Keluar" @click="handleLogout">⎋</button>
      </div>
    </aside>

    <!-- Main content -->
    <main class="main-content">
      <!-- Mobile top bar -->
      <div class="mobile-topbar">
        <button class="hamburger-btn" @click="mobileMenuOpen = true">☰</button>
        <span class="mobile-brand">🍿 SnackKilo</span>
        <button class="theme-btn-mobile" @click="theme.toggle()">{{ theme.isDark ? '☀' : '☾' }}</button>
      </div>

      <RouterView />
    </main>

    <ToastContainer />
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import ToastContainer from '@/components/ToastContainer.vue'

const auth  = useAuthStore()
const theme = useThemeStore()
const router = useRouter()
const route  = useRoute()
const sidebarCollapsed = ref(false)
const mobileMenuOpen   = ref(false)

// Tutup sidebar mobile saat navigasi
watch(() => route.path, () => { mobileMenuOpen.value = false })

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<style scoped>
.app-wrapper {
  display: flex;
  min-height: 100vh;
  background: var(--bg-page);
  transition: background var(--transition);
}

/* ===== SIDEBAR ===== */
.sidebar {
  width: 240px;
  min-height: 100vh;
  background: var(--sidebar-bg);
  display: flex;
  flex-direction: column;
  transition: width 0.25s ease, background var(--transition);
  flex-shrink: 0;
  position: sticky;
  top: 0;
  height: 100vh;
  overflow: hidden;
  z-index: 200;
}

.sidebar.collapsed { width: 64px; }
.sidebar.collapsed .logo-text,
.sidebar.collapsed .nav-label,
.sidebar.collapsed .user-detail,
.sidebar.collapsed .nav-divider span { display: none; }
.sidebar.collapsed .logo { justify-content: center; }
.sidebar.collapsed .user-info { justify-content: center; }
.sidebar.collapsed .nav-item { justify-content: center; padding: 12px 0; }

.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 16px;
  border-bottom: 1px solid var(--sidebar-border);
}

.logo { display: flex; align-items: center; gap: 10px; }
.logo-icon { font-size: 20px; }
.logo-text { color: #ffffff; font-weight: 600; font-size: 16px; white-space: nowrap; }

.collapse-btn {
  background: none;
  border: 1px solid var(--sidebar-border);
  color: var(--sidebar-text);
  width: 28px; height: 28px;
  border-radius: 6px;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px;
  flex-shrink: 0;
}
.collapse-btn:hover { background: var(--sidebar-hover-bg); color: #fff; }
.mobile-only  { display: none; }
.desktop-only { display: flex; }

.sidebar-nav {
  flex: 1;
  padding: 12px 8px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  overflow-y: auto;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  color: var(--sidebar-text);
  text-decoration: none;
  font-size: 14px;
  transition: all 0.15s;
  white-space: nowrap;
}
.nav-item:hover  { background: var(--sidebar-hover-bg); color: var(--sidebar-hover-text); }
.nav-item.active { background: var(--sidebar-active-bg); color: var(--sidebar-active-text); font-weight: 600; }
.nav-icon { font-size: 16px; flex-shrink: 0; width: 20px; text-align: center; }

.nav-divider {
  padding: 16px 12px 4px;
  color: var(--sidebar-divider);
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.sidebar-footer {
  padding: 12px 16px;
  border-top: 1px solid var(--sidebar-border);
  display: flex;
  align-items: center;
  gap: 8px;
}

.user-info { display: flex; align-items: center; gap: 8px; flex: 1; overflow: hidden; }

.avatar {
  width: 32px; height: 32px;
  background: var(--brand);
  color: var(--brand-text);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 13px;
  flex-shrink: 0;
}

.user-name { color: #ddd; font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-role { color: var(--sidebar-text); font-size: 11px; text-transform: capitalize; }

.theme-btn, .logout-btn {
  background: none;
  border: 1px solid var(--sidebar-border);
  color: var(--sidebar-text);
  width: 32px; height: 32px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  flex-shrink: 0;
  transition: all 0.15s;
}
.theme-btn:hover  { background: var(--sidebar-hover-bg); color: var(--brand); }
.logout-btn:hover { background: #ff4444; border-color: #ff4444; color: #fff; }

/* ===== MAIN CONTENT ===== */
.main-content {
  flex: 1;
  padding: 32px;
  overflow-y: auto;
  min-height: 100vh;
  min-width: 0;
  transition: background var(--transition);
}

/* ===== MOBILE TOPBAR ===== */
.mobile-topbar { display: none; }

.hamburger-btn {
  background: none;
  border: 1px solid var(--border-input);
  color: var(--text-2);
  width: 38px; height: 38px;
  border-radius: 9px;
  cursor: pointer;
  font-size: 18px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.hamburger-btn:hover { background: var(--bg-surface-2); }

.mobile-brand {
  flex: 1;
  font-weight: 700;
  font-size: 16px;
  color: var(--text-1);
}

.theme-btn-mobile {
  background: none;
  border: 1px solid var(--border-input);
  color: var(--text-2);
  width: 38px; height: 38px;
  border-radius: 9px;
  cursor: pointer;
  font-size: 15px;
  flex-shrink: 0;
}

/* ===== MOBILE BACKDROP ===== */
.mobile-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 199;
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.25s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
  /* Sidebar jadi drawer overlay */
  .sidebar {
    position: fixed;
    left: -260px;
    height: 100vh;
    transition: left 0.25s ease;
  }
  .sidebar.mobile-open { left: 0; }

  /* Sembunyikan tombol collapse desktop, tampilkan tombol tutup mobile */
  .desktop-only { display: none !important; }
  .mobile-only  { display: flex !important; }

  /* Topbar mobile tampil */
  .mobile-topbar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    background: var(--bg-surface);
    border-bottom: 1px solid var(--border);
    margin: -16px -16px 16px;
    position: sticky;
    top: 0;
    z-index: 100;
  }

  .main-content {
    padding: 16px;
    min-height: 100vh;
  }
}
</style>
