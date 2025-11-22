<?php

namespace App\Http\Requests;

use App\Casts\SpeakerPackage;
use App\Rules\ValidAmountForCurrentLocale;
use Cknow\Money\Money;
use Illuminate\Foundation\Http\FormRequest;

class SaveConferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required'],
            'description' => ['required'],
            'url' => ['required', 'url'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'has_cfp' => ['boolean'],
            'cfp_url' => ['nullable', 'prohibited_if:has_cfp,false', 'url'],
            'cfp_starts_at' => [
                'nullable',
                'prohibited_if:has_cfp,false',
                'date',
                'before:starts_at',
            ],
            'cfp_ends_at' => [
                'nullable',
                'prohibited_if:has_cfp,false',
                'date',
                'after:cfp_starts_at',
                'before:starts_at',
            ],
            'location' => ['nullable'],
            'location_name' => ['nullable'],
            'latitude' => ['nullable'],
            'longitude' => ['nullable'],
            'speaker_package' => ['nullable'],
            'speaker_package.currency' => function ($attribute, $value, $fail) {
                if (! Money::isValidCurrency($value)) {
                    $fail($attribute.' must be a valid currency type.');
                }
            },
            ...collect(SpeakerPackage::CATEGORIES)
                ->mapWithKeys(function ($category) {
                    return [
                        "speaker_package.{$category}" => [
                            'nullable',
                            new ValidAmountForCurrentLocale,
                        ],
                    ];
                }),
        ];
    }

    public function validated($key = null, $default = null)
    {
        return data_get($this->withSpeakerPackage(), $key, $default);
    }

    private function withSpeakerPackage()
    {
        $speakerPackage = new SpeakerPackage(
            parent::validated('speaker_package') ?? [],
        );

        return array_merge($this->validator->validated(), [
            'speaker_package' => $speakerPackage->count() ? $speakerPackage : null,
        ]);
    }
}
