import { ref } from 'vue'
import financeCategoryApi from '../api/financeCategory'

export function useFinanceCategory() {
  const categories = ref([])
  const loading = ref(false)
  const pagination = ref({ current_page: 1, per_page: 100, total: 0 })

  const fetchAll = async (params = {}) => {
    loading.value = true
    try {
      const response = await financeCategoryApi.getFinanceCategories({
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        ...params,
      })
      categories.value = response.data.data || []
      if (response.data.meta) {
        pagination.value.current_page = response.data.meta.current_page || pagination.value.current_page
        pagination.value.per_page = response.data.meta.per_page || pagination.value.per_page
        pagination.value.total = response.data.meta.total || 0
      }
      return categories.value
    } finally {
      loading.value = false
    }
  }

  const store = async (data) => {
    loading.value = true
    try {
      const response = await financeCategoryApi.createFinanceCategory(data)
      await fetchAll()
      return response.data.data
    } finally {
      loading.value = false
    }
  }

  const update = async (id, data) => {
    loading.value = true
    try {
      const response = await financeCategoryApi.updateFinanceCategory(id, data)
      await fetchAll()
      return response.data.data
    } finally {
      loading.value = false
    }
  }

  const remove = async (id) => {
    loading.value = true
    try {
      await financeCategoryApi.deleteFinanceCategory(id)
      await fetchAll()
    } finally {
      loading.value = false
    }
  }

  return { categories, loading, pagination, fetchAll, store, update, remove }
}
