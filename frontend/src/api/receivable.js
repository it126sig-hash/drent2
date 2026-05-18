import api from './axios'

const prefix = '/v1/receivables'

export const getReceivables = (params) => {
  return api.get(prefix, { params })
}

export const getPaymentHistory = (params) => {
  return api.get(`${prefix}/payment-history`, { params })
}

export const generateInvoice = (payload) => {
  return api.post(`${prefix}/invoices`, payload)
}

export const getInvoices = (params) => {
  return api.get('/v1/invoices', { params })
}

export const markInvoiceSent = (invoiceId) => {
  return api.post(`/v1/invoices/${invoiceId}/mark-sent`)
}

export const refreshInvoiceAmount = (invoiceId, data = {}) => {
  return api.post(`/v1/invoices/${invoiceId}/refresh-amount`, data)
}

export const downloadInvoicePdf = (invoiceId) => {
  return api.get(`/v1/invoices/${invoiceId}/pdf`, { responseType: 'blob' })
}

export const addInvoicePayment = (invoiceId, data) => {
  return api.post(`/v1/invoices/${invoiceId}/payments`, data)
}

export const getPublicInvoice = (token) => {
  return api.get(`/v1/public/invoices/${token}`)
}

export default {
  getReceivables,
  getPaymentHistory,
  generateInvoice,
  getInvoices,
  markInvoiceSent,
  refreshInvoiceAmount,
  downloadInvoicePdf,
  addInvoicePayment,
  getPublicInvoice,
}
