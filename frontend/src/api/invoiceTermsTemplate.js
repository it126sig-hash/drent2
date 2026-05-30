import api from './axios'

const prefix = '/v1/invoice-terms-templates'

export const getInvoiceTermsTemplates = () => api.get(prefix)

export const createInvoiceTermsTemplate = (data) => api.post(prefix, data)

export const updateInvoiceTermsTemplate = (id, data) => api.put(`${prefix}/${id}`, data)

export const deleteInvoiceTermsTemplate = (id) => api.delete(`${prefix}/${id}`)

export default {
  getInvoiceTermsTemplates,
  createInvoiceTermsTemplate,
  updateInvoiceTermsTemplate,
  deleteInvoiceTermsTemplate,
}
