import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  description?: string
  label?: string
  confirmText: string
  /** Quick picks shown as buttons above the text box. */
  presets?: string[]
  required?: boolean
  destructive?: boolean
  busy?: boolean
  onConfirm: (text: string) => void
  children?: React.ReactNode
}

/** Asks for a short reason/note (reject an ad, ban a user, handle a report). */
export function ReasonDialog({ open, onOpenChange, title, description, label = 'Reason', confirmText, presets = [], required = true, destructive, busy, onConfirm, children }: Props) {
  const [text, setText] = useState('')
  const ok = !required || text.trim().length >= 3

  return (
    <Dialog
      open={open}
      onOpenChange={(o) => {
        if (!o) setText('')
        onOpenChange(o)
      }}
    >
      <DialogContent className='sm:max-w-md'>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          {description && <DialogDescription>{description}</DialogDescription>}
        </DialogHeader>
        {presets.length > 0 && (
          <div className='flex flex-wrap gap-1.5'>
            {presets.map((p) => (
              <Button key={p} type='button' size='sm' variant={text === p ? 'default' : 'outline'} className='h-7 text-xs' onClick={() => setText(p)}>
                {p}
              </Button>
            ))}
          </div>
        )}
        <div className='grid gap-2'>
          <Label htmlFor='reason-text'>{label}</Label>
          <Textarea id='reason-text' value={text} onChange={(e) => setText(e.target.value)} maxLength={300} rows={3} />
          <p className='text-xs text-muted-foreground'>{required ? 'The seller sees this. Keep it short and clear.' : 'Optional.'}</p>
        </div>
        {children}
        <DialogFooter>
          <Button variant='outline' onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button variant={destructive ? 'destructive' : 'default'} disabled={!ok || busy} onClick={() => onConfirm(text.trim())}>
            {confirmText}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
