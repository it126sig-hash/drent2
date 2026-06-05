import { ref } from 'vue'
import cancellationApi from '../api/cancellation'
import { useToast } from 'primevue/usetoast'

export function useCancellation() {
  const toast = useToast()
  const cancellations = ref([])
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({
    total: 0,
    per_page: 15,
    current_page: 1,
    last_page: 1,
  })

  const fetchCancellations = async (params = {}) => {
    loading.value = true
    error.value = null
    try {
      const response = await cancellationApi.getCancellations(params)
      cancellations.value = response.data.data
      if (response.data.meta) {
        pagination.value = {
          total: response.data.meta.total,
          per_page: response.data.meta.per_page,
          current_page: response.data.meta.current_page,
          last_page: response.data.meta.last_page,
        }
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memuat data pembatalan'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
    } finally {
      loading.value = false
    }
  }

  const payRefund = async (id, data) => {
    loading.value = true
    try {
      const response = await cancellationApi.payRefund(id, data)
      toast?.add({ severity: 'success', summary: 'Sukses', detail: 'Refund berhasil dibayar', life: 3000 })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memproses pembayaran refund'
      toast?.add({ severity: 'error', summary: 'Error', detail: error.value, life: 5000 })
      throw err
    } finally {
      loading.value = false
    }
  }

  return { cancellations, loading, error, pagination, fetchCancellations, payRefund }
}
