import api from './axios';

const prefix = '/v1/booking-cancellations';

export const getCancellations = (params) => api.get(prefix, { params });

export const getCancellation = (id) => api.get(`${prefix}/${id}`);

export const payRefund = (id, data) => api.post(`${prefix}/${id}/pay-refund`, data);

export default { getCancellations, getCancellation, payRefund };
