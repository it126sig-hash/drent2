import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppLayout from '../components/AppLayout.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
      meta: { guest: true }
    },
    {
      path: '/',
      component: AppLayout,
      meta: { auth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('../views/DashboardView.vue'),
        },
        {
          path: '/rental-owners',
          name: 'rental-owners',
          component: () => import('../views/rental-owners/RentalOwnerListView.vue'),
        },
        {
          path: '/units',
          name: 'units',
          component: () => import('../views/units/UnitListView.vue'),
        },
        {
          path: '/drivers',
          name: 'drivers',
          component: () => import('../views/drivers/DriverListView.vue'),
        },
        {
          path: '/customers',
          name: 'customers',
          component: () => import('../views/customers/CustomerListView.vue'),
        },
      ]
    }
  ]
})

router.beforeEach((to, from, next) => {
  const auth = useAuthStore()
  
  if (to.meta.auth && !auth.isAuthenticated) {
    next({ name: 'login' })
  } else if (to.meta.guest && auth.isAuthenticated) {
    next({ name: 'dashboard' })
  } else {
    next()
  }
})

export default router
