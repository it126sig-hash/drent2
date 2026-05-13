import api from './axios'

const prefix = '/v1/physical-checks'

export const getPhysicalCheckBookings = (params = {}) => {
  return api.get(`${prefix}/bookings`, { params })
}

export const getPhysicalCheckItems = () => {
  return api.get('/v1/physical-check-items')
}

export const requestPhysicalCheck = (bookingId, type) => {
  return api.post(`${prefix}/request`, { booking_id: bookingId, type })
}

export const getPhysicalCheckByBooking = (bookingId, type) => {
  return api.get(`/v1/bookings/${bookingId}/physical-checks/${type}`)
}

export const getPhysicalCheck = (id) => {
  return api.get(`${prefix}/${id}`)
}

export const storePhysicalCheck = (data) => {
  return api.post(prefix, data)
}

export default {
  getPhysicalCheckBookings,
  getPhysicalCheckItems,
  requestPhysicalCheck,
  getPhysicalCheckByBooking,
  getPhysicalCheck,
  storePhysicalCheck,
}
