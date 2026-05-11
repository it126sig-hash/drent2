import { defineStore } from 'pinia'
import axios from '../api/axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user')) || null,
    token: localStorage.getItem('token') || null,
    branch: JSON.parse(localStorage.getItem('branch')) || null,
  }),
  
  getters: {
    isAuthenticated: (state) => !!state.token,
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
      localStorage.setItem('token', token)
      localStorage.setItem('user', JSON.stringify(user))
      localStorage.setItem('branch', JSON.stringify(branch))
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
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
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.removeItem('branch')
      delete axios.defaults.headers.common['Authorization']
    }
  }
})
