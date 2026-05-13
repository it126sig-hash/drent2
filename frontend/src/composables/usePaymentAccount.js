import { ref } from 'vue'
import { getPaymentAccounts, createPaymentAccount, updatePaymentAccount, deletePaymentAccount } from '../api/paymentAccount'

export function usePaymentAccount() {
  const accounts = ref([])
  const loading = ref(false)
  const pagination = ref({ current_page: 1, per_page: 15, total: 0 })

  const fetchAll = async (params = {}) => {
    loading.value = true
    try {
      const res = await getPaymentAccounts({ page: pagination.value.current_page, per_page: pagination.value.per_page, ...params })
      accounts.value = res.data.data
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
      await createPaymentAccount(data)
      await fetchAll()
    } finally {
      loading.value = false
    }
  }

  const update = async (id, data) => {
    loading.value = true
    try {
      await updatePaymentAccount(id, data)
      await fetchAll()
    } finally {
      loading.value = false
    }
  }

  const remove = async (id) => {
    loading.value = true
    try {
      await deletePaymentAccount(id)
      await fetchAll()
    } finally {
      loading.value = false
    }
  }

  return { accounts, loading, pagination, fetchAll, store, update, remove }
}
