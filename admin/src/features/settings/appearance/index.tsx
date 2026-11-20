import { Separator } from '@/components/ui/separator'
import { ContentSection } from '../components/content-section'
import { AppearanceForm } from './appearance-form'
import { BrandColorForm } from './brand-color-form'
import { SiteColorForm } from './site-color-form'

export function SettingsAppearance() {
  return (
    <ContentSection title='Appearance' desc='The website colour, the admin colour, and light or dark theme.'>
      <div className='space-y-8'>
        <SiteColorForm />
        <Separator />
        <BrandColorForm />
        <Separator />
        <AppearanceForm />
      </div>
    </ContentSection>
  )
}
