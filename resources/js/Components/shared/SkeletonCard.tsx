import { cn } from '@/lib/utils'

interface Props {
    className?: string
    height?: string
}

export function SkeletonLine({ className }: { className?: string }) {
    return <div className={cn('skeleton rounded', className)} />
}

export default function SkeletonCard({ className, height = 'h-40' }: Props) {
    return (
        <div className={cn(
            'rounded-xl border p-5 space-y-3',
            height,
            className
        )}
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
            <SkeletonLine className="h-4 w-1/3" />
            <SkeletonLine className="h-8 w-1/2" />
            <SkeletonLine className="h-3 w-2/3" />
        </div>
    )
}
