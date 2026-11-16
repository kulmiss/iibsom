import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { type CategoryRow, type FieldRow } from './types'

const TYPE_LABEL: Record<FieldRow['type'], string> = { text: 'Short text', number: 'Number', select: 'Choose from a list', boolean: 'Yes / No' }

/** The extra questions sellers answer for this category (cars: brand, year, km…). */
export function FieldsSheet({ category, onOpenChange }: { category: CategoryRow | null; onOpenChange: (o: boolean) => void }) {
  const qc = useQueryClient()
  const [editing, setEditing] = useState<FieldRow | 'new' | null>(null)
  const [deleting, setDeleting] = useState<FieldRow | null>(null)

  const { data } = useQuery({
    queryKey: ['fields', category?.id],
    queryFn: async () => (await api.get<{ own: FieldRow[]; inherited: FieldRow[] }>(`/categories/${category!.id}/fields`)).data,
    enabled: !!category,
  })
  const remove = useMutation({
    mutationFn: (f: FieldRow) => api.delete(`/categories/${category!.id}/fields/${f.id}`),
    onSuccess: () => {
      toast.success('Field removed. Old ads keep their answers.')
      setDeleting(null)
      qc.invalidateQueries({ queryKey: ['fields'] })
      qc.invalidateQueries({ queryKey: ['categories'] })
    },
  })

  const row = (f: FieldRow, inherited: boolean) => (
    <li key={f.id} className='flex items-start gap-3 rounded-md border p-3'>
      <div className='min-w-0 flex-1'>
        <p className='font-medium'>
          {f.label_en} {f.unit && <span className='text-muted-foreground'>({f.unit})</span>} {f.required && <Badge variant='outline'>Required</Badge>} {!f.enabled && <Badge variant='secondary'>OFF</Badge>}
        </p>
        <p className='text-xs text-muted-foreground'>
          {TYPE_LABEL[f.type]} · key <code>{f.key}</code>
          {f.filterable && ' · used as a search filter'}
          {inherited && ' · from the main category'}
        </p>
        {f.options.length > 0 && <p className='mt-1 line-clamp-2 text-xs'>{f.options.join(', ')}</p>}
      </div>
      {!inherited && (
        <div className='flex gap-1'>
          <Button size='icon' variant='ghost' className='size-8' aria-label='Edit field' onClick={() => setEditing(f)}>
            <Pencil />
          </Button>
          <Button size='icon' variant='ghost' className='size-8 text-destructive' aria-label='Remove field' onClick={() => setDeleting(f)}>
            <Trash2 />
          </Button>
        </div>
      )}
    </li>
  )

  return (
    <Sheet open={!!category} onOpenChange={onOpenChange}>
      <SheetContent className='flex w-full flex-col gap-0 p-0 sm:max-w-lg'>
        <SheetHeader className='border-b'>
          <SheetTitle>Fields for “{category?.name_en}”</SheetTitle>
          <SheetDescription>Extra questions sellers answer when they post here. Buyers can filter by them.</SheetDescription>
        </SheetHeader>
        <ScrollArea className='min-h-0 flex-1'>
          <div className='space-y-3 p-4'>
            {data?.inherited.length ? <ul className='space-y-2'>{data.inherited.map((f) => row(f, true))}</ul> : null}
            <ul className='space-y-2'>{data?.own.map((f) => row(f, false))}</ul>
            {data && data.own.length === 0 && data.inherited.length === 0 && <p className='text-sm text-muted-foreground'>No extra fields yet.</p>}
          </div>
        </ScrollArea>
        <div className='border-t p-3'>
          <Button onClick={() => setEditing('new')}>
            <Plus /> Add a field
          </Button>
        </div>
        {category && <FieldDialog category={category} field={editing} onClose={() => setEditing(null)} />}
        <ConfirmDialog
          open={!!deleting}
          onOpenChange={(o) => !o && setDeleting(null)}
          title={`Remove “${deleting?.label_en}”?`}
          desc='Sellers will not be asked any more. Old ads keep their answers (hidden).'
          confirmText='Remove'
          destructive
          handleConfirm={() => deleting && remove.mutate(deleting)}
        />
      </SheetContent>
    </Sheet>
  )
}

