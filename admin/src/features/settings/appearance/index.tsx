import { Card, CardContent } from '@/components/ui/card'
import { ContentSection } from '../components/content-section'
import { AppearanceForm } from './appearance-form'
import { BrandColorForm } from './brand-color-form'
import { SiteColorForm } from './site-color-form'

export function SettingsAppearance() {
  return (
    <ContentSection title='Look and colours' desc='The website colour, the admin colour, and light or dark theme.'>
      <div className='grid gap-4'>
        <Card id='site-color' className='scroll-mt-24 py-5'>
          <CardContent className='px-5'>
            <SiteColorForm />
          </CardContent>
        </Card>
        <Card id='admin-color' className='scroll-mt-24 py-5'>
          <CardContent className='px-5'>
            <BrandColorForm />
          </CardContent>
        </Card>
        <Card id='theme' className='scroll-mt-24 py-5'>
          <CardContent className='px-5'>
            <AppearanceForm />
          </CardContent>
        </Card>
      </div>
    </ContentSection>
  )
}
