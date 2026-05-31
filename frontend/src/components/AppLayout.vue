<script setup>
import { ref, computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'

import { useAuthStore } from '../stores/auth'
import Toast from 'primevue/toast'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const sidebarCollapsed = ref(false)
const sidebarHovered = ref(false)
const mobileSidebarVisible = ref(false)

const isSidebarCompact = computed(() => sidebarCollapsed.value && !sidebarHovered.value)
const userInitials = computed(() => authStore.user?.name?.substring(0, 2).toUpperCase() || 'US')
const userPhotoUrl = computed(() => authStore.user?.foto_profile_url || null)

const toggleSidebar = () => {
  sidebarCollapsed.value = !sidebarCollapsed.value
}

const handleSidebarMouseEnter = () => {
  sidebarHovered.value = true
}

const handleSidebarMouseLeave = () => {
  sidebarHovered.value = false
}

const toggleMobileSidebar = () => {
  mobileSidebarVisible.value = !mobileSidebarVisible.value
}

const handleLogout = async () => {
  await authStore.logout()
  router.push({ name: 'login' })
}

const menuSections = [
  {
    label: '',
    items: [
      { label: 'Dashboard', icon: 'pi pi-home', route: '/', permission: 'dashboard.view' },
    ],
  },
  {
    label: 'Transaksi',
    items: [
      { label: 'Booking', icon: 'pi pi-calendar', route: '/bookings', permission: 'booking.view' },
      { label: 'Request Supervisor', icon: 'pi pi-inbox', route: '/supervisor/requests', permission: 'booking.supervisor_request' },
      { label: 'Riwayat Request Saya', icon: 'pi pi-history', route: '/my-requests' },
      { label: 'Cek Fisik', icon: 'pi pi-check-square', route: '/physical-checks', permission: 'physical_check.view' },
    ],
  },
  {
    label: 'Keuangan',
    items: [
      { label: 'Piutang', icon: 'pi pi-wallet', route: '/finance/receivables', permission: 'finance.receivable' },
      { label: 'Rent to Rent', icon: 'pi pi-building', route: '/finance/rent-to-rent', permission: 'finance.rent_to_rent' },
      { label: 'Biaya Operasional', icon: 'pi pi-receipt', route: '/finance/operational-costs', permission: 'finance.operational_cost' },
      { label: 'List Transaksi', icon: 'pi pi-list', route: '/finance/transactions', permission: 'finance.transaction' },
      { label: 'Mutasi Rekening', icon: 'pi pi-arrow-right-arrow-left', route: '/finance/account-mutations', permission: 'finance.account_mutation' },
      { label: 'Laporan Bulanan', icon: 'pi pi-chart-bar', route: '/reports/transactions', permission: 'finance.monthly_report' },
      { label: 'Laporan Penggunaan Unit', icon: 'pi pi-car', route: '/reports/unit-usage', permission: 'finance.monthly_report' },
      { label: 'Laporan Penggunaan Driver', icon: 'pi pi-id-card', route: '/reports/driver-usage', permission: 'finance.monthly_report' },
      { label: 'Laporan Pelanggan', icon: 'pi pi-users', route: '/reports/customer-usage', permission: 'finance.monthly_report' },
      { label: 'Operasional Driver', icon: 'pi pi-briefcase', route: '/driver/operational', permission: 'driver.operational' },
    ],
  },
  
  {
    label: 'Kendaraan',
    items: [
      { label: 'Pemilik Rental', icon: 'pi pi-users', route: '/rental-owners', permission: 'vehicle.rental_owner' },
      { label: 'Unit Kendaraan', icon: 'pi pi-car', route: '/units', permission: 'vehicle.unit' },
      { label: 'Driver', icon: 'pi pi-id-card', route: '/drivers', permission: 'vehicle.driver' },
    ],
  },
  {
    label: 'Pelanggan',
    items: [
       { label: 'Pelanggan', icon: 'pi pi-users', route: '/customers', permission: 'customer.view' },
      { label: 'Member', icon: 'pi pi-id-card', route: '/mdm/members', permission: 'member.view' },
    ],
  },
  {
    label: 'Data Master',
    items: [
      { label: 'Profil Tenant', icon: 'pi pi-building', route: '/master/tenant', permission: 'master.tenant' },
      { label: 'Cabang', icon: 'pi pi-sitemap', route: '/master/branches', permission: 'master.branch' },
      { label: 'Manajemen User', icon: 'pi pi-user-plus', route: '/users', permission: 'master.user' },
      { label: 'Akun Pembayaran', icon: 'pi pi-credit-card', route: '/master/payment-accounts', permission: 'master.payment_account' },
      { label: 'List Kota', icon: 'pi pi-map-marker', route: '/master/cities', permission: 'master.city' },
      { label: 'Tipe Biaya', icon: 'pi pi-list', route: '/master/cost-types', permission: 'master.cost_type' },
      { label: 'Paket Harga', icon: 'pi pi-tag', route: '/master/pricing-packages', permission: 'master.pricing_package' },
      { label: 'Manajemen Role', icon: 'pi pi-shield', route: '/settings/role-permissions', permission: 'master.role_management' },
      { label: 'Template S&K Invoice', icon: 'pi pi-file-edit', route: '/master/invoice-terms-templates' },
    ],
  },
]

const normalizePath = (path) => path.replace(/\/+$/, '') || '/'

const isMenuItemActive = (targetRoute) => {
  const currentPath = normalizePath(route.path)
  const itemPath = normalizePath(targetRoute)

  if (itemPath === '/') {
    return currentPath === '/'
  }

  return currentPath === itemPath || currentPath.startsWith(`${itemPath}/`)
}

const canShowMenuItem = (item) => {
    if (!item.permission) return true
    return authStore.hasPermission(item.permission)
}

const filteredMenuSections = computed(() => {
  return menuSections
    .map(section => ({
      ...section,
      items: section.items.filter(canShowMenuItem),
    }))
    .filter(section => section.items.length > 0)
})
</script>

<template>
  <div
    class="layout-wrapper"
    :class="{
      'sidebar-collapsed': sidebarCollapsed,
      'sidebar-compact': isSidebarCompact
    }"
  >
    <Toast />
    
    <!-- Desktop Sidebar -->
    <aside
      class="layout-sidebar hide-mobile"
      @mouseenter="handleSidebarMouseEnter"
      @mouseleave="handleSidebarMouseLeave"
    >
      <div class="sidebar-panel">
        <div class="sidebar-header">
          <img v-if="!isSidebarCompact" class="app-logo app-logo-sidebar" src="/logo.svg" alt="DRENT Vibe" />
          <button
            class="sidebar-toggle-btn"
            type="button"
            :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            :aria-pressed="sidebarCollapsed"
            v-tooltip.right="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            @click="toggleSidebar"
          >
            <i :class="sidebarCollapsed ? 'pi pi-angle-double-right' : 'pi pi-angle-double-left'"></i>
          </button>
        </div>

        <nav class="sidebar-nav">
          <div
            v-for="section in filteredMenuSections"
            :key="section.label || 'main'"
            class="nav-section"
            :class="{ 'nav-section-main': !section.label }"
          >
            <div v-if="section.label && !isSidebarCompact" class="nav-section-title">{{ section.label }}</div>
            <RouterLink 
              v-for="item in section.items" 
              :key="item.route" 
              :to="item.route" 
              class="nav-item"
              :class="{ active: isMenuItemActive(item.route) }"
              v-tooltip.right="isSidebarCompact ? item.label : null"
            >
              <i :class="item.icon"></i>
              <span v-if="!isSidebarCompact" class="nav-label">{{ item.label }}</span>
            </RouterLink>
          </div>
        </nav>

        <div class="sidebar-footer">
          <RouterLink
            to="/profile"
            class="sidebar-profile-link"
            v-tooltip.right="isSidebarCompact ? 'Profil User' : null"
          >
            <div class="user-avatar-initials">
              <img v-if="userPhotoUrl" :src="userPhotoUrl" alt="Foto profil" />
              <span v-else>{{ userInitials }}</span>
            </div>
            <div v-if="!isSidebarCompact" class="user-info">
              <span class="user-name">{{ authStore.user?.name }}</span>
              <span class="user-role">{{ authStore.user?.role }}</span>
            </div>
          </RouterLink>
          <button class="logout-btn" @click="handleLogout" v-tooltip.right="'Logout'">
            <i class="pi pi-power-off"></i>
          </button>
        </div>
      </div>
    </aside>

    <!-- Mobile Top Bar -->
    <header class="mobile-top-bar show-mobile">
      <button class="menu-btn" @click="toggleMobileSidebar">
        <i class="pi pi-bars"></i>
      </button>
      <img class="app-logo app-logo-mobile" src="/logo-light.svg" alt="DRENT Vibe" />
      <div class="mobile-header-right">
        <i class="pi pi-bell" style="color: #FFFFFF;"></i>
        <RouterLink to="/profile" class="user-avatar-mini">
          <img v-if="userPhotoUrl" :src="userPhotoUrl" alt="Foto profil" />
          <span v-else>{{ userInitials }}</span>
        </RouterLink>
      </div>
    </header>

    <!-- Mobile Sidebar Drawer -->
    <div class="mobile-sidebar-overlay" v-if="mobileSidebarVisible" @click="toggleMobileSidebar"></div>
    <aside class="mobile-sidebar-drawer" :class="{ 'visible': mobileSidebarVisible }">
       <div class="drawer-header">
          <img class="app-logo app-logo-drawer" src="/logo.svg" alt="DRENT Vibe" />
          <button class="close-btn" @click="toggleMobileSidebar"><i class="pi pi-times"></i></button>
       </div>
       <nav class="drawer-nav">
        <div
          v-for="section in filteredMenuSections"
          :key="section.label || 'main'"
          class="nav-section"
          :class="{ 'nav-section-main': !section.label }"
        >
          <div v-if="section.label" class="nav-section-title">{{ section.label }}</div>
          <RouterLink 
            v-for="item in section.items" 
            :key="item.route" 
            :to="item.route" 
            class="nav-item"
            :class="{ active: isMenuItemActive(item.route) }"
            @click="mobileSidebarVisible = false"
          >
            <i :class="item.icon"></i>
            <span>{{ item.label }}</span>
          </RouterLink>
        </div>
       </nav>
       <div class="drawer-footer">
          <RouterLink class="btn-pill btn-secondary w-full drawer-profile-btn" to="/profile" @click="mobileSidebarVisible = false">
             <i class="pi pi-user"></i> Profil User
          </RouterLink>
          <button class="btn-pill btn-secondary w-full" @click="handleLogout">
             <i class="pi pi-power-off"></i> Logout
          </button>
       </div>
    </aside>

    <!-- Main Content -->
    <main class="layout-main">
      <div class="content-container">
        <RouterView v-slot="{ Component }">
          <keep-alive>
            <component :is="Component" v-if="route.meta.keepAlive" :key="route.name" />
          </keep-alive>
          <component :is="Component" v-if="!route.meta.keepAlive" :key="route.name" />
        </RouterView>
      </div>
    </main>


    <!-- Mobile Bottom Navigation -->
    <nav v-if="authStore.user?.role === 'driver_tetap'" class="mobile-bottom-nav mobile-bottom-nav-driver show-mobile">
      <RouterLink to="/driver/operational" class="bottom-nav-item" :class="{ active: isMenuItemActive('/driver/operational') }">
        <i class="pi pi-briefcase"></i>
        <span>Operasional</span>
      </RouterLink>
      <RouterLink to="/driver/trip-history" class="bottom-nav-item" :class="{ active: isMenuItemActive('/driver/trip-history') }">
        <i class="pi pi-history"></i>
        <span>Riwayat Jalan</span>
      </RouterLink>
      <RouterLink to="/profile" class="bottom-nav-item" :class="{ active: isMenuItemActive('/profile') }">
        <i class="pi pi-user"></i>
        <span>Profil</span>
      </RouterLink>
    </nav>

    <!-- Mobile Bottom Navigation -->
    <nav v-else class="mobile-bottom-nav show-mobile">
      <RouterLink to="/" class="bottom-nav-item" :class="{ active: isMenuItemActive('/') }">
        <i class="pi pi-home"></i>
        <span>Dashboard</span>
      </RouterLink>
      <RouterLink to="/bookings" class="bottom-nav-item" :class="{ active: isMenuItemActive('/bookings') }">
        <i class="pi pi-calendar"></i>
        <span>Booking</span>
      </RouterLink>
      <RouterLink to="/finance/receivables" class="bottom-nav-item" :class="{ active: isMenuItemActive('/finance/receivables') }">
        <i class="pi pi-wallet"></i>
        <span>Piutang</span>
      </RouterLink>
      <RouterLink to="/finance/rent-to-rent" class="bottom-nav-item" :class="{ active: isMenuItemActive('/finance/rent-to-rent') }">
        <i class="pi pi-building"></i>
        <span>Rent2Rent</span>
      </RouterLink>
    </nav>
  </div>
