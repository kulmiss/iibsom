import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { api, apiErrorMessage } from '@/lib/api'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { CategoryIcon } from '@/components/category-icon'
import { type CategoryRow } from './types'

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  category: CategoryRow | null // null = new
  mains: CategoryRow[]
  icons: string[]
  defaultParentId?: number | null
}

export function CategoryDialog({ open, onOpenChange, category, mains, icons, defaultParentId = null }: Props) {
  const qc = useQueryClient()
  // The parent remounts this dialog (key) for each category, so the start values are read once.
  const [form, setForm] = useState(() =>
    category
      ? { name_en: category.name_en, name_so: category.name_so ?? '', parent_id: category.parent_id, icon: category.icon, enabled: category.enabled, popular: category.popular, has_condition: category.has_condition }
      : { name_en: '', name_so: '', parent_id: defaultParentId, icon: 'tag', enabled: true, popular: false, has_condition: true }
  )

  const save = useMutation({
    mutationFn: () => (category ? api.put(`/categories/${category.id}`, form) : api.post('/categories', form)),
    onSuccess: () => {
      toast.success(category ? 'Category saved.' : 'Category added.')
      qc.invalidateQueries({ queryKey: ['categories'] })
      onOpenChange(false)
    },
    onError: (e) => toast.error(apiErrorMessage(e)),
  })

  const isMainWithChildren = !!category && category.parent_id === null && (category.children?.length ?? 0) > 0

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='sm:max-w-lg'>
        <DialogHeader>
          <DialogTitle>{category ? `Edit “${category.name_en}”` : 'Add a category'}</DialogTitle>
        </DialogHeader>
        <div className='grid gap-4'>
          <div className='grid grid-cols-2 gap-3'>
            <div className='grid gap-2'>
              <Label htmlFor='cat-en'>Name (English)</Label>
              <Input id='cat-en' value={form.name_en} onChange={(e) => setForm({ ...form, name_en: e.target.value })} maxLength={60} />
            </div>
            <div className='grid gap-2'>
              <Label htmlFor='cat-so'>Name (Somali)</Label>
              <Input id='cat-so' value={form.name_so} onChange={(e) => setForm({ ...form, name_so: e.target.value })} maxLength={60} placeholder='Optional' />
            </div>
          </div>
          <div className='grid gap-2'>
            <Label>Inside</Label>
            <Select disabled={isMainWithChildren} value={form.parent_id ? String(form.parent_id) : 'none'} onValueChange={(v) => setForm({ ...form, parent_id: v === 'none' ? null : Number(v) })}>
              <SelectTrigger className='w-full'>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value='none'>Nothing: this is a main category</SelectItem>
                {mains
                  .filter((m) => m.id !== category?.id)
                  .map((m) => (
                    <SelectItem key={m.id} value={String(m.id)}>
                      {m.name_en}
                    </SelectItem>
                  ))}
              </SelectContent>
            </Select>
            <p className='text-xs text-muted-foreground'>Ads are posted in sub categories (for example Vehicles → Cars).</p>
          </div>
          <div className='grid gap-2'>
            <Label>Icon</Label>
            <ScrollArea className='h-32 rounded-md border p-2'>
              <div className='grid grid-cols-8 gap-1'>
                {icons.map((name) => (
                  <button
                    key={name}
                    type='button'
                    title={name}
                    onClick={() => setForm({ ...form, icon: name })}
                    className={cn('flex aspect-square items-center justify-center rounded-md hover:bg-muted', form.icon === name && 'bg-primary text-primary-foreground hover:bg-primary')}
                  >
                    <CategoryIcon name={name} className='size-5' />
                  </button>
                ))}
              </div>
            </ScrollArea>
          </div>
          <div className='grid gap-3 text-sm'>
            <label className='flex items-center justify-between gap-3'>
              <span>ON (visible on the site)</span>
              <Switch checked={form.enabled} onCheckedChange={(v) => setForm({ ...form, enabled: v })} />
            </label>
            <label className='flex items-center justify-between gap-3'>
              <span>Show in “Popular categories” on the home page</span>
              <Switch checked={form.popular} onCheckedChange={(v) => setForm({ ...form, popular: v })} />
            </label>
            <label className='flex items-center justify-between gap-3'>
              <span>Ask “New or used?”</span>
              <Switch checked={form.has_condition} onCheckedChange={(v) => setForm({ ...form, has_condition: v })} />
            </label>
          </div>
        </div>
        <DialogFooter>
          <Button variant='outline' onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={() => save.mutate()} disabled={form.name_en.trim().length < 2 || save.isPending}>
            Save
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
