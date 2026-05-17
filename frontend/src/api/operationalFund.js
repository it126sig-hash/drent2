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

export default {
  getOperationalBookings,
  getOperationalFund,
  getOperationalHistory,
  createOperationalFund,
  closeOperationalFund,
  acceptOperationalFund,
  createOperationalExpense,
  approveOperationalExpense,
  rejectOperationalExpense,
  getOperationalExpensePhoto,
  getDriverOperationalFunds,
  getDriverSchedules,
}
