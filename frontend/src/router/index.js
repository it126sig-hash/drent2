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
      path: '/invoice/:token',
      name: 'PublicInvoice',
      component: () => import('../views/public/PublicInvoiceView.vue'),
    },
    {
      path: '/physical-checks/public/:token',
      name: 'PublicPhysicalCheckForm',
      component: () => import('../views/physical-checks/PhysicalCheckFormView.vue'),
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
          path: '/master/cities',
          name: 'cities',
          component: () => import('../views/master/CityListView.vue'),
          meta: { roles: ['superadmin', 'admin_branch', 'cs'] }
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
          path: '/supervisor/requests',
          name: 'SupervisorRequests',
          component: () => import('../views/supervisor/SupervisorRequestListView.vue'),
          meta: { roles: ['superadmin', 'supervisor'] },
        },
        {
          path: '/bookings/create',
          name: 'BookingCreate',
          component: () => import('../views/bookings/BookingCreateView.vue'),
        },
        {
          path: '/bookings/:id/edit',
          name: 'BookingEdit',
          component: () => import('../views/bookings/BookingCreateView.vue'),
        },
        {
          path: '/bookings/:id',
          name: 'BookingDetail',
          component: () => import('../views/bookings/BookingDetailView.vue'),
        },
        {
          path: '/physical-checks',
          name: 'PhysicalCheckList',
          component: () => import('../views/physical-checks/PhysicalCheckListView.vue'),
        },
        {
          path: '/finance/receivables',
          name: 'ReceivableList',
          component: () => import('../views/finance/ReceivableListView.vue'),
          meta: { roles: ['superadmin', 'admin_branch', 'finance'] },
        },
        {
          path: '/physical-checks/:bookingId/:type',
          name: 'PhysicalCheckForm',
          component: () => import('../views/physical-checks/PhysicalCheckFormView.vue'),
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
  } else if (to.meta.roles && !to.meta.roles.includes(auth.user?.role)) {
    return { name: 'dashboard' }
  } else {
    return true
  }
})

export default router
