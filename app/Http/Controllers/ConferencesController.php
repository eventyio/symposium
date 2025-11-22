<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveConferenceRequest;
use App\Models\Conference;
use App\Services\Currency;
use App\Transformers\TalkForConferenceTransformer as TalkTransformer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class ConferencesController extends Controller
{
    public function create(): View
    {
        return view('conferences.create', [
            'conference' => new Conference,
            'currencies' => Currency::all(),
        ]);
    }

    public function store(SaveConferenceRequest $request): RedirectResponse
    {
        $conference = Conference::create(array_merge($request->validated(), [
            'author_id' => auth()->user()->id,
        ]));

        Event::dispatch('new-conference', [$conference]);
        Session::flash('success-message', 'Successfully created new conference.');

        return redirect('conferences/'.$conference->id);
    }

    public function show($id)
    {
        if (auth()->guest()) {
            return $this->showPublic($id);
        }

        if (auth()->user()->isAdmin()) {
            $conference = Conference::withoutGlobalScope('notRejected')->findOrFail($id);
        } else {
            $conference = Conference::findOrFail($id);
        }

        $talks = auth()->user()->talks()->withCurrentRevision()->get()->sortByTitle()->map(function ($talk) use ($conference) {
            return TalkTransformer::transform($talk, $conference);
        });

        $conference->loadCount('openIssues');

        return view('conferences.show', [
            'conference' => $conference,
            'talks' => $talks,
        ]);
    }

    public function edit($id)
    {
        $conference = Conference::findOrFail($id);

        if ($conference->author_id !== auth()->id() && ! auth()->user()->isAdmin()) {
            Log::error('User '.auth()->user()->id." tried to edit a conference they don't own.");

            return redirect('/');
        }

        return view('conferences.edit', [
            'conference' => $conference,
            'currencies' => Currency::all(),
        ]);
    }

    public function update($id, SaveConferenceRequest $request): RedirectResponse
    {
        // @todo Update this to use ACL... gosh this app is old...
        $conference = Conference::findOrFail($id);

        if ($conference->author_id !== auth()->id() && ! auth()->user()->isAdmin()) {
            Log::error('User '.auth()->user()->id." tried to edit a conference they don't own.");

            return redirect('/');
        }

        $conference->fill($request->validated());

        if (auth()->user()->isAdmin()) {
            $conference->is_shared = $request->input('is_shared');
            $conference->is_approved = $request->input('is_approved');
        }

        $conference->save();

        Session::flash('success-message', 'Successfully edited conference.');

        return redirect('conferences/'.$conference->id);
    }

    public function destroy($id): RedirectResponse
    {
        $conference = auth()->user()->conferences()->findOrFail($id);
        $conference->delete();

        Session::flash('success-message', 'Conference successfully deleted.');

        return redirect('conferences');
    }

    private function showPublic($id)
    {
        $conference = Conference::approved()->findOrFail($id);
        $conference->loadCount('openIssues');

        return view('conferences.showPublic', [
            'conference' => $conference,
        ]);
    }
}
