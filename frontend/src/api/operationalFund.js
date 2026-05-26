import api from './axios'

const prefix = '/v1'

export const getOperationalBookings = (params) =>
  api.get(`${prefix}/operational-funds/bookings`, { params })

export const getOperationalFund = (id) =>
  api.get(`${prefix}/operational-funds/${id}`)

export const getOperationalHistory = (params) =>
  api.get(`${prefix}/operational-funds/history`, { params })

export const createOperationalFund = (bookingId, data) =>
  api.post(`${prefix}/bookings/${bookingId}/operational-funds`, data)

export const closeOperationalFund = (fundId, data) =>
  api.post(`${prefix}/operational-funds/${fundId}/close`, data)

export const acceptOperationalFund = (fundId) =>
  api.post(`${prefix}/operational-funds/${fundId}/accept`)

export const createOperationalExpense = (fundId, data) =>
  api.post(`${prefix}/operational-funds/${fundId}/expenses`, data, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })

export const createBookingExpense = (bookingId, data) =>
  api.post(`${prefix}/bookings/${bookingId}/expenses`, data, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })

export const approveOperationalExpense = (expenseId) =>
  api.post(`${prefix}/operational-expenses/${expenseId}/approve`)

export const rejectOperationalExpense = (expenseId, data) =>
  api.post(`${prefix}/operational-expenses/${expenseId}/reject`, data)

export const getOperationalExpensePhoto = (expenseId) =>
  api.get(`${prefix}/operational-expenses/${expenseId}/photo`, {
    responseType: 'blob',
  })

export const getDriverOperationalFunds = (params) =>
  api.get(`${prefix}/driver/operational-funds`, { params })

export const getDriverSchedules = (params) =>
  api.get(`${prefix}/driver/schedules`, { params })

export const markOperationalComplete = (bookingId) =>
  api.post(`${prefix}/bookings/${bookingId}/operational-complete`)

export const revertOperationalActive = (bookingId, data) =>
  api.post(`${prefix}/bookings/${bookingId}/operational-revert`, data)

export const voidOperationalFund = (fundId, data) =>
  api.post(`${prefix}/operational-funds/${fundId}/void`, data)

export const voidOperationalExpense = (expenseId, data) =>
  api.post(`${prefix}/operational-expenses/${expenseId}/void`, data)

export const approveVoidOperationalExpense = (expenseId) =>
  api.post(`${prefix}/operational-expenses/${expenseId}/approve-void`)

export const rejectVoidOperationalExpense = (expenseId, data) =>
  api.post(`${prefix}/operational-expenses/${expenseId}/reject-void`, data)

export default {
  getOperationalBookings,
  getOperationalFund,
  getOperationalHistory,
  createOperationalFund,
  closeOperationalFund,
  acceptOperationalFund,
  createOperationalExpense,
  createBookingExpense,
  approveOperationalExpense,
  rejectOperationalExpense,
  getOperationalExpensePhoto,
  getDriverOperationalFunds,
  getDriverSchedules,
  markOperationalComplete,
  revertOperationalActive,
  voidOperationalFund,
  voidOperationalExpense,
  approveVoidOperationalExpense,
  rejectVoidOperationalExpense,
}