</template>

<style scoped>
.layout-wrapper {
  display: flex;
  min-height: 100vh;
  background-color: var(--page-bg);
}

/* === Desktop Sidebar === */
.layout-sidebar {
  width: 260px;
  flex: 0 0 260px;
  position: sticky;
  top: 0;
  height: 100vh;
  z-index: 100;
  transition:
    width 0.3s cubic-bezier(0.4, 0, 0.2, 1),
    flex-basis 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar-panel {
  width: 260px;
  height: 100%;
  background-color: var(--text-primary);
  border-right: 1px solid rgba(255, 255, 255, 0.08);
  display: flex;
  flex-direction: column;
  transition:
    width 0.3s cubic-bezier(0.4, 0, 0.2, 1),
    box-shadow 0.2s ease;
  overflow: hidden;
}

.sidebar-collapsed .layout-sidebar {
  flex-basis: 80px;
  width: 80px;
}

.sidebar-collapsed .sidebar-panel {
  width: 80px;
}

.sidebar-collapsed:not(.sidebar-compact) {
  z-index: 200;
}

.sidebar-collapsed:not(.sidebar-compact) .sidebar-panel {
  width: 260px;
  box-shadow: var(--shadow-modal);
}

.sidebar-header {
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-md);
  padding: 0 var(--space-xl);
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.sidebar-compact .sidebar-header {
  justify-content: center;
  padding: 0;
}

.app-logo {
  display: block;
  flex: 0 0 auto;
  object-fit: contain;
}

.app-logo-sidebar {
  width: 126px;
  height: 36px;
  object-position: left center;
}

.sidebar-toggle-btn {
  width: 34px;
  height: 34px;
  flex: 0 0 auto;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: var(--radius-sm);
  background: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.78);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background-color 0.2s, color 0.2s, border-color 0.2s;
}

