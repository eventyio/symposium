<?php

namespace App\Models;

use Creativeorange\Gravatar\Facades\Gravatar;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\HasApiTokens;
use Laravel\Scout\Searchable;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;
    use HasFactory;
    use HasRelationships;
    use Notifiable;
    use Searchable;

    public const ADMIN_ROLE = 1;

    public const PROFILE_PICTURE_THUMB_PATH = 'profile_pictures/thumbs/';

    public const PROFILE_PICTURE_HIRES_PATH = 'profile_pictures/hires/';

    protected $hidden = ['password', 'remember_token'];

    protected $fillable = ['email', 'password', 'name'];

    public static function searchPublicSpeakers($query)
    {
        if (! $query) {
            return static::whereHasPublicProfile();
        }

        return static::search($query)
            ->query(fn ($query) => $query->whereHasPublicProfile());
    }

    protected static function boot()
    {
        parent::boot();

        // Cascade deletes
        static::deleting(function ($user) {
            $user->talks()->delete();
            // $user->conferences()->delete(); // Not sure if we want to do this.
            $user->bios()->delete();

            if ($user->profile_picture && strpos($user->profile_picture, '/') === false) {
                Storage::delete(self::PROFILE_PICTURE_THUMB_PATH.$user->profile_picture);
                Storage::delete(self::PROFILE_PICTURE_HIRES_PATH.$user->profile_picture);
            }

            DB::table('favorites')->where('user_id', $user->id)->delete();
            DB::table('dismissed_conferences')->where('user_id', $user->id)->delete();
        });
    }

    public function scopeWhereHasPublicProfile($query)
    {
        $query->where('enable_profile', true)
            ->whereNotNull('profile_slug');
    }

    public function scopeWantsNotifications($query)
    {
        return $query->where('wants_notifications', true);
    }

    public function scopeWhereFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function isAdmin()
    {
        return $this->role == self::ADMIN_ROLE;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }

    public function talks(): HasMany
    {
        return $this->hasMany(Talk::class, 'author_id');
    }

    public function talkRevisions(): HasManyThrough
    {
        return $this->hasManyThrough(
            TalkRevision::class,
            Talk::class,
            'author_id',
        );
    }

    public function submissions()
    {
        return $this->hasManyDeepFromRelations(
            $this->talkRevisions(),
            (new TalkRevision)->submissions(),
        );
    }

    public function archivedTalks(): HasMany
    {
        return $this->hasMany(Talk::class, 'author_id')
            ->withoutGlobalScope('active')
            ->archived();
    }

    public function bios(): HasMany
    {
        return $this->hasMany(Bio::class)->orderBy('nickname');
    }

    public function conferences(): HasMany
    {
        return $this->hasMany(Conference::class, 'author_id');
    }

    public function dismissedConferences()
    {
        return $this->belongstoMany(Conference::class, 'dismissed_conferences')->withTimestamps();
    }

    public function favoritedConferences()
    {
        return $this->belongstoMany(Conference::class, 'favorites')->withTimestamps();
    }

    public function updateProfilePicture($filename)
    {
        $this->profile_picture = $filename;

        return $this->save();
    }

    public function getProfilePictureThumbAttribute()
    {
        if (! $this->profile_picture) {
            return Gravatar::get($this->email, 'profile');
        }

        return asset('/storage/'.self::PROFILE_PICTURE_THUMB_PATH.$this->profile_picture);
    }

    public function getProfilePictureHiresAttribute()
    {
        if (! $this->profile_picture) {
            return Gravatar::get($this->email, 'hire');
        }

        return asset('/storage/'.self::PROFILE_PICTURE_HIRES_PATH.$this->profile_picture);
    }

    public function toSearchableArray()
    {
        return [
            'name' => $this->name,
            'location' => $this->location,
            'neighborhood' => $this->neighborhood,
            'sublocality' => $this->sublocality,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
        ];
    }

    public function social(): HasMany
    {
        return $this->hasMany(UserSocial::class);
    }

    public function hasSocialLinked($service)
    {
        return (bool) $this->social->where('service', $service)->count();
    }
}