function FieldDialog({ category, field, onClose }: { category: CategoryRow; field: FieldRow | 'new' | null; onClose: () => void }) {
  const qc = useQueryClient()
  const isNew = field === 'new'
  const initial = isNew || !field ? { key: '', type: 'text' as FieldRow['type'], label_en: '', label_so: '', options: '', unit: '', required: false, filterable: true, enabled: true } : { ...field, label_so: field.label_so ?? '', unit: field.unit ?? '', options: field.options.join('\n') }
  const [form, setForm] = useState(initial)
  const [lastField, setLastField] = useState(field)
  if (field !== lastField) {
    setLastField(field)
    setForm(initial)
  }

  const save = useMutation({
    mutationFn: () => {
      const body = { ...form, options: form.type === 'select' ? form.options.split('\n').map((o) => o.trim()).filter(Boolean) : null }
      return isNew ? api.post(`/categories/${category.id}/fields`, body) : api.put(`/categories/${category.id}/fields/${(field as FieldRow).id}`, body)
    },
    onSuccess: () => {
      toast.success('Field saved.')
      qc.invalidateQueries({ queryKey: ['fields'] })
      qc.invalidateQueries({ queryKey: ['categories'] })
      onClose()
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  return (
    <Dialog open={field !== null} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className='sm:max-w-md'>
        <DialogHeader>
          <DialogTitle>{isNew ? 'Add a field' : `Edit “${(field as FieldRow | null)?.label_en}”`}</DialogTitle>
        </DialogHeader>
        <div className='grid gap-3'>
          <div className='grid grid-cols-2 gap-3'>
            <div className='grid gap-2'>
              <Label htmlFor='f-en'>Question (English)</Label>
              <Input
                id='f-en'
                value={form.label_en}
                onChange={(e) => {
                  const label_en = e.target.value
                  const auto = label_en.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/^(\d)/, 'f_$1')
                  setForm({ ...form, label_en, key: isNew ? auto : form.key })
                }}
              />
            </div>
            <div className='grid gap-2'>
              <Label htmlFor='f-so'>Question (Somali)</Label>
              <Input id='f-so' value={form.label_so} onChange={(e) => setForm({ ...form, label_so: e.target.value })} placeholder='Optional' />
            </div>
          </div>
          <div className='grid grid-cols-2 gap-3'>
            <div className='grid gap-2'>
              <Label>Answer type</Label>
              <Select value={form.type} onValueChange={(v) => setForm({ ...form, type: v as FieldRow['type'] })}>
                <SelectTrigger className='w-full'>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(TYPE_LABEL).map(([v, l]) => (
                    <SelectItem key={v} value={v}>
                      {l}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className='grid gap-2'>
              <Label htmlFor='f-unit'>Unit</Label>
              <Input id='f-unit' value={form.unit} onChange={(e) => setForm({ ...form, unit: e.target.value })} placeholder='km, m², GB…' />
            </div>
          </div>
          {form.type === 'select' && (
            <div className='grid gap-2'>
              <Label htmlFor='f-opts'>Choices (one per line)</Label>
              <Textarea id='f-opts' rows={5} value={form.options} onChange={(e) => setForm({ ...form, options: e.target.value })} />
            </div>
          )}
          <div className='grid gap-2'>
            <Label htmlFor='f-key'>Key</Label>
            <Input id='f-key' value={form.key} onChange={(e) => setForm({ ...form, key: e.target.value })} className='font-mono' />
            <p className='text-xs text-muted-foreground'>Used inside the system. Small letters, numbers and _ only. Don't change it once ads use it.</p>
          </div>
          <div className='grid gap-2 text-sm'>
            <label className='flex items-center justify-between'>
              Required <Switch checked={form.required} onCheckedChange={(v) => setForm({ ...form, required: v })} />
            </label>
            <label className='flex items-center justify-between'>
              Buyers can filter by it <Switch checked={form.filterable} onCheckedChange={(v) => setForm({ ...form, filterable: v })} />
            </label>
            <label className='flex items-center justify-between'>
              ON <Switch checked={form.enabled} onCheckedChange={(v) => setForm({ ...form, enabled: v })} />
            </label>
          </div>
        </div>
        <DialogFooter>
          <Button variant='outline' onClick={onClose}>
            Cancel
          </Button>
          <Button onClick={() => save.mutate()} disabled={!form.label_en || !form.key || save.isPending}>
            Save
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
