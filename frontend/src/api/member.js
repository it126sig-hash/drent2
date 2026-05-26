import axios from './axios';

const prefix = '/v1/members';

export default {
    list(params) {
        return axios.get(prefix, { params });
    },
    get(id) {
        return axios.get(`${prefix}/${id}`);
    },
    create(formData) {
        return axios.post(prefix, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
    },
    update(id, formData) {
        // Use POST with _method=PUT for multipart/form-data updates in Laravel
        formData.append('_method', 'PUT');
        return axios.post(`${prefix}/${id}`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
    },
    activate(id) {
        return axios.patch(`${prefix}/${id}/activate`);
    },
    updateStatus(id, status) {
        return axios.patch(`${prefix}/${id}/status`, { status_member: status });
    },
    extend(id, data) {
        return axios.post(`${prefix}/${id}/extend`, data);
    },
    getExtensions(id) {
        return axios.get(`${prefix}/${id}/extensions`);
    },
    getDocumentUrl(id, type) {
        // This returns the API endpoint URL for the document
        return `${import.meta.env.VITE_API_URL}/v1/members/${id}/documents/${type}`;
    }
};
