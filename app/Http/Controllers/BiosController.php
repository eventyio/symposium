<?php

namespace App\Http\Controllers;

use App\Exceptions\ValidationException;
use App\Models\Bio;
use App\Services\CreateBioForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class BiosController extends Controller
{
    public function index(): View
    {
        $bios = auth()->user()->bios;

        return view('bios.index', [
            'bios' => $bios,
        ]);
    }

    public function create(): View
    {
        return view('bios.create', [
            'bio' => new Bio,
        ]);
    }

    public function store(): RedirectResponse
    {
        // @todo: Why is this here? Why aren't we validating like we do everywhere else?
        $form = CreateBioForm::fillOut(request()->input(), auth()->user());

        try {
            $bio = $form->complete();
        } catch (ValidationException $e) {
            return redirect('bios/create')
                ->withErrors($e->errors())
                ->withInput();
        }

        Session::flash('success-message', 'Successfully created new bio.');

        return redirect('/bios/'.$bio->id);
    }

    public function show($id): View
    {
        $bio = auth()->user()->bios()->findOrFail($id);

        return view('bios.show', [
            'bio' => $bio,
        ]);
    }

    public function edit($id): View
    {
        $bio = auth()->user()->bios()->findOrFail($id);

        return view('bios.edit', [
            'bio' => $bio,
        ]);
    }

    public function update($id, Request $request): RedirectResponse
    {
        request()->validate([
            'nickname' => 'required',
            'body' => 'required',
            'public' => '',
        ]);

        $bio = auth()->user()->bios()->findOrFail($id);

        $bio->nickname = $request->get('nickname');
        $bio->body = $request->get('body');
        $bio->public = $request->get('public');
        $bio->save();

        Session::flash('success-message', 'Successfully edited bio.');

        return redirect('bios/'.$bio->id);
    }

    public function destroy($id): RedirectResponse
    {
        $bio = auth()->user()->bios()->findOrFail($id);

        $bio->delete();

        Session::flash('success-message', 'Bio successfully deleted.');

        return redirect('bios');
    }
}
