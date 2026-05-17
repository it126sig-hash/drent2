import api from './axios'

const prefix = '/v1/dashboard'

export const getDashboard = (params = {}) => api.get(prefix, { params })

export default {
  getDashboard,
}
