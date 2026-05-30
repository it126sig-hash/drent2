import api from './axios'

const prefix = '/v1/branches'

export const fetchBranches = (params) => api.get(prefix, { params })
export const fetchBranch = (id) => api.get(`${prefix}/${id}`)

export const createBranch = (formData) =>
  api.post(prefix, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })

export const updateBranch = (id, formData) =>
  api.post(`${prefix}/${id}`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })

export const deleteBranch = (id) => api.delete(`${prefix}/${id}`)

export default {
  fetchBranches,
  fetchBranch,
  createBranch,
  updateBranch,
  deleteBranch,
}
