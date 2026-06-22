import api from './axios'

const prefix = '/v1'

export const getFleetCards = (params) =>
  api.get(`${prefix}/fleet-cards`, { params })

export const createFleetCard = (data) =>
  api.post(`${prefix}/fleet-cards`, data)

export const updateFleetCardStatus = (cardId, data) =>
  api.post(`${prefix}/fleet-cards/${cardId}/status`, data)

export const getFleetCardBookingDetails = (params) =>
  api.get(`${prefix}/fleet-card-funds/booking-details`, { params })

export const getFleetCardFund = (id) =>
  api.get(`${prefix}/fleet-card-funds/${id}`)

export const getFleetCardHistory = (params) =>
  api.get(`${prefix}/fleet-card-funds/history`, { params })

export const createFleetCardFund = (bookingDetailId, data) =>
  api.post(`${prefix}/booking-details/${bookingDetailId}/fleet-card-funds`, data)

export const closeFleetCardFund = (fundId, data) =>
  api.post(`${prefix}/fleet-card-funds/${fundId}/close`, data)

export const acceptFleetCardFund = (fundId) =>
  api.post(`${prefix}/fleet-card-funds/${fundId}/accept`)

export const createFleetCardExpense = (fundId, data) =>
  api.post(`${prefix}/fleet-card-funds/${fundId}/expenses`, data, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })

export const approveFleetCardExpense = (expenseId) =>
  api.post(`${prefix}/fleet-card-expenses/${expenseId}/approve`)

export const rejectFleetCardExpense = (expenseId, data) =>
  api.post(`${prefix}/fleet-card-expenses/${expenseId}/reject`, data)

export const getFleetCardExpensePhoto = (expenseId) =>
  api.get(`${prefix}/fleet-card-expenses/${expenseId}/photo`, {
    responseType: 'blob',
  })

export const getDriverFleetCardFunds = (params) =>
  api.get(`${prefix}/driver/fleet-card-funds`, { params })

export const voidFleetCardFund = (fundId, data) =>
  api.post(`${prefix}/fleet-card-funds/${fundId}/void`, data)

export const approveVoidFleetCardFund = (fundId) =>
  api.post(`${prefix}/fleet-card-funds/${fundId}/approve-void`)

export const rejectVoidFleetCardFund = (fundId, data) =>
  api.post(`${prefix}/fleet-card-funds/${fundId}/reject-void`, data)

export const voidFleetCardExpense = (expenseId, data) =>
  api.post(`${prefix}/fleet-card-expenses/${expenseId}/void`, data)

export const approveVoidFleetCardExpense = (expenseId) =>
  api.post(`${prefix}/fleet-card-expenses/${expenseId}/approve-void`)

export const rejectVoidFleetCardExpense = (expenseId, data) =>
  api.post(`${prefix}/fleet-card-expenses/${expenseId}/reject-void`, data)

export default {
  getFleetCards,
  createFleetCard,
  updateFleetCardStatus,
  getFleetCardBookingDetails,
  getFleetCardFund,
  getFleetCardHistory,
  createFleetCardFund,
  closeFleetCardFund,
  acceptFleetCardFund,
  createFleetCardExpense,
  approveFleetCardExpense,
  rejectFleetCardExpense,
  getFleetCardExpensePhoto,
  getDriverFleetCardFunds,
  voidFleetCardFund,
  approveVoidFleetCardFund,
  rejectVoidFleetCardFund,
  voidFleetCardExpense,
  approveVoidFleetCardExpense,
  rejectVoidFleetCardExpense,
}
