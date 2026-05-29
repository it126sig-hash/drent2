import api from './axios'

const prefix = '/v1'

export const getProfile = () => api.get(`${prefix}/profile`)
export const updateProfile = (data) => api.post(`${prefix}/profile`, data, {
  headers: { 'Content-Type': 'multipart/form-data' },
})
export const updateProfilePassword = (data) => api.patch(`${prefix}/profile/password`, data)
