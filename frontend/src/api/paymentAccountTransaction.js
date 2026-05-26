import axios from './axios'

const prefix = '/v1/payment-account-transactions'

export const getPaymentAccountTransactions = (params = {}) => axios.get(prefix, { params })
export const createTransfer = (data) => axios.post(`${prefix}/transfer`, data)
export const createOtherTransaction = (data) => axios.post(`${prefix}/other`, data)
export const createAdjustment = (data) => axios.post(`${prefix}/adjust`, data)

export default {
  getPaymentAccountTransactions,
  createTransfer,
  createOtherTransaction,
  createAdjustment,
}
