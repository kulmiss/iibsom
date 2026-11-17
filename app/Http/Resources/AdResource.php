<?php

namespace App\Http\Resources;

use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ad */
class AdResource extends JsonResource
{
    /** true = full ad (description, all photos, seller name). */
    public bool $detail = false;

    /** @return array<int|string, mixed> */
    public function toArray(Request $request): array
    {
        $detail = $this->detail;
        $photo = fn ($p) => ['thumb' => $p->url('thumb'), 'medium' => $p->url('medium'), 'large' => $p->url('large'), 'width' => $p->width, 'height' => $p->height];

        return [
            'id' => $this->id,
            'title' => $this->title,
            'price' => $this->price,
            'currency' => 'USD',
            'negotiable' => $this->price_negotiable,
            'condition' => $this->condition,
            'category' => ['id' => $this->category->id, 'slug' => $this->category->slug, 'name' => $this->category->label],
            'city' => ['id' => $this->city->id, 'slug' => $this->city->slug, 'name' => $this->city->label],
            'area' => $this->whenLoaded('area', fn () => $this->area?->label),
            'cover' => $this->cover ? $photo($this->cover) : null,
            'published_at' => $this->published_at?->toIso8601String(),
            'url' => $this->url(),
            $this->mergeWhen($detail, fn () => [
                'description' => $this->description,
                'fields' => $this->fields ?? [],
                'photos' => $this->photos->map($photo)->values(),
                'seller' => ['name' => $this->user->name, 'member_since' => $this->user->created_at?->toDateString()],
                'expires_at' => $this->expires_at?->toIso8601String(),
            ]),
        ];
    }
}
