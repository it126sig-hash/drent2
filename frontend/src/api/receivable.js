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

export const mergeInvoices = (payload) => {
  return api.post('/v1/invoices/merge', payload)
}

export const getInvoices = (params) => {
  return api.get('/v1/invoices', { params })
}

export const getInvoice = (invoiceId) => {
  return api.get(`/v1/invoices/${invoiceId}`)
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

export const getPublicBookingProgress = (token) => {
  return api.get(`/v1/public/bookings/${token}`)
}

export const getPublicBookingInvoices = (token) => {
  return api.get(`/v1/public/bookings/${token}/invoices`)
}

export const getInvoiceHistories = (invoiceId) => {
  return api.get(`/v1/invoices/${invoiceId}/histories`)
}

export const getBookingInvoiceHistories = (bookingId) => {
  return api.get(`/v1/bookings/${bookingId}/invoice-histories`)
}

export const requestVoidInvoicePayment = (paymentId, data) => {
  return api.post(`/v1/invoice-payments/${paymentId}/request-void`, data)
}

export const approveVoidInvoicePayment = (paymentId) => {
  return api.post(`/v1/invoice-payments/${paymentId}/approve-void`)
}

export const rejectVoidInvoicePayment = (paymentId, data = {}) => {
  return api.post(`/v1/invoice-payments/${paymentId}/reject-void`, data)
}

export const requestVoidInvoice = (invoiceId, data) => {
  return api.post(`/v1/invoices/${invoiceId}/request-void`, data)
}

export const approveVoidInvoice = (invoiceId) => {
  return api.post(`/v1/invoices/${invoiceId}/approve-void`)
}

export const rejectVoidInvoice = (invoiceId, data = {}) => {
  return api.post(`/v1/invoices/${invoiceId}/reject-void`, data)
}

export default {
  getReceivables,
  getPaymentHistory,
  generateInvoice,
  mergeInvoices,
  getInvoices,
  getInvoice,
  markInvoiceSent,
  refreshInvoiceAmount,
  downloadInvoicePdf,
  addInvoicePayment,
  getPublicInvoice,
  getPublicBookingProgress,
  getPublicBookingInvoices,
  getInvoiceHistories,
  getBookingInvoiceHistories,
  requestVoidInvoicePayment,
  approveVoidInvoicePayment,
  rejectVoidInvoicePayment,
  requestVoidInvoice,
  approveVoidInvoice,
  rejectVoidInvoice,
}
