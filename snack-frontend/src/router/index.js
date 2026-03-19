import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const APP = 'SnackKilo'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', component: () => import('@/views/LoginView.vue'), meta: { guest: true, title: 'Login' } },
    {
      path: '/',
      component: () => import('@/layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '',                name: 'dashboard',        component: () => import('@/views/DashboardView.vue'),       meta: { title: 'Dashboard' } },
        { path: 'products',        name: 'products',         component: () => import('@/views/ProductsView.vue'),        meta: { title: 'Produk' } },
        { path: 'stock-in',        name: 'stock-in',         component: () => import('@/views/StockInView.vue'),         meta: { title: 'Stok Masuk' } },
        { path: 'stock-adjustments', name: 'stock-adjustments', component: () => import('@/views/StockAdjustmentsView.vue'), meta: { title: 'Koreksi Stok' } },
        { path: 'sales',           name: 'sales',            component: () => import('@/views/SalesView.vue'),           meta: { title: 'Penjualan' } },
        { path: 'sales/new',       name: 'sales-new',        component: () => import('@/views/SalesNewView.vue'),        meta: { title: 'Kasir — Transaksi Baru' } },
        { path: 'cash-closing',    name: 'cash-closing',     component: () => import('@/views/CashClosingView.vue'),     meta: { title: 'Tutup Kasir' } },
        { path: 'suppliers',       name: 'suppliers',        component: () => import('@/views/SuppliersView.vue'),       meta: { title: 'Supplier', adminOnly: true } },
        { path: 'reports',         name: 'reports',          component: () => import('@/views/ReportsView.vue'),         meta: { title: 'Laporan', adminOnly: true } },
        { path: 'users',           name: 'users',            component: () => import('@/views/UsersView.vue'),           meta: { title: 'Pengguna', adminOnly: true } },
      ],
    },
  ],
})

router.beforeEach((to, _from, next) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isLoggedIn) return next('/login')
  if (to.meta.guest && auth.isLoggedIn) return next('/')
  if (to.meta.adminOnly && !auth.isAdmin) return next('/')
  next()
})

router.afterEach((to) => {
  const title = to.meta?.title
  document.title = title ? `${title} — ${APP}` : APP
})

export default router
