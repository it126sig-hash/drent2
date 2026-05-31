import axios from './axios'

const prefix = '/v1/pricing-packages'

export const getPricingPackages = (params) => axios.get(prefix, { params })
export const getPricingPackage = (id) => axios.get(`${prefix}/${id}`)
export const createPricingPackage = (data) => axios.post(prefix, data)
export const updatePricingPackage = (id, data) => axios.put(`${prefix}/${id}`, data)
export const deletePricingPackage = (id) => axios.delete(`${prefix}/${id}`)
export const downloadImportTemplate = () => axios.get(`${prefix}/import-template`, { responseType: 'blob' })
export const importPricingPackages = (file) => {
  const form = new FormData()
  form.append('file', file)
  return axios.post(`${prefix}/import`, form, { headers: { 'Content-Type': 'multipart/form-data' } })
}

export default { getPricingPackages, getPricingPackage, createPricingPackage, updatePricingPackage, deletePricingPackage, downloadImportTemplate, importPricingPackages }
