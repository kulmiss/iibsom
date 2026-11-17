import { Separator } from '@/components/ui/separator'
import { ContentSection } from '../components/content-section'
import { AppearanceForm } from './appearance-form'
import { BrandColorForm } from './brand-color-form'

export function SettingsAppearance() {
  return (
    <ContentSection title='Appearance' desc='Light or dark theme, font and the admin brand colour.'>
      <div className='space-y-8'>
        <BrandColorForm />
        <Separator />
        <AppearanceForm />
      </div>
    </ContentSection>
  )
}
