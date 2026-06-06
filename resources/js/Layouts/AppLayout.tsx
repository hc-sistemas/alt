import { useState, useEffect } from 'react'
import { usePage } from '@inertiajs/react'
import Sidebar from '@/Components/shared/Sidebar'
import Topbar from '@/Components/shared/Topbar'
import { useThemeStore } from '@/Stores/themeStore'
import { ToastContainer } from 'react-toastify'
import 'react-toastify/dist/ReactToastify.css'
import type { PageProps } from '@/types'

interface Props {
    children: React.ReactNode
    title?: string
    suppressFlash?: boolean
}

function getDefaultCollapsed(): boolean {
    if (typeof window === 'undefined') return false
    const saved = localStorage.getItem('altamira-sidebar-collapsed')
    if (saved !== null) return saved === 'true'
    return window.innerWidth < 1024
}

export default function AppLayout({ children, title, suppressFlash }: Props) {
    const [sidebarCollapsed, setSidebarCollapsed] = useState(getDefaultCollapsed)
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
    const { theme } = useThemeStore()
    const { flash } = usePage<PageProps>().props
    const [flashMessage, setFlashMessage] = useState(flash)

    useEffect(() => {
        if (flash.success || flash.error || flash.warning) {
            setFlashMessage(flash)
            const timer = setTimeout(() => setFlashMessage({}), 4000)
            return () => clearTimeout(timer)
        }
    }, [flash])

    function handleCollapse(v: boolean) {
        setSidebarCollapsed(v)
        localStorage.setItem('altamira-sidebar-collapsed', String(v))
    }

    return (
        <div className="flex h-screen overflow-hidden" style={{ background: 'var(--bg-main)' }}>
            {/* Sidebar */}
            <Sidebar
                collapsed={sidebarCollapsed}
                onCollapse={handleCollapse}
                mobileOpen={mobileMenuOpen}
                onMobileClose={() => setMobileMenuOpen(false)}
            />

            {/* Main content */}
            <div className="flex-1 flex flex-col min-w-0 overflow-hidden">
                <Topbar
                    onMobileMenu={() => setMobileMenuOpen(true)}
                    pageTitle={title}
                />

                {/* Page content */}
                <main className="flex-1 overflow-y-auto">
                    {children}
                </main>
            </div>

            {/* Toast notifications globales (suprimido cuando la página tiene su propio TC) */}
            {!suppressFlash && (
                <ToastContainer
                    position="top-right"
                    autoClose={4000}
                    hideProgressBar={false}
                    newestOnTop
                    closeOnClick
                    pauseOnHover
                    theme={theme === 'dark' ? 'dark' : 'colored'}
                />
            )}
        </div>
    )
}
