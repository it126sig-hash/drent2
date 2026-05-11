import axios from 'axios'

const instance = axios.create({
  baseURL: 'http://localhost/drent-vibe/backend/public/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  }
})

// Add a request interceptor
instance.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Add a response interceptor
instance.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      // Clear auth and redirect to login if unauthorized
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.removeItem('branch')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default instance
