import { ref } from 'vue'
import financeReportApi from '../api/financeReport'

export function useMonthlyFinanceReport() {
  const report = ref(null)
  const loading = ref(false)
  const error = ref(null)

  const fetchReport = async (params = {}) => {
    loading.value = true
    error.value = null
    try {
      const response = await financeReportApi.getMonthlyFinanceReport(params)
      report.value = response.data.data
      return report.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Gagal memuat laporan bulanan'
      throw err
    } finally {
      loading.value = false
    }
  }

  return { report, loading, error, fetchReport }
}
