import { useAuthStore } from '../stores/auth'

export function usePermission() {
  const auth = useAuthStore()

  const can = (key) => {
    return auth.hasPermission(key)
  }

  return { can }
}
