import axios from './axios'

const prefix = '/v1/cost-types'

export const getCostTypes = (params) => axios.get(prefix, { params })
export const getCostType = (id) => axios.get(`${prefix}/${id}`)
export const createCostType = (data) => axios.post(prefix, data)
export const updateCostType = (id, data) => axios.put(`${prefix}/${id}`, data)
export const deleteCostType = (id) => axios.delete(`${prefix}/${id}`)

export default { getCostTypes, getCostType, createCostType, updateCostType, deleteCostType }
