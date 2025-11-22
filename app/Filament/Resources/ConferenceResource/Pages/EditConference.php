<?php

namespace App\Filament\Resources\ConferenceResource\Pages;

use App\Filament\Resources\ConferenceResource;
use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditConference extends EditRecord
{
    protected static string $resource = ConferenceResource::class;

    public function getHeading(): string
    {
        if (! $this->record->isRejected()) {
            return parent::getHeading();
        }

        return parent::getHeading().' (rejected)';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('Reject')
                ->color('danger')
                ->action(fn () => $this->record->reject())
                ->visible(fn () => ! $this->record->isRejected()),
            Action::make('Restore')
                ->action(fn () => $this->record->restore())
                ->visible(fn () => $this->record->isRejected()),
        ];
    }
}
