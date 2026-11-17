import { ShieldCheck } from 'lucide-react'
import { useAuthStore } from '@/stores/auth-store'
import { ContentSection } from '../components/content-section'

export function SettingsSecurity() {
  const user = useAuthStore((s) => s.user)
  return (
    <ContentSection title='Security (2FA)' desc='Two-factor sign-in is required for all staff.'>
      <div className='flex items-start gap-3 rounded-lg border p-4'>
        <ShieldCheck className='mt-0.5 size-5 text-primary' />
        <div className='text-sm'>
          <p className='font-medium'>2FA is on for {user?.email}</p>
          <p className='text-muted-foreground'>
            Each sign-in needs your password and the 6-digit code from your authenticator app. Lost your phone? Use one of your recovery codes, or ask the Super Admin to reset your 2FA.
          </p>
        </div>
      </div>
    </ContentSection>
  )
}
