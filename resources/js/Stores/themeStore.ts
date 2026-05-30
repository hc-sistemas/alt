import { create } from 'zustand'

interface ThemeState {
    theme: 'light' | 'dark'
    toggleTheme: () => void
    setTheme: (theme: 'light' | 'dark') => void
}

const getInitialTheme = (): 'light' | 'dark' => {
    if (typeof window === 'undefined') return 'light'
    const saved = localStorage.getItem('altamira-theme') as 'light' | 'dark' | null
    return saved ?? 'light'
}

const applyTheme = (theme: 'light' | 'dark') => {
    if (typeof document !== 'undefined') {
        document.documentElement.classList.toggle('dark', theme === 'dark')
    }
}

export const useThemeStore = create<ThemeState>((set) => ({
    theme: getInitialTheme(),
    toggleTheme: () => set((state) => {
        const newTheme = state.theme === 'light' ? 'dark' : 'light'
        localStorage.setItem('altamira-theme', newTheme)
        applyTheme(newTheme)
        return { theme: newTheme }
    }),
    setTheme: (theme) => {
        localStorage.setItem('altamira-theme', theme)
        applyTheme(theme)
        set({ theme })
    },
}))

// Aplicar tema al cargar
if (typeof window !== 'undefined') {
    applyTheme(getInitialTheme())
}
