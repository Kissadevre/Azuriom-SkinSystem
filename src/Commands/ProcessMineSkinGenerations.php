<?php

namespace Azuriom\Plugin\SkinSystem\Commands;

use Azuriom\Plugin\SkinSystem\Models\MineSkinGeneration;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Services\SkinDeliveryService;
use Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings;
use Azuriom\Plugin\SkinSystem\Services\UserSkinLock;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Throwable;

class ProcessMineSkinGenerations extends Command
{
    protected $signature = 'skinsystem:mineskin:process {--limit=25 : Maximum due jobs to process}';

    protected $description = 'Poll due MineSkin jobs and submit completed appearances to SkinsRestorer.';

    public function handle(SkinDeliveryService $delivery, UserSkinLock $lock): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 250],
        ]);

        if ($limit === false) {
            $this->error('The --limit option must be an integer between 1 and 250.');

            return self::INVALID;
        }

        $generations = MineSkinGeneration::query()
            ->whereIn('status', [
                MineSkinGeneration::STATUS_PENDING,
                MineSkinGeneration::STATUS_PROCESSING,
            ])
            ->where(function ($query) {
                $query->whereNull('next_poll_at')->orWhere('next_poll_at', '<=', now());
            })
            ->orderBy('next_poll_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;
        $busy = 0;
        $failed = 0;

        foreach ($generations as $generation) {
            try {
                $lock->runForUserId((int) $generation->user_id, function () use (
                    $generation,
                    $delivery,
                    &$processed,
                ) {
                    $skin = Skin::query()
                        ->where('user_id', $generation->user_id)
                        ->where('revision', $generation->skin_revision)
                        ->where('delivery_strategy', SkinSystemSettings::DELIVERY_MINESKIN)
                        ->first();

                    if ($skin === null) {
                        return;
                    }

                    $delivery->advanceAndApply($skin, $skin->user()->firstOrFail());
                    $processed++;
                });
            } catch (LockTimeoutException) {
                $busy++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $this->info("Processed {$processed} MineSkin jobs; {$busy} busy and {$failed} failed unexpectedly.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
