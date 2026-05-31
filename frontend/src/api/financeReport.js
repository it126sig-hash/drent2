import axios from './axios'

const prefix = '/v1/reports'

export const getMonthlyFinanceReport = (params = {}) => axios.get(`${prefix}/monthly-finance`, { params })

export const getUnitUsageReport = (params = {}) => axios.get(`${prefix}/unit-usage`, { params })

export const getDriverUsageReport = (params = {}) => axios.get(`${prefix}/driver-usage`, { params })

export const getCustomerUsageReport = (params = {}) => axios.get(`${prefix}/customer-usage`, { params })

export default {
  getMonthlyFinanceReport,
  getUnitUsageReport,
  getDriverUsageReport,
  getCustomerUsageReport,
}
