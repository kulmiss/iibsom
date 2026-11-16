import { useState } from 'react'
import { useNavigate, useSearch } from '@tanstack/react-router'
import { Loader2 } from 'lucide-react'
import { api, apiErrorMessage } from '@/lib/api'
import { cn } from '@/lib/utils'
import { useAuthStore, type StaffUser } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { InputOTP, InputOTPGroup, InputOTPSeparator, InputOTPSlot } from '@/components/ui/input-otp'
import { Label } from '@/components/ui/label'

type Props = React.HTMLAttributes<HTMLFormElement> & {
  /** "verify" = normal sign-in, "confirm" = first-time 2FA setup */
  mode?: 'verify' | 'confirm'
  onConfirmed?: (recoveryCodes: string[]) => void
}

export function OtpForm({ className, mode = 'verify', onConfirmed, ...props }: Props) {
  const navigate = useNavigate()
  const { redirect } = useSearch({ strict: false }) as { redirect?: string }
  const setUser = useAuthStore((s) => s.setUser)
  const [code, setCode] = useState('')
  const [recovery, setRecovery] = useState('')
  const [useRecovery, setUseRecovery] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError(null)
    try {
      if (mode === 'confirm') {
        const { data } = await api.post<{ recovery_codes: string[]; user: StaffUser }>('/2fa/confirm', { code })
        setUser(data.user)
        onConfirmed?.(data.recovery_codes)
      } else {
        const body = useRecovery ? { recovery_code: recovery } : { code }
        const { data } = await api.post<{ user: StaffUser }>('/2fa/verify', body)
        setUser(data.user)
        navigate({ to: redirect && redirect.startsWith('/') ? redirect.replace(/^\/admin/, '') || '/' : '/', replace: true })
      }
    } catch (err) {
      setError(apiErrorMessage(err))
      setCode('')
    } finally {
      setLoading(false)
    }
  }

  return (
    <form onSubmit={submit} className={cn('grid gap-3', className)} {...props}>
      {useRecovery ? (
        <div className='grid gap-2'>
          <Label htmlFor='recovery'>Recovery code</Label>
          <Input id='recovery' autoComplete='one-time-code' placeholder='ABCDE-12345' value={recovery} onChange={(e) => setRecovery(e.target.value)} />
        </div>
      ) : (
        <InputOTP maxLength={6} value={code} onChange={setCode} autoFocus containerClassName='justify-between sm:[&>[data-slot="input-otp-group"]>div]:w-12' aria-label='6-digit code'>
          <InputOTPGroup>
            <InputOTPSlot index={0} />
            <InputOTPSlot index={1} />
          </InputOTPGroup>
          <InputOTPSeparator />
          <InputOTPGroup>
            <InputOTPSlot index={2} />
            <InputOTPSlot index={3} />
          </InputOTPGroup>
          <InputOTPSeparator />
          <InputOTPGroup>
            <InputOTPSlot index={4} />
            <InputOTPSlot index={5} />
          </InputOTPGroup>
        </InputOTP>
      )}

      {error && (
        <p role='alert' className='text-sm font-medium text-destructive'>
          {error}
        </p>
      )}

      <Button className='mt-1' disabled={loading || (useRecovery ? recovery.length < 5 : code.length < 6)}>
        {loading && <Loader2 className='animate-spin' />}
        {mode === 'confirm' ? 'Turn on 2FA' : 'Sign in'}
      </Button>

      {mode === 'verify' && (
        <button type='button' className='text-sm text-muted-foreground underline underline-offset-4' onClick={() => setUseRecovery((v) => !v)}>
          {useRecovery ? 'Use the code from my app' : 'Lost your phone? Use a recovery code'}
        </button>
      )}
    </form>
  )
}
