import { defineStore } from 'pinia'
import axios from '../api/axios'

const parseStoredJson = (key, fallback = null) => {
  const value = localStorage.getItem(key)

  if (!value) return fallback

  try {
    return JSON.parse(value)
  } catch (error) {
    localStorage.removeItem(key)
    return fallback
  }
}

const normalizePermissions = (permissions) => {
  if (Array.isArray(permissions)) {
    return [...new Set(permissions.filter((permission) => typeof permission === 'string' && permission.trim()))]
  }

  if (typeof permissions === 'string') {
    const value = permissions.trim()

    if (!value) return []

    try {
      return normalizePermissions(JSON.parse(value))
    } catch (error) {
      return value.split(',').map((permission) => permission.trim()).filter(Boolean)
    }
  }

  if (permissions && typeof permissions === 'object') {
    if (Array.isArray(permissions.data)) return normalizePermissions(permissions.data)
    if (Array.isArray(permissions.permissions)) return normalizePermissions(permissions.permissions)

    const values = Object.values(permissions)

    if (values.every((permission) => typeof permission === 'string')) {
      return normalizePermissions(values)
    }

    return Object.entries(permissions)
      .filter(([, value]) => value === true || value === 1 || value === '1' || value === 'true')
      .map(([permission]) => permission)
  }

  return []
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: parseStoredJson('user', null),
    token: localStorage.getItem('token') || null,
    branch: parseStoredJson('branch', null),
    permissions: normalizePermissions(parseStoredJson('permissions', [])),
  }),
  
  getters: {
    isAuthenticated: (state) => !!state.token,
    hasPermission: (state) => (key) => {
      if (state.user?.role === 'superadmin') return true;
      return normalizePermissions(state.permissions).includes(key);
    }
  },
  
  actions: {
    async login(credentials) {
      try {
        const response = await axios.post('/v1/login', credentials)
        this.setAuth(response.data.data)
        return response
      } catch (error) {
        throw error
      }
    },
    
    setAuth({ user, token, branch }) {
      this.user = user
      this.token = token
      this.branch = branch
      this.permissions = normalizePermissions(user?.permissions)
      localStorage.setItem('token', token)
      localStorage.setItem('user', JSON.stringify(user))
      localStorage.setItem('branch', JSON.stringify(branch))
      localStorage.setItem('permissions', JSON.stringify(this.permissions))
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
    },

    setUser(user) {
      this.user = user
      this.permissions = user?.permissions === undefined
        ? normalizePermissions(this.permissions)
        : normalizePermissions(user.permissions)
      localStorage.setItem('user', JSON.stringify(user))
      localStorage.setItem('permissions', JSON.stringify(this.permissions))
    },
    
    async logout() {
      try {
        await axios.post('/v1/logout')
      } catch (error) {
        console.error('Logout error', error)
      } finally {
        this.clearAuth()
      }
    },
    
    clearAuth() {
      this.user = null
      this.token = null
      this.branch = null
      this.permissions = []
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.removeItem('branch')
      localStorage.removeItem('permissions')
      delete axios.defaults.headers.common['Authorization']
    }
  }
})
