<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Plugin\SkinSystem\Models\MineSkinGeneration;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncState;
use Azuriom\Plugin\SkinSystem\Services\ManageSkin;
use Azuriom\Plugin\SkinSystem\Services\MineSkinClient;
use Azuriom\Plugin\SkinSystem\Services\MineSkinGenerationManager;
use Azuriom\Plugin\SkinSystem\Services\SkinDeliveryService;
use Azuriom\Plugin\SkinSystem\Services\SkinProcessor;
use Azuriom\Plugin\SkinSystem\Services\SkinStorage;
use Azuriom\Plugin\SkinSystem\Services\SkinSynchronizer;
use Azuriom\Plugin\SkinSystem\Services\SkinSyncTargetRegistry;
use Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings;
use Azuriom\Plugin\SkinSystem\Services\SkinsRestorerCommandBuilder;
use Azuriom\Plugin\SkinSystem\Tests\Fakes\ConfigurableSkinSystemSettings;
use Azuriom\Plugin\SkinSystem\Tests\Fakes\RecordingServerBridge;
use Azuriom\Plugin\SkinSystem\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class MineSkinDeliveryTest extends TestCase
{
    public function test_mineskin_delivery_waits_for_generation_before_dispatching_the_result_url(): void
    {
        $user = $this->createUser();
        $server = $this->createServer('mc-rcon');
        $settings = $this->settings($server->id, SkinSystemSettings::DELIVERY_MINESKIN);
        Http::fake([
            MineSkinClient::BASE_URL.'/queue' => Http::sequence()
                ->push(['job' => ['id' => 'job-123', 'status' => 'pending']], 202),
            MineSkinClient::BASE_URL.'/queue/job-123' => Http::response([
                'job' => ['id' => 'job-123', 'status' => 'completed'],
                'skin' => ['uuid' => '123456781234423482341234567890ab'],
            ]),
        ]);

        $skin = $this->manager($settings)
            ->store($user, $this->uploadedSkin(), Skin::VARIANT_CLASSIC)['skin'];
        $delivery = $this->delivery($settings);
        $queued = $delivery->apply($skin, $user);

        $this->assertSame(SkinSyncState::STATUS_PENDING, $queued->status);
        $this->assertSame('mineskin_processing', $queued->error);
        $this->assertSame(SkinSystemSettings::DELIVERY_MINESKIN, $skin->delivery_strategy);
        $this->assertSame(MineSkinGeneration::STATUS_PENDING, MineSkinGeneration::query()->sole()->status);
        $this->assertSame([], RecordingServerBridge::$calls);

        MineSkinGeneration::query()->update(['next_poll_at' => now()->subSecond()]);
        $submitted = $delivery->advanceAndApply($skin, $user);

        $this->assertSame(SkinSyncState::STATUS_SUBMITTED, $submitted->status);
        $this->assertSame(MineSkinGeneration::STATUS_COMPLETED, MineSkinGeneration::query()->sole()->status);
        $this->assertSame(
            ['skin set "https://minesk.in/123456781234423482341234567890ab" '.self::PRIMARY_UUID.' classic'],
            RecordingServerBridge::$calls[0]['commands'],
        );
    }

    public function test_hybrid_delivery_stays_direct_until_a_cape_is_selected(): void
    {
        $user = $this->createUser();
        $server = $this->createServer('mc-rcon');
        $settings = $this->settings($server->id, SkinSystemSettings::DELIVERY_HYBRID);
        Http::fake();

        $skin = $this->manager($settings)
            ->store($user, $this->uploadedSkin(), Skin::VARIANT_CLASSIC)['skin'];
        $result = $this->delivery($settings)->apply($skin, $user);

        $this->assertSame(SkinSystemSettings::DELIVERY_DIRECT, $skin->delivery_strategy);
        $this->assertSame(SkinSyncState::STATUS_SUBMITTED, $result->status);
        $this->assertStringContainsString('/api/skinsystem/skins/', RecordingServerBridge::$calls[0]['commands'][0]);
        $this->assertSame(0, MineSkinGeneration::query()->count());
        Http::assertNothingSent();
    }

    private function settings(int $serverId, string $mode): ConfigurableSkinSystemSettings
    {
        $settings = new ConfigurableSkinSystemSettings;
        $settings->selectedServerId = $serverId;
        $settings->selectedDeliveryMode = $mode;
        $settings->configuredMineSkinApiKey = 'msk_test_key';

        return $settings;
    }

    private function manager(ConfigurableSkinSystemSettings $settings): ManageSkin
    {
        return new ManageSkin(
            app(SkinProcessor::class),
            app(SkinStorage::class),
            $settings,
            app(SkinsRestorerCommandBuilder::class),
            app(SkinSyncTargetRegistry::class),
        );
    }

    private function delivery(ConfigurableSkinSystemSettings $settings): SkinDeliveryService
    {
        $generations = new MineSkinGenerationManager(
            app(MineSkinClient::class),
            app(SkinStorage::class),
            $settings,
        );
        $synchronizer = new SkinSynchronizer(
            $settings,
            app(SkinsRestorerCommandBuilder::class),
            app(SkinSyncTargetRegistry::class),
        );

        return new SkinDeliveryService($settings, $generations, $synchronizer);
    }

    private function uploadedSkin(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'skinsystem-mineskin-');
        $image = imagecreatetruecolor(64, 64);
        imagefill($image, 0, 0, imagecolorallocate($image, 80, 120, 160));
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'skin.png', 'image/png', null, true);
    }
}
