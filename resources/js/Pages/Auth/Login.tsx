import { useForm, Head } from '@inertiajs/react'
import { FormEvent, useState } from 'react'
import AuthLayout from '@/Layouts/AuthLayout'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Eye, EyeOff, LogIn, AlertCircle } from 'lucide-react'

interface Props {
    errors?: Record<string, string>
}

export default function Login({ errors: serverErrors }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    })

    const [showPassword, setShowPassword] = useState(false)

    const allErrors = { ...serverErrors, ...errors }

    function submit(e: FormEvent) {
        e.preventDefault()
        post(route('login.post'))
    }

    return (
        <AuthLayout>
            <Head title="Iniciar Sesión" />

            <div className="space-y-6">
                <div>
                    <h2 className="text-2xl font-bold" style={{ color: 'var(--text-main)' }}>
                        Iniciar Sesión
                    </h2>
                    <p className="mt-1 text-sm" style={{ color: 'var(--text-muted)' }}>
                        Ingresa tus credenciales para continuar
                    </p>
                </div>

                {/* Error general */}
                {allErrors.email && (
                    <div className="flex items-start gap-2 p-3 rounded-lg bg-red-500/10 border border-red-500/20">
                        <AlertCircle className="w-4 h-4 text-red-400 mt-0.5 shrink-0" />
                        <p className="text-sm text-red-400">{allErrors.email}</p>
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="email">Correo electrónico</Label>
                        <div className="relative">
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={e => setData('email', e.target.value)}
                                placeholder="correo@empresa.com"
                                autoComplete="email"
                                autoFocus
                                error={allErrors.email}
                                className="pl-9"
                            />
                            <svg className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }}
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                    d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                            </svg>
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="password">Contraseña</Label>
                        <div className="relative">
                            <Input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                value={data.password}
                                onChange={e => setData('password', e.target.value)}
                                placeholder="••••••••"
                                autoComplete="current-password"
                                error={allErrors.password}
                                className="pl-9 pr-10"
                            />
                            <svg className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }}
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <button
                                type="button"
                                onClick={() => setShowPassword(!showPassword)}
                                className="absolute right-3 top-2.5 transition-colors"
                                style={{ color: 'var(--text-muted)' }}
                                tabIndex={-1}
                            >
                                {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                            </button>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <input
                            id="remember"
                            type="checkbox"
                            checked={data.remember}
                            onChange={e => setData('remember', e.target.checked)}
                            className="rounded border-slate-300 text-amber-500"
                        />
                        <Label htmlFor="remember" className="font-normal cursor-pointer">
                            Recordarme
                        </Label>
                    </div>

                    <Button
                        type="submit"
                        className="w-full h-10 text-base font-semibold"
                        loading={processing}
                    >
                        <LogIn className="w-4 h-4" />
                        Ingresar
                    </Button>
                </form>
            </div>
        </AuthLayout>
    )
}
