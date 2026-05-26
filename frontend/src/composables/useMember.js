import { ref } from 'vue'
import memberApi from '../api/member'

export const useMember = () => {
    const members = ref([])
    const member = ref(null)
    const extensions = ref([])
    const loading = ref(false)
    const error = ref(null)
    const pagination = ref({
        total: 0,
        per_page: 15,
        current_page: 1,
        last_page: 1
    })

    const fetchAll = async (params = {}) => {
        loading.value = true
        error.value = null
        try {
            const response = await memberApi.list({
                page: pagination.value.current_page,
                per_page: pagination.value.per_page,
                ...params
            })
            members.value = response.data.data
            pagination.value = {
                total: response.data.meta?.total || response.data.total,
                per_page: response.data.meta?.per_page || response.data.per_page,
                current_page: response.data.meta?.current_page || response.data.current_page,
                last_page: response.data.meta?.last_page || response.data.last_page
            }
        } catch (err) {
            error.value = err.response?.data?.message || 'Gagal memuat data member'
            throw err
        } finally {
            loading.value = false
        }
    }

    const fetchDetail = async (id) => {
        loading.value = true
        error.value = null
        try {
            const response = await memberApi.get(id)
            member.value = response.data.data
            return response.data.data
        } catch (err) {
            error.value = err.response?.data?.message || 'Gagal memuat detail member'
            throw err
        } finally {
            loading.value = false
        }
    }

    const store = async (data) => {
        loading.value = true
        error.value = null
        try {
            const response = await memberApi.create(data)
            return response.data.data
        } catch (err) {
            error.value = err.response?.data?.message || 'Gagal menyimpan data member'
            throw err
        } finally {
            loading.value = false
        }
    }

    const update = async (id, data) => {
        loading.value = true
        error.value = null
        try {
            const response = await memberApi.update(id, data)
            return response.data.data
        } catch (err) {
            error.value = err.response?.data?.message || 'Gagal memperbarui data member'
            throw err
        } finally {
            loading.value = false
        }
    }

    const activate = async (id) => {
        loading.value = true
        error.value = null
        try {
            const response = await memberApi.activate(id)
            return response.data.data
        } catch (err) {
            error.value = err.response?.data?.message || 'Gagal mengaktifkan member'
            throw err
        } finally {
            loading.value = false
        }
    }

    const updateStatus = async (id, status) => {
        loading.value = true
        error.value = null
        try {
            const response = await memberApi.updateStatus(id, status)
            member.value = response.data.data
            return response.data.data
        } catch (err) {
            error.value = err.response?.data?.message || 'Gagal memperbarui status member'
            throw err
        } finally {
            loading.value = false
        }
    }

    const extendMember = async (id, data) => {
        loading.value = true
        error.value = null
        try {
            const response = await memberApi.extend(id, data)
            return response.data.data
        } catch (err) {
            error.value = err.response?.data?.message || 'Gagal memperpanjang masa aktif member'
            throw err
        } finally {
            loading.value = false
        }
    }

    const fetchExtensions = async (id) => {
        loading.value = true
        error.value = null
        try {
            const response = await memberApi.getExtensions(id)
            extensions.value = response.data.data
            return response.data.data
        } catch (err) {
            error.value = err.response?.data?.message || 'Gagal memuat history perpanjang member'
            throw err
        } finally {
            loading.value = false
        }
    }

    return {
        members,
        member,
        extensions,
        loading,
        error,
        pagination,
        fetchAll,
        fetchDetail,
        store,
        update,
        activate,
        updateStatus,
        extendMember,
        fetchExtensions
    }
}
