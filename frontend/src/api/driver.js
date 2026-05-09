import api from './axios'

const prefix = '/v1/drivers'

export const fetchDrivers = (params) => api.get(prefix, { params })
export const fetchDriver = (id) => api.get(`${prefix}/${id}`)
export const createDriver = (data) => api.post(prefix, data)
export const updateDriver = (id, data) => api.put(`${prefix}/${id}`, data)
export const deleteDriver = (id) => api.delete(`${prefix}/${id}`)
export const updateDriverBalance = (id, saldo) =>
  api.patch(`${prefix}/${id}/balance`, { saldo })
