import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate } from '@tanstack/react-router'
import { Copy, ShieldCheck } from 'lucide-react'
import { toast } from 'sonner'
import { api } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { AuthLayout } from '../auth-layout'
import { OtpForm } from '../otp/components/otp-form'

/** First staff sign-in: 2FA is required, so set it up now. */
export function TwoFactorSetup() {
  const navigate = useNavigate()
  const [codes, setCodes] = useState<string[] | null>(null)
  const setup = useQuery({
    queryKey: ['2fa-setup'],
    queryFn: async () => (await api.get<{ qr_svg: string; secret: string }>('/2fa/setup')).data,
    retry: false,
    staleTime: Infinity,
  })

  if (codes) {
    return (
      <AuthLayout>
        <Card className='max-w-md gap-4'>
          <CardHeader>
            <CardTitle className='flex items-center gap-2 text-base'>
              <ShieldCheck className='size-5 text-primary' /> 2FA is on. Save your recovery codes
            </CardTitle>
            <CardDescription>If you lose your phone, each code lets you sign in once. Write them down or keep them in a password manager. You will not see them again.</CardDescription>
          </CardHeader>
          <CardContent>
            <ul className='grid grid-cols-2 gap-2 rounded-lg bg-muted p-3 font-mono text-sm'>
              {codes.map((c) => (
                <li key={c}>{c}</li>
              ))}
            </ul>
          </CardContent>
          <CardFooter className='flex gap-2'>
            <Button
              variant='outline'
              onClick={() => {
                navigator.clipboard?.writeText(codes.join('\n'))
                toast.success('Codes copied')
              }}
            >
              <Copy /> Copy
            </Button>
            <Button className='flex-1' onClick={() => navigate({ to: '/', replace: true })}>
              I saved them, continue
            </Button>
          </CardFooter>
        </Card>
      </AuthLayout>
    )
  }

  return (
    <AuthLayout>
      <Card className='max-w-md gap-4'>
        <CardHeader>
          <CardTitle className='text-base'>Set up two-factor sign-in (required)</CardTitle>
          <CardDescription>1. Install an authenticator app on your phone. 2. Scan this QR code. 3. Type the 6-digit code it shows.</CardDescription>
        </CardHeader>
        <CardContent className='grid gap-4'>
          {setup.isLoading && <Skeleton className='mx-auto size-[220px]' />}
          {setup.isError && (
            <p className='text-sm text-destructive'>
              Your sign-in step expired. <Link to='/sign-in' className='underline'>Sign in again</Link>.
            </p>
          )}
          {setup.data && (
            <>
              {/* QR SVG made by the server (no user input inside). */}
              <div className='mx-auto rounded-lg bg-white p-2' dangerouslySetInnerHTML={{ __html: setup.data.qr_svg }} />
              <p className='text-center text-xs text-muted-foreground'>
                Can't scan? Enter this key: <span className='font-mono break-all select-all'>{setup.data.secret}</span>
              </p>
              <OtpForm mode='confirm' onConfirmed={setCodes} />
            </>
          )}
        </CardContent>
      </Card>
    </AuthLayout>
  )
}
