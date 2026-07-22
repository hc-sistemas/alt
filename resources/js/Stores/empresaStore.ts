import { create } from 'zustand'
import type { Empresa } from '@/types'

interface EmpresaState {
    empresaActiva: Partial<Empresa> | null
    empresasUsuario: Partial<Empresa>[]
    setEmpresaActiva: (empresa: Partial<Empresa> | null) => void
    setEmpresasUsuario: (empresas: Partial<Empresa>[]) => void
}

export const useEmpresaStore = create<EmpresaState>((set) => ({
    empresaActiva: null,
    empresasUsuario: [],
    setEmpresaActiva: (empresa) => set({ empresaActiva: empresa }),
    setEmpresasUsuario: (empresas) => set({ empresasUsuario: empresas }),
}))
