<?php

namespace App\Models;

use Database\Factories\ReceiverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['name'])]
#[Hidden(['user_id', 'created_at', 'updated_at', 'pivot', 'media'])]
class Receiver extends Model implements HasMedia
{
    /** @use HasFactory<ReceiverFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->useDisk('local')
            ->storeConversionsOnDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('avatar')
            ->performOnCollections('image')
            ->nonOptimized()
            ->nonQueued()
            ->fit(Fit::Crop, 200, 200)
            ->format('webp')
            ->quality(85);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function defaultTags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps()->orderBy('name')->orderBy('tags.id');
    }
}
