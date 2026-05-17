import api from './axios'

const prefix = '/v1/rent-to-rent'

export const getRentToRentDebts = (params) => api.get(prefix, { params })

export const getRentToRentDebt = (debtId) => api.get(`${prefix}/${debtId}`)

export const updateRentToRentDebtAmount = (debtId, data) => api.patch(`${prefix}/${debtId}/amount`, data)

export const getRentToRentBills = (params) => api.get(`${prefix}/bills`, { params })

export const getRentToRentBill = (billId) => api.get(`${prefix}/bills/${billId}`)

export const generateRentToRentBill = (payload) => api.post(`${prefix}/bills`, payload)

export const markRentToRentBillSent = (billId) => api.post(`${prefix}/bills/${billId}/mark-sent`)

export const addRentToRentPayment = (billId, data) => api.post(`${prefix}/bills/${billId}/payments`, data)

export const requestVoidRentToRentBill = (billId, data) => api.post(`${prefix}/bills/${billId}/request-void`, data)

export const approveVoidRentToRentBill = (billId) => api.post(`${prefix}/bills/${billId}/approve-void`)

export const rejectVoidRentToRentBill = (billId, data) => api.post(`${prefix}/bills/${billId}/reject-void`, data)

export const downloadRentToRentBillPdf = (billId) => api.get(`${prefix}/bills/${billId}/pdf`, { responseType: 'blob' })

export const getRentToRentPaymentHistory = (params) => api.get(`${prefix}/payment-history`, { params })

export const getPublicRentToRentBill = (token) => api.get(`/v1/public/rent-to-rent-bills/${token}`)

export const downloadPublicRentToRentBillPdf = (token) => api.get(`/v1/public/rent-to-rent-bills/${token}/pdf`, { responseType: 'blob' })

export default {
  getRentToRentDebts,
  getRentToRentDebt,
  updateRentToRentDebtAmount,
  getRentToRentBills,
  getRentToRentBill,
  generateRentToRentBill,
  markRentToRentBillSent,
  addRentToRentPayment,
  requestVoidRentToRentBill,
  approveVoidRentToRentBill,
  rejectVoidRentToRentBill,
  downloadRentToRentBillPdf,
  getRentToRentPaymentHistory,
  getPublicRentToRentBill,
  downloadPublicRentToRentBillPdf,
}
