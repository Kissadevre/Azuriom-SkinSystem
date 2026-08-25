<?php

namespace Azuriom\Plugin\SkinSystem\Controllers;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\User;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncState;
use Azuriom\Plugin\SkinSystem\Requests\StoreSkinRequest;
use Azuriom\Plugin\SkinSystem\Services\ManageSkin;
use Azuriom\Plugin\SkinSystem\Services\SkinSynchronizer;
use Azuriom\Plugin\SkinSystem\Services\UserSkinLock;
use Azuriom\Plugin\SkinSystem\Support\SyncResult;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SkinController extends Controller
{
    /**
     * Store or replace the current user's skin.
     */
    public function store(
        StoreSkinRequest $request,
        ManageSkin $manager,
        SkinSynchronizer $synchronizer,
        UserSkinLock $lock,
    ): RedirectResponse {
        return $this->withUserLock($request->user(), $lock, function () use ($request, $manager, $synchronizer) {
            $result = $manager->store(
                $request->user(),
                $request->file('skin'),
                $request->string('variant')->toString(),
            );

            if (! $result['changed']) {
                return to_route('skinsystem.index')
                    ->with('success', trans('skinsystem::messages.status.unchanged'));
            }

            return $this->withSyncFeedback(
                to_route('skinsystem.index'),
                $synchronizer->apply($result['skin'], $request->user()),
                trans('skinsystem::messages.status.updated'),
            );
        });
    }

    /**
     * Manually submit the latest set or clear operation again.
     */
    public function sync(
        Request $request,
        SkinSynchronizer $synchronizer,
        UserSkinLock $lock,
    ): RedirectResponse {
        return $this->withUserLock($request->user(), $lock, function () use ($request, $synchronizer) {
            $skin = Skin::query()
                ->where('user_id', $request->user()->getKey())
                ->first();

            if ($skin !== null) {
                return $this->withSyncFeedback(
                    to_route('skinsystem.index'),
                    $synchronizer->apply($skin, $request->user()),
                );
            }

            $state = SkinSyncState::query()
                ->where('user_id', $request->user()->getKey())
                ->where('action', SkinSyncState::ACTION_CLEAR)
                ->firstOrFail();

            return $this->withSyncFeedback(
                to_route('skinsystem.index'),
                $synchronizer->clear($request->user(), $state->skin_revision),
                submittedKey: 'clear_submitted',
            );
        });
    }

    /**
     * Remove the active skin and preserve a recoverable clear operation.
     */
    public function destroy(
        Request $request,
        ManageSkin $manager,
        SkinSynchronizer $synchronizer,
        UserSkinLock $lock,
    ): RedirectResponse {
        return $this->withUserLock($request->user(), $lock, function () use ($request, $manager, $synchronizer) {
            $state = $manager->delete($request->user());
            $redirect = to_route('skinsystem.index');

            if ($state === null) {
                return $redirect->with('success', trans('skinsystem::messages.status.deleted'));
            }

            return $this->withSyncFeedback(
                $redirect,
                $synchronizer->clear($request->user(), $state->skin_revision),
                trans('skinsystem::messages.status.deleted'),
                'clear_submitted',
            );
        });
    }

    private function withUserLock(User $user, UserSkinLock $lock, Closure $callback): RedirectResponse
    {
        try {
            return $lock->run($user, $callback);
        } catch (LockTimeoutException) {
            return to_route('skinsystem.index')
                ->with('warning', trans('skinsystem::messages.sync.errors.operation_busy'));
        }
    }

    private function withSyncFeedback(
        RedirectResponse $redirect,
        SyncResult $result,
        ?string $baseMessage = null,
        string $submittedKey = 'submitted',
    ): RedirectResponse {
        $syncMessage = trans($result->error === null
            ? "skinsystem::messages.sync.feedback.{$submittedKey}"
            : "skinsystem::messages.sync.errors.{$result->error}");

        if ($result->status === SkinSyncState::STATUS_SUBMITTED) {
            return $redirect->with('success', trim(($baseMessage ?? '').' '.$syncMessage));
        }

        if ($baseMessage !== null) {
            $redirect->with('success', $baseMessage);
        }

        $level = $result->status === SkinSyncState::STATUS_FAILED ? 'error' : 'warning';

        return $redirect->with($level, $syncMessage);
    }
}
