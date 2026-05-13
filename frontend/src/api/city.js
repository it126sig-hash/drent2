import api from './axios'

const prefix = '/v1/cities'

export const fetchCities = (params) => api.get(prefix, { params })
export const fetchCity = (id) => api.get(`${prefix}/${id}`)
export const createCity = (data) => api.post(prefix, data)
export const updateCity = (id, data) => api.put(`${prefix}/${id}`, data)
export const deleteCity = (id) => api.delete(`${prefix}/${id}`)

export default { fetchCities, fetchCity, createCity, updateCity, deleteCity }
