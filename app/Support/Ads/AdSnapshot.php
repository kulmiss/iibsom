<?php

namespace App\Support\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Area;
use App\Models\City;

/**
 * The approved version of an ad, so moderators can see exactly what a seller
 * changed after approval ("Edited" in the queue). Saved on approval and when
 * staff edit a live ad.
 *
 * @phpstan-type Snapshot array{title: string, description: string, price: ?int, price_negotiable: bool, condition: ?string, city_id: int, area_id: ?int, fields: array<array-key, mixed>, photos: list<int>}
 * @phpstan-type Change array{key: string, label: string, before: string, after: string}
 */
final class AdSnapshot
{
    /** @return Snapshot */
    public static function take(Ad $ad): array
    {
        return [
            'title' => $ad->title,
            'description' => $ad->description,
            'price' => $ad->price,
            'price_negotiable' => $ad->price_negotiable,
            'condition' => $ad->condition,
            'city_id' => $ad->city_id,
            'area_id' => $ad->area_id,
            'fields' => $ad->fields ?? [],
            'photos' => array_values(array_map('intval', $ad->photos()->orderBy('sort')->pluck('id')->all())),
        ];
    }

    /** Was approved before and is waiting again after the seller changed it. */
    public static function isEdited(Ad $ad): bool
    {
        return feature('ads.edit_changes') && $ad->status === AdStatus::Pending && $ad->approved_snapshot !== null;
    }

    /**
     * What is different from the approved version (null = nothing to compare).
     *
     * @return list<Change>|null
     */
    public static function changes(Ad $ad): ?array
    {
        $before = $ad->approved_snapshot;
        if ($before === null || ! self::isEdited($ad)) {
            return null;
        }
        $after = self::take($ad);
        $changes = [];

        foreach (['title' => 'Title', 'description' => 'Description'] as $key => $label) {
            if ((string) ($before[$key] ?? '') !== $after[$key]) {
                $changes[] = self::change($key, $label, (string) ($before[$key] ?? ''), $after[$key]);
            }
        }

        $price = fn (array $s): string => trim((isset($s['price']) ? '$'.number_format((int) $s['price']) : '').(! empty($s['price_negotiable']) ? ' · Negotiable' : '')) ?: '—';
        if ($price($before) !== $price($after)) {
            $changes[] = self::change('price', 'Price', $price($before), $price($after));
        }

        if (($before['condition'] ?? null) !== $after['condition']) {
            $changes[] = self::change('condition', 'Condition', ucfirst((string) ($before['condition'] ?? '—')), ucfirst($after['condition'] ?? '—'));
        }

        $place = fn (array $s): string => trim((City::find($s['city_id'] ?? 0)->label ?? '—').(isset($s['area_id']) ? ', '.(Area::find($s['area_id'])->label ?? '?') : ''));
        if (($before['city_id'] ?? null) !== $after['city_id'] || ($before['area_id'] ?? null) !== $after['area_id']) {
            $changes[] = self::change('place', 'Location', $place($before), $place($after));
        }

        $labels = $ad->category->allFields()->keyBy('key');
        $oldFields = is_array($before['fields'] ?? null) ? $before['fields'] : [];
        foreach (array_unique([...array_keys($oldFields), ...array_keys($after['fields'])]) as $key) {
            $old = $oldFields[$key] ?? null;
            $new = $after['fields'][$key] ?? null;
            if ($old != $new) { // 2014 vs "2014" is the same answer
                $field = $labels->get($key);
                $show = fn (mixed $v): string => $v === null ? '—' : (is_bool($v) ? ($v ? 'Yes' : 'No') : (string) ($field?->optionList()[(string) $v] ?? $v));
                $changes[] = self::change('field.'.$key, $field !== null ? $field->label_text : (string) $key, $show($old), $show($new));
            }
        }

        $oldPhotos = array_map('intval', is_array($before['photos'] ?? null) ? $before['photos'] : []);
        if ($oldPhotos !== $after['photos']) {
            $added = count(array_diff($after['photos'], $oldPhotos));
            $removed = count(array_diff($oldPhotos, $after['photos']));
            $text = $added || $removed ? trim(($added ? "{$added} added " : '').($removed ? "{$removed} removed" : '')) : 'Order changed (new cover photo?)';
            $count = fn (int $n): string => $n.($n === 1 ? ' photo' : ' photos');
            $changes[] = self::change('photos', 'Photos', $count(count($oldPhotos)), $count(count($after['photos'])).' — '.$text);
        }

        return $changes;
    }

    /** For ads approved before snapshots existed (run once by the migration). */
    public static function backfill(): void
    {
        Ad::withTrashed()->whereNull('approved_snapshot')->whereNotNull('reviewed_at')
            ->whereIn('status', [AdStatus::Live, AdStatus::Sold, AdStatus::Expired])
            ->chunkById(200, function ($ads) {
                foreach ($ads as $ad) {
                    // Straight to the table: no events, and updated_at stays as it was.
                    Ad::withTrashed()->whereKey($ad->id)->toBase()->update(['approved_snapshot' => json_encode(self::take($ad))]);
                }
            });
    }

    /** @return Change */
    private static function change(string $key, string $label, string $before, string $after): array
    {
        return compact('key', 'label', 'before', 'after');
    }
}
