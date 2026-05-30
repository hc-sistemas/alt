import { useState, useEffect } from 'react'
import { usePage } from '@inertiajs/react'
import Sidebar from '@/Components/shared/Sidebar'
import Topbar from '@/Components/shared/Topbar'
import { useThemeStore } from '@/Stores/themeStore'
import type { PageProps } from '@/types'

interface Props {
    children: React.ReactNode
    title?: string
}

function getDefaultCollapsed(): boolean {
    if (typeof window === 'undefined') return false
    const saved = localStorage.getItem('altamira-sidebar-collapsed')
    if (saved !== null) return saved === 'true'
    return window.innerWidth < 1024
}

export default function AppLayout({ children, title }: Props) {
    const [sidebarCollapsed, setSidebarCollapsed] = useState(getDefaultCollapsed)
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
    const { theme } = useThemeStore()
    const { flash } = usePage<PageProps>().props
    const [flashMessage, setFlashMessage] = useState(flash)

    useEffect(() => {
        if (flash.success || flash.error) {
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

                {/* Flash messages */}
                {(flashMessage.success || flashMessage.error) && (
                    <div className={`mx-4 mt-3 p-3 rounded-lg border text-sm flex items-center gap-2 ${
                        flashMessage.success
                            ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400'
                            : 'bg-red-500/10 border-red-500/30 text-red-600 dark:text-red-400'
                    }`}>
                        <span className="w-1.5 h-1.5 rounded-full shrink-0"
                            style={{ background: flashMessage.success ? '#10B981' : '#EF4444' }} />
                        {flashMessage.success || flashMessage.error}
                    </div>
                )}

                {/* Page content */}
                <main className="flex-1 overflow-y-auto">
                    {children}
                </main>
            </div>
        </div>
    )
}
