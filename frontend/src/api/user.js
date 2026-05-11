import api from './axios'

const prefix = '/v1'

export const getUsers = (params) => api.get(`${prefix}/users`, { params })
export const getUser = (id) => api.get(`${prefix}/users/${id}`)
export const createUser = (data) => api.post(`${prefix}/users`, data)
export const updateUser = (id, data) => api.put(`${prefix}/users/${id}`, data)
export const deleteUser = (id) => api.delete(`${prefix}/users/${id}`)
export const resetUserPassword = (id, data) => api.patch(`${prefix}/users/${id}/reset-password`, data)
export const getRoles = () => api.get(`${prefix}/roles`)
