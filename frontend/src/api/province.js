import api from './axios'

const prefix = '/v1/provinces'

export const fetchProvinces = (params) => api.get(prefix, { params })

export default { fetchProvinces }