.sidebar-toggle-btn:hover {
  background: rgba(255, 255, 255, 0.14);
  border-color: rgba(255, 255, 255, 0.2);
  color: #FFFFFF;
}

.sidebar-nav {
  flex: 1;
  padding: var(--space-md);
  display: flex;
  flex-direction: column;
  gap: 4px;
  overflow-y: auto;
  scrollbar-color: rgba(255, 255, 255, 0.28) transparent;
  scrollbar-width: thin;
}

.sidebar-nav::-webkit-scrollbar {
  width: 6px;
}

.sidebar-nav::-webkit-scrollbar-track {
  background: transparent;
}

.sidebar-nav::-webkit-scrollbar-thumb {
  background-color: rgba(255, 255, 255, 0.22);
  border-radius: var(--radius-full);
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
  background-color: rgba(255, 255, 255, 0.36);
}

.sidebar-compact .sidebar-nav {
  align-items: center;
}

.nav-section {
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 100%;
  padding-top: var(--space-md);
  margin-top: var(--space-md);
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.nav-section-main {
  padding-top: 0;
  margin-top: 0;
  border-top: none;
}

.sidebar-compact .nav-section {
  align-items: center;
}

.nav-section-title {
  padding: 0 var(--space-lg) var(--space-xs);
  color: rgba(255, 255, 255, 0.42);
  font-family: var(--font-headline);
  font-size: 10px;
  font-weight: 600;
  line-height: 1.4;
  letter-spacing: 0;
  text-transform: uppercase;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-md) var(--space-lg);
  border-radius: var(--radius-default);
  color: rgba(255, 255, 255, 0.72);
  text-decoration: none;
  font-family: var(--font-body);
  font-size: 10px;
  font-weight: 500;
  transition: all 0.2s;
  width: 100%;
  min-height: 32px;
  min-width: 0;
}

