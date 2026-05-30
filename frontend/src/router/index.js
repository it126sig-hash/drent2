import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../stores/auth";
import AppLayout from "../components/AppLayout.vue";

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: "/login",
      name: "login",
      component: () => import("../views/LoginView.vue"),
      meta: { guest: true },
    },
    {
      path: "/invoice/:token",
      name: "PublicInvoice",
      component: () => import("../views/public/PublicInvoiceView.vue"),
    },
    {
      path: "/rent-to-rent/:token",
      name: "PublicRentToRentBill",
      component: () => import("../views/public/PublicRentToRentBillView.vue"),
    },
    {
      path: "/physical-checks/public/:token",
      name: "PublicPhysicalCheckForm",
      component: () =>
        import("../views/physical-checks/PhysicalCheckFormView.vue"),
    },
    {
      path: "/",
      component: AppLayout,
      meta: { auth: true },
      children: [
        {
          path: "",
          name: "dashboard",
          component: () => import("../views/DashboardView.vue"),
        },
        {
          path: "/profile",
          name: "profile",
          component: () => import("../views/profile/UserProfileView.vue"),
        },
        {
          path: "/my-requests",
          name: "my-requests",
          component: () => import("../views/profile/MyRequestListView.vue"),
        },
        {
          path: "/rental-owners",
          name: "rental-owners",
          component: () =>
            import("../views/rental-owners/RentalOwnerListView.vue"),
          
        },
        {
          path: "/units",
          name: "units",
          component: () => import("../views/units/UnitListView.vue"),
          
        },
        {
          path: "/drivers",
          name: "drivers",
          component: () => import("../views/drivers/DriverListView.vue"),
          
        },
        {
          path: "/customers",
          name: "customers",
          component: () => import("../views/customers/CustomerListView.vue"),
          
        },
        {
          path: "/mdm/members",
          name: "members",
          component: () => import("../views/members/MemberListView.vue"),
          
        },
        {
          path: "/mdm/members/create",
          name: "member-create",
          component: () => import("../views/members/MemberFormView.vue"),
        },
        {
          path: "/mdm/members/:id",
          name: "member-detail",
          component: () => import("../views/members/MemberDetailView.vue"),
        },
        {
          path: "/mdm/members/:id/edit",
          name: "member-edit",
          component: () => import("../views/members/MemberFormView.vue"),
        },
        {
          path: "/users",
          name: "users",
          component: () => import("../views/users/UserListView.vue"),
          meta: { permission: "master.user" },
        },
        {
          path: "/master/payment-accounts",
          name: "payment-accounts",
          component: () => import("../views/master/PaymentAccountListView.vue"),
          meta: { permission: "master.payment_account" },
        },

        {
          path: "/master/tenant",
          name: "tenant-settings",
          component: () => import("../views/master/TenantSettingsView.vue"),
          meta: { permission: "master.tenant" },
        },
        {
          path: "/master/branches",
          name: "branches",
          component: () => import("../views/master/BranchListView.vue"),
          meta: { permission: "master.branch" },
        },
        {
          path: "/master/cities",
          name: "cities",
          component: () => import("../views/master/CityListView.vue"),
          meta: { permission: "master.city" },
        },
        {
          path: "/master/cost-types",
          name: "cost-types",
          component: () => import("../views/master/CostTypeListView.vue"),
          meta: { permission: "master.cost_type" },
        },
        {
          path: "/master/pricing-packages",
          name: "pricing-packages",
          component: () => import("../views/master/PricingPackageListView.vue"),
          meta: { permission: "master.pricing_package" },
        },
        {
          path: "/bookings",
          name: "BookingList",
          component: () => import("../views/bookings/BookingListView.vue"),
          
        },
        {
          path: "/supervisor/requests",
          name: "SupervisorRequests",
          component: () =>
            import("../views/supervisor/SupervisorRequestListView.vue"),
          meta: { permission: "booking.supervisor_request", keepAlive: false },
        },
        {
          path: "/bookings/create",
          name: "BookingCreate",
          component: () => import("../views/bookings/BookingCreateView.vue"),
        },
        {
          path: "/bookings/:id/edit",
          name: "BookingEdit",
          component: () => import("../views/bookings/BookingCreateView.vue"),
        },
        {
          path: "/bookings/:id",
          name: "BookingDetail",
          component: () => import("../views/bookings/BookingDetailView.vue"),
        },
        {
          path: "/physical-checks",
          name: "PhysicalCheckList",
          component: () =>
            import("../views/physical-checks/PhysicalCheckListView.vue"),
          
        },
        {
          path: "/finance/receivables",
          name: "ReceivableList",
          component: () => import("../views/finance/ReceivableListView.vue"),
          meta: { permission: "finance.receivable", keepAlive: false },
        },
        {
          path: "/finance/operational-costs",
          name: "OperationalCostList",
          component: () => import("../views/finance/OperationalCostListView.vue"),
          meta: { permission: "finance.operational_cost", keepAlive: false },
        },
        {
          path: "/finance/rent-to-rent",
          name: "RentToRentList",
          component: () => import("../views/finance/RentToRentListView.vue"),
          meta: { permission: "finance.rent_to_rent", keepAlive: false },
        },
        {
          path: "/finance/transactions",
          name: "TransactionList",
          component: () => import("../views/finance/TransactionListView.vue"),
          meta: { permission: "finance.transaction", keepAlive: false },
        },
        {
          path: "/finance/account-mutations",
          name: "PaymentAccountMutations",
          component: () => import("../views/finance/PaymentAccountMutationView.vue"),
          meta: { permission: "finance.account_mutation", keepAlive: false },
        },
        {
          path: "/reports/transactions",
          name: "TransactionReport",
          component: () => import("../views/reports/TransactionReportView.vue"),
          meta: { permission: "finance.monthly_report" },
        },
        {
          path: "/driver/operational",
          name: "DriverOperational",
          component: () => import("../views/driver/DriverOperationalView.vue"),
          meta: { permission: "driver.operational" },
        },
        {
          path: "/physical-checks/:bookingId/:type",
          name: "PhysicalCheckForm",
          component: () =>
            import("../views/physical-checks/PhysicalCheckFormView.vue"),
        },
        {
          path: "/settings/role-permissions",
          name: "RolePermissions",
          component: () => import("../views/settings/RolePermissionView.vue"),
          meta: { permission: "master.role_management" },
        },
        {
          path: "/master/invoice-terms-templates",
          name: "InvoiceTermsTemplates",
          component: () => import("../views/master/InvoiceTermsTemplateListView.vue"),
        },
      ],
    },
  ],
});

router.beforeEach((to, from) => {
  const auth = useAuthStore();

  if (to.meta.auth && !auth.isAuthenticated) {
    return { name: "login" };
  } else if (to.meta.guest && auth.isAuthenticated) {
    return { name: "dashboard" };
  } else if (to.meta.permission && !auth.hasPermission(to.meta.permission)) {
    return { name: "dashboard" };
  } else {
    return true;
  }
});

export default router;
