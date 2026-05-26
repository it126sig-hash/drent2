import { ref } from 'vue'
import transactionApi from '../api/paymentAccountTransaction'

export function usePaymentAccountTransaction() {
  const transactions = ref([])
  const loading = ref(false)
  const actionLoading = ref(false)
  const pagination = ref({ current_page: 1, per_page: 15, total: 0 })

  const fetchAll = async (params = {}) => {
    loading.value = true
    try {
      const response = await transactionApi.getPaymentAccountTransactions({
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        ...params,
      })
      transactions.value = response.data.data || []
      if (response.data.meta) {
        pagination.value.current_page = response.data.meta.current_page || pagination.value.current_page
        pagination.value.per_page = response.data.meta.per_page || pagination.value.per_page
        pagination.value.total = response.data.meta.total || 0
      }
      return transactions.value
    } finally {
      loading.value = false
    }
  }

  const transfer = async (data) => {
    actionLoading.value = true
    try {
      const response = await transactionApi.createTransfer(data)
      return response.data.data
    } finally {
      actionLoading.value = false
    }
  }

  const other = async (data) => {
    actionLoading.value = true
    try {
      const response = await transactionApi.createOtherTransaction(data)
      return response.data.data
    } finally {
      actionLoading.value = false
    }
  }

  const adjust = async (data) => {
    actionLoading.value = true
    try {
      const response = await transactionApi.createAdjustment(data)
      return response.data.data
    } finally {
      actionLoading.value = false
    }
  }

  return { transactions, loading, actionLoading, pagination, fetchAll, transfer, other, adjust }
}
