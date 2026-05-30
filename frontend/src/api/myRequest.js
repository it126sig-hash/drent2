import api from './axios'

export const getMyRequests = () => {
  return api.get('/v1/my-requests')
}

export default {
  getMyRequests,
}
