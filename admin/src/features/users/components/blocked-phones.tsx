import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { type ColumnDef } from '@tanstack/react-table'
import { format } from 'date-fns'
import { Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ServerDataTable } from '@/components/server-data-table'

type Blocked = { id: number; phone: string; reason: string | null; by: string | null; created_at: string }

export function BlockedPhones() {
  const can = useAuthStore((s) => s.can)
  const qc = useQueryClient()
  const [adding, setAdding] = useState(false)
  const [phone, setPhone] = useState('')
  const [reason, setReason] = useState('')

  const add = useMutation({
    mutationFn: () => api.post('/blocked-phones', { phone, reason }),
    onSuccess: () => {
      toast.success('Number blocked.')
      setAdding(false)
      setPhone('')
      setReason('')
      qc.invalidateQueries({ queryKey: ['blocked-phones'] })
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })
  const remove = useMutation({
    mutationFn: (id: number) => api.delete(`/blocked-phones/${id}`),
    onSuccess: () => {
      toast.success('Number unblocked.')
      qc.invalidateQueries({ queryKey: ['blocked-phones'] })
    },
  })

  const columns: ColumnDef<Blocked>[] = [
    { accessorKey: 'phone', header: 'Phone', cell: ({ row }) => <span className='font-mono'>{row.original.phone}</span> },
    { accessorKey: 'reason', header: 'Reason', cell: ({ row }) => row.original.reason ?? '–' },
    { accessorKey: 'by', header: 'Blocked by' },
    { accessorKey: 'created_at', header: 'When', cell: ({ row }) => format(new Date(row.original.created_at), 'd MMM yyyy') },
    {
      id: 'actions',
      header: '',
      cell: ({ row }) =>
        can('users.ban') && (
          <Button size='sm' variant='ghost' onClick={() => remove.mutate(row.original.id)}>
            <Trash2 /> Unblock
          </Button>
        ),
    },
  ]

  return (
    <div className='flex flex-1 flex-col gap-3'>
      {can('users.ban') && (
        <Button className='self-start' onClick={() => setAdding(true)}>
          <Plus /> Block a number
        </Button>
      )}
      <ServerDataTable<Blocked> url='/blocked-phones' queryKey='blocked-phones' columns={columns} searchPlaceholder='Search number…' rowId={(r) => String(r.id)} csvName='blocked-phones' />
      <Dialog open={adding} onOpenChange={setAdding}>
        <DialogContent className='sm:max-w-sm'>
          <DialogHeader>
            <DialogTitle>Block a phone number</DialogTitle>
          </DialogHeader>
          <div className='grid gap-3'>
            <div className='grid gap-2'>
              <Label htmlFor='bp-phone'>Somali mobile number</Label>
              <Input id='bp-phone' placeholder='61 5123456' value={phone} onChange={(e) => setPhone(e.target.value)} />
            </div>
            <div className='grid gap-2'>
              <Label htmlFor='bp-reason'>Reason (optional)</Label>
              <Input id='bp-reason' value={reason} onChange={(e) => setReason(e.target.value)} maxLength={200} />
            </div>
          </div>
          <DialogFooter>
            <Button onClick={() => add.mutate()} disabled={phone.length < 6 || add.isPending}>
              Block
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
