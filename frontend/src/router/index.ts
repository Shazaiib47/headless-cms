import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AdminLayout from '@/layouts/AdminLayout.vue'
import HomeView from '@/views/public/HomeView.vue'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    requiresAdmin?: boolean
    requiresEditor?: boolean
    guestOnly?: boolean
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'home', component: HomeView },
    {
      path: '/admin/login',
      name: 'login',
      component: () => import('@/views/admin/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/admin',
      component: AdminLayout,
      meta: { requiresAuth: true },
      redirect: { name: 'admin-pages' },
      children: [
        {
          path: 'pages',
          name: 'admin-pages',
          component: () => import('@/views/admin/PagesView.vue'),
        },
        {
          path: 'pages/new',
          name: 'admin-page-new',
          component: () => import('@/views/admin/PageFormView.vue'),
          meta: { requiresEditor: true },
        },
        {
          // Viewers may open pages read-only.
          path: 'pages/:id(\\d+)/edit',
          name: 'admin-page-edit',
          component: () => import('@/views/admin/PageFormView.vue'),
        },
        {
          path: 'users',
          name: 'admin-users',
          component: () => import('@/views/admin/UsersView.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'users/new',
          name: 'admin-user-new',
          component: () => import('@/views/admin/UserFormView.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'users/:id(\\d+)/edit',
          name: 'admin-user-edit',
          component: () => import('@/views/admin/UserFormView.vue'),
          meta: { requiresAdmin: true },
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  if (!to.meta.requiresAuth && !to.meta.guestOnly) return true

  const auth = useAuthStore()
  await auth.ensureLoaded()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'admin-pages' }
  }
  // Conveniences only: the API enforces these permissions regardless.
  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return { name: 'admin-pages' }
  }
  if (to.meta.requiresEditor && !auth.canEdit) {
    return { name: 'admin-pages' }
  }
  return true
})

export default router
