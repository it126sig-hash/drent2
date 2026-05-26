import api from './axios'

const prefix = '/v1/transactions'

export const getTransactions = (params) => api.get(prefix, { params })

export const getTransactionDetail = (id) => api.get(`${prefix}/${id}`)

export default {
  getTransactions,
  getTransactionDetail,
}
