import { Page } from '@/components/layout/page'
import { AdsTable } from './components/ads-table'

export function AllAds() {
  return (
    <Page title='Ads' description='Every ad on iibsom. Tap an ad to see it all, approve, reject, edit or delete.'>
      <AdsTable />
    </Page>
  )
}

export function PendingAds() {
  return (
    <Page title='Waiting for approval' description='Every new ad is checked before it goes live. Select many to approve or reject at once.'>
      <AdsTable status='pending' queue />
    </Page>
  )
}
