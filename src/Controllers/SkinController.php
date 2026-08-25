<?php

namespace Azuriom\Plugin\SkinSystem\Controllers;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\SkinSystem\Requests\StoreSkinRequest;
use Azuriom\Plugin\SkinSystem\Services\ManageSkin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SkinController extends Controller
{
    /**
     * Store or replace the current user's skin.
     */
    public function store(StoreSkinRequest $request, ManageSkin $manager): RedirectResponse
    {
        $result = $manager->store(
            $request->user(),
            $request->file('skin'),
            $request->string('variant')->toString(),
        );

        $message = $result['changed'] ? 'updated' : 'unchanged';

        return to_route('skinsystem.index')
            ->with('success', trans("skinsystem::messages.status.{$message}"));
    }

    /**
     * Remove the current user's active skin.
     */
    public function destroy(Request $request, ManageSkin $manager): RedirectResponse
    {
        $manager->delete($request->user());

        return to_route('skinsystem.index')
            ->with('success', trans('skinsystem::messages.status.deleted'));
    }
}
