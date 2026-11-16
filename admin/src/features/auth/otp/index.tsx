import { Link } from '@tanstack/react-router'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { AuthLayout } from '../auth-layout'
import { OtpForm } from './components/otp-form'

export function Otp() {
  return (
    <AuthLayout>
      <Card className='max-w-md gap-4'>
        <CardHeader>
          <CardTitle className='text-base tracking-tight'>Enter your 6-digit code</CardTitle>
          <CardDescription>Open your authenticator app (Google Authenticator, Microsoft Authenticator, Authy…) and type the code for iibsom.</CardDescription>
        </CardHeader>
        <CardContent>
          <OtpForm mode='verify' />
        </CardContent>
        <CardFooter>
          <Link to='/sign-in' className='text-sm text-muted-foreground underline underline-offset-4 hover:text-primary'>
            Back to sign in
          </Link>
        </CardFooter>
      </Card>
    </AuthLayout>
  )
}
