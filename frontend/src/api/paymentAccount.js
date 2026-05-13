import axios from './axios'

const prefix = '/v1/payment-accounts'

export const getPaymentAccounts = (params) => axios.get(prefix, { params })
export const getPaymentAccount = (id) => axios.get(`${prefix}/${id}`)
export const createPaymentAccount = (data) => axios.post(prefix, data)
export const updatePaymentAccount = (id, data) => axios.put(`${prefix}/${id}`, data)
export const deletePaymentAccount = (id) => axios.delete(`${prefix}/${id}`)

export default { getPaymentAccounts, getPaymentAccount, createPaymentAccount, updatePaymentAccount, deletePaymentAccount }
