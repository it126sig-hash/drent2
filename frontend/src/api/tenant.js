import api from './axios'

const prefix = '/v1/tenant'

export const fetchTenant = () => api.get(prefix)

export const updateTenant = (formData) =>
  api.post(prefix, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })

export default { fetchTenant, updateTenant }
