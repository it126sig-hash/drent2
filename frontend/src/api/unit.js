import axios from './axios'

const prefix = '/v1/units'

export const getUnits = (params) => {
  return axios.get(prefix, { params })
}

export const getUnit = (id) => {
  return axios.get(`${prefix}/${id}`)
}

export const createUnit = (data) => {
  return axios.post(prefix, data)
}

export const updateUnit = (id, data) => {
  return axios.put(`${prefix}/${id}`, data)
}

export const deleteUnit = (id) => {
  return axios.delete(`${prefix}/${id}`)
}

export const uploadUnitPhoto = (id, formData) => {
  return axios.post(`${prefix}/${id}/photos`, formData, {
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

export const deleteUnitPhoto = (unitId, photoId) => {
  return axios.delete(`${prefix}/${unitId}/photos/${photoId}`)
}

export const batchUpdateUnitCity = (data) => {
  return axios.post(`${prefix}/batch-update-city`, data)
}

export const checkUnitSchedule = (id, params) => {
  return axios.get(`${prefix}/${id}/schedule-check`, { params })
}

export default {
  getUnits,
  getUnit,
  createUnit,
  updateUnit,
  deleteUnit,
  uploadUnitPhoto,
  deleteUnitPhoto,
  batchUpdateUnitCity,
  checkUnitSchedule
}
