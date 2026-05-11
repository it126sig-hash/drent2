import api from './axios';

const prefix = '/v1/bookings';

export const createBooking = (data) => {
  return api.post(prefix, data);
};

export const getBookings = (params) => {
  return api.get(prefix, { params });
};

export const getBooking = (id) => {
  return api.get(`${prefix}/${id}`);
};

export const updateBookingStatus = (id, payload) => {
  return api.patch(`${prefix}/${id}/status`, payload);
};

export const handleBooking = (id, data) => {
  return api.patch(`${prefix}/${id}/handle`, data);
};

export const checkoutBooking = (id, data = {}) => {
  return api.post(`${prefix}/${id}/checkout`, data);
};

export const completeBooking = (id, data = {}) => {
  return api.post(`${prefix}/${id}/complete`, data);
};

export const cancelBooking = (id, data = {}) => {
  return api.post(`${prefix}/${id}/cancel`, data);
};

export const getBookingPayments = (bookingId) => {
  return api.get(`${prefix}/${bookingId}/payments`);
};

export const addBookingPayment = (bookingId, data) => {
  return api.post(`${prefix}/${bookingId}/payments`, data);
};

export const addBookingDetail = (bookingId, data) => {
  return api.post(`${prefix}/${bookingId}/details`, data);
};

export const addBookingCost = (detailId, data) => {
  return api.post(`/v1/booking-details/${detailId}/costs`, data);
};

export const extendBooking = (bookingId, data) => {
  return api.post(`${prefix}/${bookingId}/extend`, data);
};

export const rollingBooking = (bookingId, data) => {
  return api.post(`${prefix}/${bookingId}/rolling`, data);
};

export const stopEarlyBooking = (bookingId, data) => {
  return api.post(`${prefix}/${bookingId}/stop-early`, data);
};

export const addAdditionalCost = (bookingId, data) => {
  return api.post(`${prefix}/${bookingId}/costs`, data);
};

export const updateBookingDetail = (detailId, data) => {
  return api.patch(`/v1/booking-details/${detailId}`, data);
};

export const updateBookingCost = (costId, data) => {
  return api.patch(`/v1/booking-costs/${costId}`, data);
};

export default {
  createBooking,
  getBookings,
  getBooking,
  updateBookingStatus,
  handleBooking,
  checkoutBooking,
  completeBooking,
  cancelBooking,
  getBookingPayments,
  addBookingPayment,
  addBookingDetail,
  addBookingCost,
  extendBooking,
  rollingBooking,
  stopEarlyBooking,
  addAdditionalCost,
  updateBookingDetail,
  updateBookingCost,
};
