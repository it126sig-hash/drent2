import { ref } from 'vue'
import { getCostTypes, createCostType, updateCostType, deleteCostType } from '../api/costType'

export function useCostType() {
  const costTypes = ref([])
  const loading = ref(false)
  const pagination = ref({ current_page: 1, per_page: 15, total: 0 })

  const fetchAll = async (params = {}) => {
    loading.value = true
    try {
      const res = await getCostTypes({ page: pagination.value.current_page, per_page: pagination.value.per_page, ...params })
      costTypes.value = res.data.data
      if (res.data.meta) {
        pagination.value.total = res.data.meta.total
        pagination.value.per_page = res.data.meta.per_page
      }
    } finally {
      loading.value = false
    }
  }

  const store = async (data) => {
    loading.value = true
    try {
      await createCostType(data)
      await fetchAll()
    } finally {
      loading.value = false
    }
  }

  const update = async (id, data) => {
    loading.value = true
    try {
      await updateCostType(id, data)
      await fetchAll()
    } finally {
      loading.value = false
    }
  }

  const remove = async (id) => {
    loading.value = true
    try {
      await deleteCostType(id)
      await fetchAll()
    } finally {
      loading.value = false
    }
  }

  return { costTypes, loading, pagination, fetchAll, store, update, remove }
}
