<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import Button from 'primevue/button'
import Toast from 'primevue/toast'

const router = useRouter()
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
  { label: 'Pemilik Rental', icon: 'pi pi-users', route: '/rental-owners' },
  { label: 'Unit Kendaraan', icon: 'pi pi-car', route: '/units' },
  { label: 'Driver', icon: 'pi pi-id-card', route: '/drivers' },
  { label: 'Pelanggan', icon: 'pi pi-users', route: '/customers' },
  { label: 'Member', icon: 'pi pi-id-card', route: '/mdm/members' },
  { label: 'Manajemen User', icon: 'pi pi-user-plus', route: '/users', roles: ['superadmin', 'admin_branch'] },
]

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
    
    <!-- Sidebar -->
    <aside class="layout-sidebar" :class="{ 'mobile-visible': mobileSidebarVisible }">
      <div class="sidebar-header">
        <span class="logo-text" v-if="!sidebarCollapsed">DRENT</span>
        <span class="logo-text-mini" v-else>D</span>
      </div>
      
      <nav class="sidebar-nav">
        <RouterLink 
          v-for="item in filteredMenuItems" 
          :key="item.route" 
          :to="item.route" 
          class="nav-item"
          @click="mobileSidebarVisible = false"
        >
          <i :class="item.icon"></i>
          <span v-if="!sidebarCollapsed">{{ item.label }}</span>
          <span class="tooltip" v-if="sidebarCollapsed">{{ item.label }}</span>
        </RouterLink>
      </nav>
      
      <div class="sidebar-footer">
        <div class="user-info-mini" v-if="sidebarCollapsed">
          <i class="pi pi-user"></i>
        </div>
        <div class="user-info" v-else>
          <p class="user-name">{{ authStore.user?.name }}</p>
          <p class="user-role">{{ authStore.user?.role }}</p>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="layout-main-container">
      <!-- Topbar -->
      <header class="layout-topbar">
        <div class="topbar-left">
          <Button icon="pi pi-bars" @click="toggleSidebar" class="p-button-text p-button-secondary hide-mobile" />
          <Button icon="pi pi-bars" @click="toggleMobileSidebar" class="p-button-text p-button-secondary show-mobile" />
          <h1 class="page-title">DRENT <span class="tosca-text">Vibe</span></h1>
        </div>
        
        <div class="topbar-right">
          <div class="branch-tag">
            <i class="pi pi-map-marker"></i>
            <span>{{ authStore.branch?.name || 'Global' }}</span>
          </div>
          <Button 
            icon="pi pi-sign-out" 
            label="Logout" 
            @click="handleLogout" 
            class="p-button-text logout-btn" 
          />
        </div>
      </header>

      <!-- Page Content -->
      <main class="layout-content">
        <RouterView />
      </main>
    </div>

    <!-- Mobile Overlay -->
    <div 
      class="mobile-overlay" 
      v-if="mobileSidebarVisible" 
      @click="mobileSidebarVisible = false"
    ></div>
  </div>
</template>

<style scoped>
.layout-wrapper {
  display: flex;
  min-height: 100vh;
  background-color: #f8fafc;
}

/* Sidebar */
.layout-sidebar {
  width: 260px;
  background-color: #0f172a;
  color: #f1f5f9;
  display: flex;
  flex-direction: column;
  transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: 1000;
  position: fixed;
  height: 100vh;
}

.sidebar-collapsed .layout-sidebar {
  width: 80px;
}

.sidebar-header {
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.logo-text {
  font-size: 1.5rem;
  font-weight: 800;
  letter-spacing: 2px;
  color: #06b6d4;
}

.logo-text-mini {
  font-size: 1.5rem;
  font-weight: 800;
  color: #06b6d4;
}

.sidebar-nav {
  flex: 1;
  padding: 20px 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.nav-item {
  display: flex;
  align-items: center;
  padding: 12px 24px;
  color: #94a3b8;
  text-decoration: none;
  transition: all 0.2s;
  position: relative;
  gap: 15px;
}

.sidebar-collapsed .nav-item {
  justify-content: center;
  padding: 15px 0;
}

.nav-item i {
  font-size: 1.2rem;
}

.nav-item:hover {
  background-color: rgba(255, 255, 255, 0.05);
  color: #06b6d4;
}

/* Link Active State */
.router-link-active:not([href="/"]), 
.router-link-exact-active {
  background-color: rgba(6, 182, 212, 0.1);
  color: #06b6d4;
  border-left: 4px solid #06b6d4;
}

.sidebar-collapsed .router-link-active:not([href="/"]),
.sidebar-collapsed .router-link-exact-active {
  border-left: none;
  background-color: rgba(6, 182, 212, 0.15);
}

.tooltip {
  position: absolute;
  left: 100%;
  margin-left: 10px;
  background-color: #1e293b;
  color: white;
  padding: 5px 12px;
  border-radius: 4px;
  font-size: 0.8rem;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.2s;
  z-index: 1001;
}

.nav-item:hover .tooltip {
  opacity: 1;
}

.sidebar-footer {
  padding: 20px;
  background-color: rgba(0, 0, 0, 0.2);
}

.user-info {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-weight: 600;
  margin: 0;
  font-size: 0.95rem;
}

.user-role {
  font-size: 0.8rem;
  color: #64748b;
  margin: 0;
  text-transform: capitalize;
}

.user-info-mini {
  display: flex;
  justify-content: center;
  color: #94a3b8;
}

/* Main Container */
.layout-main-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  margin-left: 260px;
  transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  min-width: 0;
}

.sidebar-collapsed .layout-main-container {
  margin-left: 80px;
}

/* Topbar */
.layout-topbar {
  height: 64px;
  background-color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  border-bottom: 1px solid #e2e8f0;
  position: sticky;
  top: 0;
  z-index: 999;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 15px;
}

.page-title {
  font-size: 1.2rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.tosca-text {
  color: #06b6d4;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 20px;
}

.branch-tag {
  display: flex;
  align-items: center;
  gap: 8px;
  background-color: #f1f5f9;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.85rem;
  color: #475569;
}

.branch-tag i {
  color: #06b6d4;
}

.logout-btn {
  color: #ef4444 !important;
  font-weight: 600 !important;
}

/* Content Area */
.layout-content {
  padding: 24px;
  flex: 1;
}

/* Mobile Helpers */
.show-mobile {
  display: none;
}

.mobile-overlay {
  display: none;
}

@media (max-width: 992px) {
  .layout-sidebar {
    left: -260px;
    transition: left 0.3s ease;
  }
  
  .layout-sidebar.mobile-visible {
    left: 0;
  }
  
  .layout-main-container {
    margin-left: 0 !important;
  }
  
  .hide-mobile {
    display: none;
  }
  
  .show-mobile {
    display: block;
  }
  
  .mobile-overlay {
    display: block;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 999;
  }
}
</style>
