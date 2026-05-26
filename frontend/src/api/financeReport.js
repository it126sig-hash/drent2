import axios from './axios'

const prefix = '/v1/reports'

export const getMonthlyFinanceReport = (params = {}) => axios.get(`${prefix}/monthly-finance`, { params })

export default {
  getMonthlyFinanceReport,
}
