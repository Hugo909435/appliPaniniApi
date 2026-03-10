<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Medal extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'image',
        'icon',
        'rarity',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['image_url', 'icon_url'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'medal_user')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            if (str_starts_with($this->image, 'http') || str_starts_with($this->image, '/')) {
                return $this->image;
            }

            if (Storage::disk('public')->exists($this->image)) {
                return Storage::url($this->image);
            }

            if (file_exists(public_path($this->image))) {
                return '/'.$this->image;
            }
        }

        $fallback = match ($this->rarity) {
            'legendary' => '/assets/medals/medal-legendary.svg',
            'epic' => '/assets/medals/medal-epic.svg',
            'rare' => '/assets/medals/medal-rare.svg',
            'uncommon' => '/assets/medals/medal-uncommon.svg',
            default => '/assets/medals/medal-common.svg',
        };

        return $fallback;
    }

    public function getIconUrlAttribute()
    {
        if ($this->icon) {
            if (str_starts_with($this->icon, 'http') || str_starts_with($this->icon, '/')) {
                return $this->icon;
            }

            if (Storage::disk('public')->exists($this->icon)) {
                return Storage::url($this->icon);
            }

            if (file_exists(public_path($this->icon))) {
                return '/'.$this->icon;
            }
        }

        return '/assets/medals/icons/medal-icon-default.svg';
    }
}
