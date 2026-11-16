import { useSearch } from '@tanstack/react-router'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { AuthLayout } from '../auth-layout'
import { UserAuthForm } from './components/user-auth-form'

export function SignIn() {
  const { redirect } = useSearch({ from: '/(auth)/sign-in' })

  return (
    <AuthLayout>
      <Card className='max-w-sm gap-4'>
        <CardHeader>
          <CardTitle className='text-lg tracking-tight'>Staff sign in</CardTitle>
          <CardDescription>For iibsom staff only. After your password you will need the 6-digit code from your authenticator app.</CardDescription>
        </CardHeader>
        <CardContent>
          <UserAuthForm redirectTo={redirect} />
        </CardContent>
        <CardFooter>
          <p className='text-center text-xs text-muted-foreground'>Forgot your password or lost your phone? Ask the Super Admin to reset your account.</p>
        </CardFooter>
      </Card>
    </AuthLayout>
  )
}
