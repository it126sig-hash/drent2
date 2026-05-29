import { defineStore } from 'pinia'
import axios from '../api/axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user')) || null,
    token: localStorage.getItem('token') || null,
    branch: JSON.parse(localStorage.getItem('branch')) || null,
    permissions: JSON.parse(localStorage.getItem('permissions')) || [],
  }),
  
  getters: {
    isAuthenticated: (state) => !!state.token,
    hasPermission: (state) => (key) => {
      if (state.user?.role === 'superadmin') return true;
      return state.permissions.includes(key);
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
      // Guard: ensure permissions is always a plain array even if the API
      // returns null, undefined, or an unexpected shape (e.g., due to a backend bug).
      this.permissions = Array.isArray(user.permissions) ? user.permissions : []
      localStorage.setItem('token', token)
      localStorage.setItem('user', JSON.stringify(user))
      localStorage.setItem('branch', JSON.stringify(branch))
      localStorage.setItem('permissions', JSON.stringify(this.permissions))
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
    },

    setUser(user) {
      this.user = user
      this.permissions = Array.isArray(user.permissions) ? user.permissions : this.permissions
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
