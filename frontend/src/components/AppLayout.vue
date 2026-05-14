<script setup>
import { ref, computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'

import { useAuthStore } from '../stores/auth'
import Button from 'primevue/button'
import Toast from 'primevue/toast'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const sidebarCollapsed = ref(false)
const mobileSidebarVisible = ref(false)

const toggleSidebar = () => {
  sidebarCollapsed.value = !sidebarCollapsed.value
}

const toggleMobileSidebar = () => {
  mobileSidebarVisible.value = !mobileSidebarVisible.value
}

const handleLogout = async () => {
  await authStore.logout()
  router.push({ name: 'login' })
}

const menuItems = [
  { label: 'Dashboard', icon: 'pi pi-home', route: '/' },
  { label: 'Booking', icon: 'pi pi-calendar', route: '/bookings' },
  { label: 'Request Supervisor', icon: 'pi pi-inbox', route: '/supervisor/requests', roles: ['superadmin', 'supervisor'] },
  { label: 'Piutang', icon: 'pi pi-wallet', route: '/finance/receivables', roles: ['superadmin', 'admin_branch', 'finance'] },
  { label: 'Cek Fisik', icon: 'pi pi-check-square', route: '/physical-checks' },
  { label: 'Pemilik Rental', icon: 'pi pi-users', route: '/rental-owners' },
  { label: 'Unit Kendaraan', icon: 'pi pi-car', route: '/units' },
  { label: 'Driver', icon: 'pi pi-id-card', route: '/drivers' },
  { label: 'Pelanggan', icon: 'pi pi-users', route: '/customers' },
  { label: 'Member', icon: 'pi pi-id-card', route: '/mdm/members' },
  { label: 'Manajemen User', icon: 'pi pi-user-plus', route: '/users', roles: ['superadmin', 'admin_branch'] },
  { label: 'Akun Pembayaran', icon: 'pi pi-credit-card', route: '/master/payment-accounts', roles: ['superadmin', 'admin_branch'] },
  { label: 'List Kota', icon: 'pi pi-map-marker', route: '/master/cities', roles: ['superadmin', 'admin_branch', 'cs'] },
  { label: 'Tipe Biaya', icon: 'pi pi-list', route: '/master/cost-types', roles: ['superadmin', 'admin_branch'] },
  { label: 'Paket Harga', icon: 'pi pi-tag', route: '/master/pricing-packages', roles: ['superadmin', 'admin_branch'] },
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

const filteredMenuItems = computed(() => {
  return menuItems.filter(item => {
    if (!item.roles) return true
    return item.roles.includes(authStore.user?.role)
  })
})
</script>

<template>
  <div class="layout-wrapper" :class="{ 'sidebar-collapsed': sidebarCollapsed }">
    <Toast />
    
    <!-- Desktop Sidebar -->
    <aside class="layout-sidebar hide-mobile">
      <div class="sidebar-header">
        <h1 class="logo-text">DRENT <span class="tosca-text">Vibe</span></h1>
      </div>
      
      <nav class="sidebar-nav">
        <RouterLink 
          v-for="item in filteredMenuItems" 
          :key="item.route" 
          :to="item.route" 
          class="nav-item"
          :class="{ active: isMenuItemActive(item.route) }"
        >
          <i :class="item.icon"></i>
          <span v-if="!sidebarCollapsed">{{ item.label }}</span>
        </RouterLink>
      </nav>
      
      <div class="sidebar-footer">
        <div class="user-avatar-initials">
          {{ authStore.user?.name?.substring(0, 2).toUpperCase() }}
        </div>
        <div v-if="!sidebarCollapsed" class="user-info">
          <span class="user-name">{{ authStore.user?.name }}</span>
          <span class="user-role">{{ authStore.user?.role }}</span>
        </div>
        <button class="logout-btn" @click="handleLogout" v-tooltip.right="'Logout'">
          <i class="pi pi-power-off"></i>
        </button>
      </div>
    </aside>

    <!-- Mobile Top Bar -->
    <header class="mobile-top-bar show-mobile">
      <button class="menu-btn" @click="toggleMobileSidebar">
        <i class="pi pi-bars"></i>
      </button>
      <h1 class="logo-text-mobile">DRENT <span class="tosca-text">Vibe</span></h1>
      <div class="mobile-header-right">
        <i class="pi pi-bell text-secondary"></i>
        <div class="user-avatar-mini">
           {{ authStore.user?.name?.substring(0, 2).toUpperCase() }}
        </div>
      </div>
    </header>

    <!-- Mobile Sidebar Drawer -->
    <div class="mobile-sidebar-overlay" v-if="mobileSidebarVisible" @click="toggleMobileSidebar"></div>
    <aside class="mobile-sidebar-drawer" :class="{ 'visible': mobileSidebarVisible }">
       <div class="drawer-header">
          <h1 class="logo-text">DRENT <span class="tosca-text">Vibe</span></h1>
          <button class="close-btn" @click="toggleMobileSidebar"><i class="pi pi-times"></i></button>
       </div>
       <nav class="drawer-nav">
          <RouterLink 
            v-for="item in filteredMenuItems" 
            :key="item.route" 
            :to="item.route" 
            class="nav-item"
            :class="{ active: isMenuItemActive(item.route) }"
            @click="mobileSidebarVisible = false"
          >
            <i :class="item.icon"></i>
            <span>{{ item.label }}</span>
          </RouterLink>
       </nav>
       <div class="drawer-footer">
          <button class="btn-pill btn-secondary w-full" @click="handleLogout">
             <i class="pi pi-power-off"></i> Logout
          </button>
       </div>
    </aside>

    <!-- Main Content -->
    <main class="layout-main">
      <div class="content-container">
        <RouterView />
      </div>
    </main>


    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-bottom-nav show-mobile">
      <RouterLink to="/" class="bottom-nav-item" :class="{ active: isMenuItemActive('/') }">
        <i class="pi pi-home"></i>
        <span>Home</span>
      </RouterLink>
      <RouterLink to="/bookings" class="bottom-nav-item" :class="{ active: isMenuItemActive('/bookings') }">
        <i class="pi pi-calendar"></i>
        <span>Booking</span>
      </RouterLink>
      <RouterLink to="/units" class="bottom-nav-item" :class="{ active: isMenuItemActive('/units') }">
        <i class="pi pi-car"></i>
        <span>Unit</span>
      </RouterLink>
      <RouterLink to="/physical-checks" class="bottom-nav-item" :class="{ active: isMenuItemActive('/physical-checks') }">
        <i class="pi pi-check-square"></i>
        <span>Cek Fisik</span>
      </RouterLink>
      <button class="bottom-nav-item" @click="toggleMobileSidebar">
        <i class="pi pi-bars"></i>
        <span>Menu</span>
      </button>
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
  background-color: var(--text-primary);
  border-right: 1px solid rgba(255, 255, 255, 0.08);
  display: flex;
  flex-direction: column;
  transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: sticky;
  top: 0;
  height: 100vh;
  z-index: 100;
}

.sidebar-collapsed .layout-sidebar {
  width: 80px;
}

.sidebar-header {
  height: 64px;
  display: flex;
  align-items: center;
  padding: 0 var(--space-xl);
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.logo-text {
  font-family: var(--font-headline);
  font-size: 1.25rem;
  font-weight: 700;
  color: #FFFFFF;
  white-space: nowrap;
}

.tosca-text { color: #7DD3FC; }

.sidebar-nav {
  flex: 1;
  padding: var(--space-md);
  display: flex;
  flex-direction: column;
  gap: 4px;
  overflow-y: auto;
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
  font-size: 13px;
  font-weight: 500;
  transition: all 0.2s;
}

.nav-item i { font-size: 1.1rem; }

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

.user-avatar-initials {
  width: 32px;
  height: 32px;
  background-color: rgba(255, 255, 255, 0.12);
  color: #FFFFFF;
  border-radius: var(--radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 12px;
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
  background-color: var(--surface-default);
  border-bottom: 1px solid var(--surface-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 var(--space-lg);
  position: sticky;
  top: 0;
  z-index: 90;
}

.logo-text-mobile {
  font-family: var(--font-headline);
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text-primary);
}

.menu-btn {
  background: none;
  border: none;
  font-size: 1.25rem;
  color: var(--text-primary);
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
  width: 20%;
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
   background: #0B1F3A;
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

.drawer-nav {
   flex: 1;
   padding: var(--space-lg);
   overflow-y: auto;
}

.drawer-footer {
   padding: var(--space-lg);
   border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.drawer-footer .btn-secondary {
   background: rgba(255, 255, 255, 0.1);
   border-color: rgba(255, 255, 255, 0.14);
   color: #FFFFFF;
}

.drawer-footer .btn-secondary:hover {
   background: rgba(255, 255, 255, 0.16);
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
