import axios from './axios'

export const getRolePermissions = () => {
  return axios.get('/v1/role-permissions')
}

export const updateRolePermissions = (role, permissions) => {
  return axios.put(`/v1/role-permissions/${role}`, { permissions })
}

export const getUserPermissions = (userId) => {
  return axios.get(`/v1/users/${userId}/permissions`)
}

export const updateUserPermissions = (userId, overrides) => {
  return axios.put(`/v1/users/${userId}/permissions`, { overrides })
}
