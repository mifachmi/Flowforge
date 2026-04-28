import { create } from 'zustand'

interface AuthState {
  token: string | null
  user: { name: string; email: string; role: string } | null
  setToken: (token: string) => void
  setUser: (user: AuthState['user']) => void
  logout: () => void
  isAuthenticated: () => boolean
}

export const useAuthStore = create<AuthState>((set, get) => ({
  token: sessionStorage.getItem('token'),
  user: null,

  setToken: (token) => {
    sessionStorage.setItem('token', token)
    set({ token })
  },

  setUser: (user) => set({ user }),

  logout: () => {
    sessionStorage.removeItem('token')
    set({ token: null, user: null })
  },

  isAuthenticated: () => !!get().token,
}))
