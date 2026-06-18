import api from './axios';

const prefix = '/v1/booking-cancellations';

export const getCancellations = (params) => api.get(prefix, { params });

export const getCancellation = (id) => api.get(`${prefix}/${id}`);

export const updateCancellation = (id, data) => api.put(`${prefix}/${id}`, data);

export const payRefund = (id, data) => api.post(`${prefix}/${id}/pay-refund`, data);

export default { getCancellations, getCancellation, updateCancellation, payRefund };
