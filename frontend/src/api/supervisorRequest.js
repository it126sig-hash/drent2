import api from './axios'

export const getSupervisorRequests = (params) => {
  return api.get('/v1/supervisor-requests', { params })
}

export default {
  getSupervisorRequests,
}
