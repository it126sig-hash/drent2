import axios from 'axios';

export default {
  createBooking(data) {
    return axios.post('/v1/bookings', data);
  },
  getBookings(params) {
    return axios.get('/v1/bookings', { params });
  },
  getBooking(id) {
    return axios.get(`/v1/bookings/${id}`);
  }
};
