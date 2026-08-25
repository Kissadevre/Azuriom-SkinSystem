<?php

namespace Azuriom\Plugin\SkinSystem\Commands;

use Azuriom\Plugin\SkinSystem\Models\SavedSkin;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinRevision;
use Azuriom\Plugin\SkinSystem\Services\SkinStorage;
use Azuriom\Plugin\SkinSystem\Services\UserSkinLock;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\DB;

class CleanupSkinRevisions extends Command
{
    public const DEFAULT_RETENTION_DAYS = 30;

    protected $signature = 'skinsystem:cleanup {--days=30 : Retain superseded revisions for this many days}';

    protected $description = 'Delete expired SkinSystem revisions and unreferenced PNG blobs.';

    public function handle(SkinStorage $storage, UserSkinLock $lock): int
    {
        $retentionDays = filter_var($this->option('days'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 3650],
        ]);

        if ($retentionDays === false) {
            $this->error('The --days option must be an integer between 1 and 3650.');

            return self::INVALID;
        }

        $cutoff = now()->subDays($retentionDays);
        $deletedRevisions = 0;
        $deletedFiles = 0;
        $busyUsers = 0;

        SkinRevision::query()
            ->where('created_at', '<', $cutoff)
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->chunkById(100, function ($revisions) use (
                $cutoff,
                $storage,
                $lock,
                &$deletedRevisions,
                &$deletedFiles,
                &$busyUsers,
            ) {
                foreach ($revisions as $revision) {
                    try {
                        $lock->runForUserId((int) $revision->user_id, function () use (
                            $revision,
                            $cutoff,
                            $storage,
                            &$deletedRevisions,
                            &$deletedFiles,
                        ) {
                            $path = DB::transaction(function () use (
                                $revision,
                                $cutoff,
                            ) {
                                $expired = SkinRevision::query()
                                    ->whereKey($revision->getKey())
                                    ->where('created_at', '<', $cutoff)
                                    ->first();

                                if ($expired === null || Skin::query()
                                    ->where('user_id', $expired->user_id)
                                    ->where('revision', $expired->revision)
                                    ->exists()) {
                                    return null;
                                }

                                $path = $expired->file;
                                $expired->delete();

                                return $path;
                            }, 3);

                            if ($path !== null) {
                                $deletedRevisions++;

                                if ($this->deleteUnreferencedFile($path, $cutoff, $storage)) {
                                    $deletedFiles++;
                                }
                            }
                        });
                    } catch (LockTimeoutException) {
                        $busyUsers++;
                    }
                }
            });

        foreach ($storage->disk()->allFiles('skinsystem/skins') as $path) {
            if (preg_match('#^skinsystem/skins/([1-9][0-9]{0,9})/[a-f0-9]{64}\.png$#D', $path, $matches) !== 1) {
                continue;
            }

            try {
                $lock->runForUserId((int) $matches[1], function () use (
                    $path,
                    $cutoff,
                    $storage,
                    &$deletedFiles,
                ) {
                    if ($this->deleteUnreferencedFile($path, $cutoff, $storage)) {
                        $deletedFiles++;
                    }
                });
            } catch (LockTimeoutException) {
                $busyUsers++;
            }
        }

        $this->info(
            "Deleted {$deletedRevisions} expired revisions and {$deletedFiles} unreferenced skin files."
        );

        if ($busyUsers > 0) {
            $this->warn("Skipped {$busyUsers} busy skin operations; the next scheduled run will retry them.");
        }

        return self::SUCCESS;
    }

    private function deleteUnreferencedFile(
        string $path,
        CarbonInterface $cutoff,
        SkinStorage $storage,
    ): bool {
        if (Skin::query()->where('file', $path)->exists()
            || SavedSkin::query()->where('file', $path)->exists()
            || SkinRevision::query()->where('file', $path)->exists()) {
            return false;
        }

        $disk = $storage->disk();

        if (! $disk->exists($path) || $disk->lastModified($path) >= $cutoff->getTimestamp()) {
            return false;
        }

        return $disk->delete($path);
    }
}