.sidebar-compact .nav-item {
  width: 44px;
  justify-content: center;
  padding: var(--space-md);
}

.nav-item i {
  width: 18px;
  flex: 0 0 18px;
  font-size: 1.1rem;
  text-align: center;
}

.nav-label {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.nav-item:hover {
  background-color: rgba(255, 255, 255, 0.08);
  color: #FFFFFF;
}

.nav-item.active {
  background-color: #FFFFFF;
  color: #0B1F3A;
  font-weight: 600;
}

.sidebar-footer {
  padding: var(--space-lg);
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  display: flex;
  align-items: center;
  gap: var(--space-md);
}

.sidebar-compact .sidebar-footer {
  flex-direction: column;
  justify-content: center;
  padding: var(--space-md) 0;
}

.sidebar-profile-link {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: var(--space-md);
  color: inherit;
  text-decoration: none;
}

.sidebar-compact .sidebar-profile-link {
  flex: 0 0 auto;
}

.user-avatar-initials {
  width: 32px;
  height: 32px;
  flex: 0 0 32px;
  background-color: rgba(255, 255, 255, 0.12);
  color: #FFFFFF;
  border-radius: var(--radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 12px;
  overflow: hidden;
}

.user-avatar-initials img,
.user-avatar-mini img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.user-name {
  font-size: 12px;
  font-weight: 600;
  color: #FFFFFF;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role {
  font-size: 10px;
  color: rgba(255, 255, 255, 0.64);
}

.logout-btn {
  background: none;
  border: none;
  color: rgba(255, 255, 255, 0.72);
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
}

.logout-btn:hover { color: #FCA5A5; }

/* === Layout Main === */
.layout-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.content-container {
  flex: 1;
}

/* === Mobile Elements === */
.mobile-top-bar {
  height: 56px;
  background-color: var(--primary);
  border-bottom: none;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 var(--space-lg);
  position: sticky;
  top: 0;
  z-index: 90;
  color: #FFFFFF;
}

.app-logo-mobile {
  width: 116px;
  height: 32px;
}

.menu-btn {
  background: none;
  border: none;
  font-size: 1.25rem;
  color: #FFFFFF;
}

.mobile-header-right {
   display: flex;
   align-items: center;
   gap: var(--space-lg);
}

.user-avatar-mini {
   width: 28px;
   height: 28px;
   border-radius: var(--radius-full);
   background: var(--card-bg);
   display: flex;
   align-items: center;
   justify-content: center;
   font-size: 10px;
   font-weight: 700;
   color: var(--text-primary);
   text-decoration: none;
   overflow: hidden;
}

.mobile-bottom-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 56px;
  background-color: var(--surface-default);
  border-top: 1px solid var(--surface-border);
  display: flex;
  align-items: center;
  justify-content: space-around;
  z-index: 100;
  padding-bottom: env(safe-area-inset-bottom);
}

.bottom-nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  color: var(--text-secondary);
  text-decoration: none;
  background: none;
  border: none;
  padding: 8px 0;
  width: 25%;
}

.mobile-bottom-nav-driver .bottom-nav-item {
  width: 33.3333%;
}

.bottom-nav-item i { font-size: 1.2rem; }
.bottom-nav-item span { font-size: 10px; font-weight: 500; }

.bottom-nav-item.active {
  color: #0B1F3A;
  font-weight: 600;
}

/* === Mobile Sidebar Drawer === */
.mobile-sidebar-overlay {
   position: fixed;
   top: 0; left: 0; right: 0; bottom: 0;
   background: rgba(26, 29, 46, 0.4);
   backdrop-filter: blur(2px);
   z-index: 1000;
}

.mobile-sidebar-drawer {
   position: fixed;
   top: 0; left: -280px;
   width: 280px;
   height: 100vh;
   background: var(--primary);
   z-index: 1001;
   transition: left 0.3s ease;
   display: flex;
   flex-direction: column;
}

.mobile-sidebar-drawer.visible {
   left: 0;
}

.drawer-header {
   padding: var(--space-xl);
   display: flex;
   justify-content: space-between;
   align-items: center;
   border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.app-logo-drawer {
   width: 132px;
   height: 38px;
   object-position: left center;
}

.drawer-nav {
   flex: 1;
   padding: var(--space-lg);
   overflow-y: auto;
}

.drawer-nav .nav-section:first-child {
   padding-top: 0;
   margin-top: 0;
   border-top: none;
}

.drawer-footer {
   padding: var(--space-lg);
   border-top: 1px solid rgba(255, 255, 255, 0.08);
   display: flex;
   flex-direction: column;
   gap: var(--space-sm);
}

.drawer-footer .btn-secondary {
   background: rgba(255, 255, 255, 0.1);
   border-color: rgba(255, 255, 255, 0.14);
   color: #FFFFFF;
}

.drawer-footer .btn-secondary:hover {
   background: rgba(255, 255, 255, 0.16);
}

.drawer-profile-btn {
   justify-content: center;
   text-decoration: none;
}

.close-btn { background: none; border: none; font-size: 1.2rem; color: rgba(255, 255, 255, 0.72); }

/* === Utility === */
.show-mobile { display: none; }

@media (max-width: 992px) {
  .layout-wrapper {
    flex-direction: column;
  }
  .hide-mobile { display: none; }
  .show-mobile { display: flex; }
  .layout-main { padding-bottom: 56px; } /* Space for bottom nav */
}
</style>
