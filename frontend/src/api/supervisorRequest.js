import api from './axios'

export const getSupervisorRequests = () => {
  return api.get('/v1/supervisor-requests')
}

export default {
  getSupervisorRequests,
}
