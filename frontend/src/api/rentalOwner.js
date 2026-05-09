import axios from './axios'

const prefix = '/v1/rental-owners'

export const getRentalOwners = (params) => {
  return axios.get(prefix, { params })
}

export const getRentalOwner = (id) => {
  return axios.get(`${prefix}/${id}`)
}

export const createRentalOwner = (data) => {
  return axios.post(prefix, data)
}

export const updateRentalOwner = (id, data) => {
  return axios.put(`${prefix}/${id}`, data)
}

export const deleteRentalOwner = (id) => {
  return axios.delete(`${prefix}/${id}`)
}

export default {
  getRentalOwners,
  getRentalOwner,
  createRentalOwner,
  updateRentalOwner,
  deleteRentalOwner
}
