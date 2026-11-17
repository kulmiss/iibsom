/** "Amina Ali" → "AA" for avatar circles. Only letters count ("Owner (dev)" → "OD"). */
export function initials(name: string | undefined): string {
  const letters = (name ?? '')
    .split(/\s+/)
    .map((p) => p.replace(/[^\p{L}]/gu, ''))
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]!.toUpperCase())
    .join('')

  return letters || '?'
}
