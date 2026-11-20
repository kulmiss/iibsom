import { useState } from 'react'
import { useNavigate } from '@tanstack/react-router'
import { Search } from 'lucide-react'
import { findSettings, settingAllowed, type SettingEntry } from '@/lib/settings-index'
import { useAuthStore } from '@/stores/auth-store'
import { Input } from '@/components/ui/input'

/** "Find a setting": type a word (colour, email, drugs…) and jump straight to the field (PR 101). */
export function SettingFinder() {
  const navigate = useNavigate()
  const can = useAuthStore((s) => s.can)
  const user = useAuthStore((s) => s.user)
  const [q, setQ] = useState('')
  const [active, setActive] = useState(0)
  const results = findSettings(q, (e) => settingAllowed(e, can, user))

  const open = (e: SettingEntry) => {
    setQ('')
    navigate({ to: e.to, hash: e.hash, search: e.search } as never)
  }

  return (
    <div className='relative'>
      <Search className='pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' aria-hidden />
      <Input
        value={q}
        onChange={(e) => {
          setQ(e.target.value)
          setActive(0)
        }}
        onKeyDown={(e) => {
          if (e.key === 'ArrowDown') {
            e.preventDefault()
            setActive((a) => Math.min(a + 1, results.length - 1))
          } else if (e.key === 'ArrowUp') {
            e.preventDefault()
            setActive((a) => Math.max(a - 1, 0))
          } else if (e.key === 'Enter' && results[active]) {
            e.preventDefault()
            open(results[active])
          } else if (e.key === 'Escape') {
            setQ('')
          }
        }}
        placeholder='Find a setting…'
        className='ps-9'
        aria-label='Find a setting'
        role='combobox'
        aria-expanded={results.length > 0}
        aria-controls='setting-finder-list'
      />
      {q.trim() !== '' && (
        <ul id='setting-finder-list' role='listbox' className='absolute z-30 mt-1 w-full overflow-hidden rounded-md border bg-popover py-1 text-sm shadow-md'>
          {results.length === 0 && <li className='px-3 py-2 text-muted-foreground'>Nothing found. Try another word.</li>}
          {results.map((r, i) => (
            <li key={r.label} role='option' aria-selected={i === active}>
              <button
                type='button'
                className={`flex w-full flex-col items-start px-3 py-2 text-start hover:bg-accent ${i === active ? 'bg-accent' : ''}`}
                onMouseEnter={() => setActive(i)}
                onClick={() => open(r)}
              >
                <span className='font-medium'>{r.label}</span>
                <span className='text-xs text-muted-foreground'>{r.section}</span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
