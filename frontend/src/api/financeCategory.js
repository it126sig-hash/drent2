import axios from './axios'

const prefix = '/v1/finance-categories'

export const getFinanceCategories = (params = {}) => axios.get(prefix, { params })
export const getFinanceCategory = (id) => axios.get(`${prefix}/${id}`)
export const createFinanceCategory = (data) => axios.post(prefix, data)
export const updateFinanceCategory = (id, data) => axios.put(`${prefix}/${id}`, data)
export const deleteFinanceCategory = (id) => axios.delete(`${prefix}/${id}`)

export default {
  getFinanceCategories,
  getFinanceCategory,
  createFinanceCategory,
  updateFinanceCategory,
  deleteFinanceCategory,
}
