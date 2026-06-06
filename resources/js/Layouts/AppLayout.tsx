import { useState } from 'react'
import Sidebar from '@/Components/shared/Sidebar'
import Topbar from '@/Components/shared/Topbar'
import { useThemeStore } from '@/Stores/themeStore'

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
        </div>
    )
}
