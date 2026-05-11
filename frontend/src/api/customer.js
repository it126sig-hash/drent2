import api from './axios'

const prefix = '/v1/customers'

export const fetchCustomers = (params) => api.get(prefix, { params })
export const fetchCustomer = (id) => api.get(`${prefix}/${id}`)
export const createCustomer = (data) => api.post(prefix, data)
export const updateCustomer = (id, data) => api.put(`${prefix}/${id}`, data)
export const deleteCustomer = (id) => api.delete(`${prefix}/${id}`)
