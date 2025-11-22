<?php

namespace App\Models;

use App\Casts\Coordinates;
use App\Casts\SpeakerPackage;
use App\Casts\Url;
use App\Notifications\ConferenceIssueReported;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Conference extends UuidBase
{
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    protected $table = 'conferences';

    protected $guarded = [
        'id',
    ];

    protected $fillable = [
        'author_id',
        'title',
        'location',
        'location_name',
        'latitude',
        'longitude',
        'description',
        'url',
        'cfp_url',
        'starts_at',
        'ends_at',
        'cfp_starts_at',
        'cfp_ends_at',
        'is_approved',
        'is_shared',
        'calling_all_papers_id',
        'has_cfp',
        'speaker_package',
    ];

    protected $attributes = [
        'has_cfp' => true,
    ];

    public static function searchQuery($search, $query)
    {
        if (! $search) {
            return tap(static::query(), $query);
        }

        return static::search($search)->query($query);
    }

    public static function boot()
    {
        parent::boot();

        self::creating(function ($conference) {
            // only approve conferences that are new imports
            if ($conference->calling_all_papers_id) {
                $conference->is_approved = true;
            }
        });

        static::addGlobalScope('notRejected', function ($builder) {
            $builder->whereNull('rejected_at');
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function usersDismissed()
    {
        return $this->belongstoMany(User::class, 'dismissed_conferences')->withTimestamps();
    }

    public function usersFavorited()
    {
        return $this->belongstoMany(User::class, 'favorites')->withTimestamps();
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ConferenceIssue::class);
    }

    public function openIssues()
    {
        return $this->issues()->whereOpen();
    }

    public function scopeWhereCfpIsFuture($query)
    {
        return $query
            ->where('has_cfp', true)
            ->where('cfp_starts_at', '>', now())
            ->where('cfp_ends_at', '>', now());
    }

    public function scopeFuture($query)
    {
        return $query
            ->where('starts_at', '>', Carbon::now());
    }

    public function scopeWhereCfpIsOpen($query)
    {
        return $query
            ->where('has_cfp', true)
            ->where('cfp_starts_at', '<=', now())
            ->where('cfp_ends_at', '>', now());
    }

    public function scopeWhereCfpIsUnclosed($query)
    {
        $query
            ->where('has_cfp', true)
            ->where('cfp_ends_at', '>', now());
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeWhereNotDismissedBy($query, $user)
    {
        return $query->whereDoesntHave('usersDismissed', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        });
    }

    public function scopeNotShared($query)
    {
        return $query->where('is_shared', false);
    }

    public function scopeWhereFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeWhereHasDates($query)
    {
        $query->whereNotNull(['starts_at', 'ends_at']);
    }

    public function scopeWhereDateDuring($query, $year, $month, $dateColumn)
    {
        tap(now()->year($year)->month($month), function ($date) use ($query, $dateColumn) {
            $query->whereDate($dateColumn, '<=', $date->endOfMonth())
                ->whereDate($dateColumn, '>=', $date->startOfMonth());
        });
    }

    public function scopeWhereHasCfpStart($query)
    {
        $query->whereNotNull('cfp_starts_at');
    }

    public function scopeWhereHasCfpEnd($query)
    {
        $query->whereNotNull('cfp_ends_at');
    }

    public function scopeWhereFavoritedBy($query, $user)
    {
        $query->whereHas('usersFavorited', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        });
    }

    public function scopeWhereDismissedBy($query, $user)
    {
        $query->whereHas('usersDismissed', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        });
    }

    public function shouldBeSearchable(): bool
    {
        return $this->starts_at > Carbon::now() && ! $this->isRejected();
    }

    /**
     * Whether CFP is currently open
     *
     * @deprecated
     */
    public function cfpIsOpen(): bool
    {
        return $this->isCurrentlyAcceptingProposals();
    }

    /**
     * Whether conference is currently accepted talk proposals
     */
    public function isCurrentlyAcceptingProposals(): bool
    {
        if (! $this->hasAnnouncedCallForProposals()) {
            return false;
        }

        return Carbon::today()->between($this->getAttribute('cfp_starts_at'), $this->getAttribute('cfp_ends_at'));
    }

    public function getLinkAttribute()
    {
        return route('conferences.show', $this->id);
    }

    public function getEventDatesDisplayAttribute()
    {
        if (! $this->starts_at) {
            return null;
        }

        if (! $this->ends_at || $this->starts_at->isSameDay($this->ends_at)) {
            return $this->starts_at->format('F j, Y');
        }

        return $this->starts_at->format('M j Y').' - '.$this->ends_at->format('M j Y');
    }

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'location' => $this->location,
        ];
    }

    public function isDismissedBy(User $user)
    {
        return $user->dismissedConferences->contains($this->id);
    }

    public function isFavoritedBy(User $user)
    {
        return $user->favoritedConferences->contains($this->id);
    }

    public function isFlagged()
    {
        return $this->open_issues_count > 0;
    }

    public function isRejected()
    {
        return (bool) $this->rejected_at;
    }

    /**
     * Return all talks from this user that were submitted to this conference
     */
    public function mySubmissions()
    {
        $talks = auth()->user()->talks;

        return $this->submissions->filter(function ($submission) use ($talks) {
            return $talks->contains($submission->talkRevision->talk);
        });
    }

    public function appliedTo()
    {
        return $this->mySubmissions()->count() > 0;
    }

    public function startsAtDisplay()
    {
        return $this->startsAtSet() ? $this->starts_at->format('D M j, Y') : '[Date not set]';
    }

    public function endsAtDisplay()
    {
        return $this->endsAtSet() ? $this->ends_at->format('D M j, Y') : '[Date not set]';
    }

    public function cfpStartsAtDisplay()
    {
        return $this->cfpStartsAtSet() ? $this->cfp_starts_at->toFormattedDateString() : '[Date not set]';
    }

    public function cfpEndsAtDisplay()
    {
        return $this->cfpEndsAtSet() ? $this->cfp_ends_at->toFormattedDateString() : '[Date not set]';
    }

    public function startsAtSet()
    {
        return $this->starts_at;
    }

    public function endsAtSet()
    {
        return $this->ends_at;
    }

    public function cfpStartsAtSet()
    {
        return $this->cfp_starts_at;
    }

    public function cfpEndsAtSet()
    {
        return $this->cfp_ends_at;
    }

    public function reportIssue($reason, $note, User $user)
    {
        $issue = $this->issues()->create([
            'user_id' => $user->id,
            'reason' => $reason,
            'note' => $note,
        ]);

        (new TightenSlack)->notify(new ConferenceIssueReported($issue));
    }

    public function reject()
    {
        $this->rejected_at = now();
        $this->save();
    }

    public function restore()
    {
        $this->rejected_at = null;
        $this->save();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cfp_starts_at' => 'datetime',
            'cfp_ends_at' => 'datetime',
            'is_approved' => 'boolean',
            'is_shared' => 'boolean',
            'author_id' => 'integer',
            'url' => Url::class,
            'cfp_url' => Url::class,
            'has_cfp' => 'boolean',
            'speaker_package' => SpeakerPackage::class,
        ];
    }

    protected function coordinates(): Attribute
    {
        return Attribute::make(
            set: fn (Coordinates $coordinates) => [
                'latitude' => $coordinates->getLatitude(),
                'longitude' => $coordinates->getLongitude(),
            ],
        );
    }

    private function hasAnnouncedCallForProposals()
    {
        return (! is_null($this->cfp_starts_at)) && (! is_null($this->cfp_ends_at));
    }
}
