import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { formatDistanceToNow } from 'date-fns'
import { KeyRound, MoreHorizontal, Plus, ShieldOff, UserMinus } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth-store'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { Page } from '@/components/layout/page'

type Staff = { id: number; name: string; email: string; role: string; role_label: string; two_factor: boolean; last_login_at: string | null }
type Role = { value: string; label: string; permissions: string[] }

export function StaffPage() {
  const qc = useQueryClient()
  const me = useAuthStore((s) => s.user)
  const { data } = useQuery({ queryKey: ['staff'], queryFn: async () => (await api.get<{ staff: Staff[]; roles: Role[] }>('/staff')).data })
  const [adding, setAdding] = useState(false)
  const [form, setForm] = useState({ name: '', email: '', role: 'moderator' })
  const [password, setPassword] = useState<{ name: string; email: string; password: string } | null>(null)
  const [removing, setRemoving] = useState<Staff | null>(null)

  const refresh = () => qc.invalidateQueries({ queryKey: ['staff'] })
  const onError = (e: unknown) => toast.error(apiErrorMessage(e))
  const add = useMutation({
    mutationFn: () => api.post<Staff & { temporary_password: string }>('/staff', form).then((r) => r.data),
    onSuccess: (s) => {
      setAdding(false)
      setForm({ name: '', email: '', role: 'moderator' })
      setPassword({ name: s.name, email: s.email, password: s.temporary_password })
      refresh()
    },
    onError,
  })
  const setRole = useMutation({ mutationFn: ({ id, role }: { id: number; role: string }) => api.put(`/staff/${id}`, { role }), onSuccess: () => { toast.success('Role changed.'); refresh() }, onError })
  const reset2fa = useMutation({ mutationFn: (id: number) => api.post(`/staff/${id}/reset-2fa`), onSuccess: () => { toast.success('2FA reset. They set it up again at next sign-in.'); refresh() }, onError })
  const resetPw = useMutation({
    mutationFn: (s: Staff) => api.post<{ temporary_password: string }>(`/staff/${s.id}/reset-password`).then((r) => ({ s, pw: r.data.temporary_password })),
    onSuccess: ({ s, pw }) => setPassword({ name: s.name, email: s.email, password: pw }),
    onError,
  })
  const remove = useMutation({ mutationFn: (id: number) => api.delete(`/staff/${id}`), onSuccess: () => { toast.success('Removed from staff.'); setRemoving(null); refresh() }, onError })

  return (
    <Page
      title='Staff'
      help='staff'
      description='People who can use this admin. Everyone must use 2FA.'
      actions={
        <Button onClick={() => setAdding(true)}>
          <Plus /> Add staff
        </Button>
      }
    >
      <div className='overflow-hidden rounded-md border'>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Role</TableHead>
              <TableHead>2FA</TableHead>
              <TableHead>Last sign-in</TableHead>
              <TableHead />
            </TableRow>
          </TableHeader>
          <TableBody>
            {data?.staff.map((s) => (
              <TableRow key={s.id}>
                <TableCell>
                  <p className='font-medium'>
                    {s.name} {s.id === me?.id && <Badge variant='secondary'>You</Badge>}
                  </p>
                  <p className='text-xs text-muted-foreground'>{s.email}</p>
                </TableCell>
                <TableCell>
                  <Select value={s.role} disabled={s.id === me?.id} onValueChange={(role) => setRole.mutate({ id: s.id, role })}>
                    <SelectTrigger className='h-8 w-36'>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {data.roles.map((r) => (
                        <SelectItem key={r.value} value={r.value}>
                          {r.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </TableCell>
                <TableCell>{s.two_factor ? <Badge variant='outline'>On</Badge> : <Badge variant='destructive'>Not set up</Badge>}</TableCell>
                <TableCell>{s.last_login_at ? formatDistanceToNow(new Date(s.last_login_at), { addSuffix: true }) : 'Never'}</TableCell>
                <TableCell className='text-end'>
                  {s.id !== me?.id && (
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button size='icon' variant='ghost' className='size-8' aria-label={`Actions for ${s.name}`}>
                          <MoreHorizontal />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align='end'>
                        <DropdownMenuItem onClick={() => resetPw.mutate(s)}>
                          <KeyRound /> New password
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => reset2fa.mutate(s.id)}>
                          <ShieldOff /> Reset 2FA (lost phone)
                        </DropdownMenuItem>
                        <DropdownMenuItem variant='destructive' onClick={() => setRemoving(s)}>
                          <UserMinus /> Remove from staff
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <div className='grid gap-4 md:grid-cols-3'>
        {data?.roles.map((r) => (
          <Card key={r.value}>
            <CardHeader>
              <CardTitle className='text-base'>{r.label}</CardTitle>
              <CardDescription>Can do:</CardDescription>
            </CardHeader>
            <CardContent>
              <ul className='list-disc space-y-0.5 ps-5 text-sm'>
                {r.permissions.map((p) => (
                  <li key={p}>{p}</li>
                ))}
              </ul>
            </CardContent>
          </Card>
        ))}
      </div>

      <Dialog open={adding} onOpenChange={setAdding}>
        <DialogContent className='sm:max-w-sm'>
          <DialogHeader>
            <DialogTitle>Add staff</DialogTitle>
            <DialogDescription>You get a one-time password to give them. They set up 2FA at first sign-in.</DialogDescription>
          </DialogHeader>
          <div className='grid gap-3'>
            <div className='grid gap-2'>
              <Label htmlFor='st-name'>Name</Label>
              <Input id='st-name' value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </div>
            <div className='grid gap-2'>
              <Label htmlFor='st-email'>Email</Label>
              <Input id='st-email' type='email' value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
            </div>
            <div className='grid gap-2'>
              <Label>Role</Label>
              <Select value={form.role} onValueChange={(role) => setForm({ ...form, role })}>
                <SelectTrigger className='w-full'>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {data?.roles.map((r) => (
                    <SelectItem key={r.value} value={r.value}>
                      {r.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button onClick={() => add.mutate()} disabled={!form.name || !form.email || add.isPending}>
              Add
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={!!password} onOpenChange={(o) => !o && setPassword(null)}>
        <DialogContent className='sm:max-w-sm'>
          <DialogHeader>
            <DialogTitle>One-time password for {password?.name}</DialogTitle>
            <DialogDescription>Give it to them privately (not by email). You will not see it again.</DialogDescription>
          </DialogHeader>
          <p className='rounded-md bg-muted p-3 text-center font-mono text-lg select-all'>{password?.password}</p>
          <p className='text-xs text-muted-foreground'>Sign-in: {password?.email} at /admin</p>
          <DialogFooter>
            <Button
              onClick={() => {
                navigator.clipboard?.writeText(password?.password ?? '')
                toast.success('Copied')
              }}
            >
              Copy
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ConfirmDialog
        open={!!removing}
        onOpenChange={(o) => !o && setRemoving(null)}
        title={`Remove ${removing?.name} from staff?`}
        desc='They lose access to the admin straight away. Their customer account stays.'
        confirmText='Remove'
        destructive
        handleConfirm={() => removing && remove.mutate(removing.id)}
      />
    </Page>
  )
}
