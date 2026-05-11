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
        {
          path: '/mdm/members',
          name: 'members',
          component: () => import('../views/members/MemberListView.vue'),
        },
        {
          path: '/mdm/members/create',
          name: 'member-create',
          component: () => import('../views/members/MemberFormView.vue'),
        },
        {
          path: '/mdm/members/:id',
          name: 'member-detail',
          component: () => import('../views/members/MemberDetailView.vue'),
        },
        {
          path: '/mdm/members/:id/edit',
          name: 'member-edit',
          component: () => import('../views/members/MemberFormView.vue'),
        },
        {
          path: '/users',
          name: 'users',
          component: () => import('../views/users/UserListView.vue'),
          meta: { roles: ['superadmin', 'admin_branch'] }
        },
        {
          path: '/master/payment-accounts',
          name: 'payment-accounts',
          component: () => import('../views/master/PaymentAccountListView.vue'),
          meta: { roles: ['superadmin', 'admin_branch'] }
        },
        {
          path: '/master/cost-types',
          name: 'cost-types',
          component: () => import('../views/master/CostTypeListView.vue'),
          meta: { roles: ['superadmin', 'admin_branch'] }
        },
        {
          path: '/master/pricing-packages',
          name: 'pricing-packages',
          component: () => import('../views/master/PricingPackageListView.vue'),
          meta: { roles: ['superadmin', 'admin_branch'] }
        },
        {
          path: '/bookings',
          name: 'BookingList',
          component: () => import('../views/bookings/BookingListView.vue'),
        },
        {
          path: '/bookings/create',
          name: 'BookingCreate',
          component: () => import('../views/bookings/BookingCreateView.vue'),
        },
        {
          path: '/bookings/:id',
          name: 'BookingDetail',
          component: () => import('../views/bookings/BookingDetailView.vue'),
        },
      ]
    }
  ]
})

router.beforeEach((to, from) => {
  const auth = useAuthStore()

  if (to.meta.auth && !auth.isAuthenticated) {
    return { name: 'login' }
  } else if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'dashboard' }
  } else {
    return true
  }
})

export default router
