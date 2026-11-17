<?php
namespace Tests\Feature\Notifications;

use Tests\TestCase;
use Illuminate\Support\Facades\{Storage, Crypt};
use App\Console\Commands\ConfigureBrowserPush;
use Symfony\Component\Console\Tester\CommandTester;

class BrowserPushSetupTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        config(['webpush.public_key' => null, 'webpush.private_key' => null]);
    }
    private function missingPackageCommand(): CommandTester {
        $command = new class extends ConfigureBrowserPush {
            protected function libraryAvailable(): bool { return false; }
        };
        $command->setLaravel($this->app);
        return new CommandTester($command);
    }
    public function test_missing_composer_package_is_not_misreported_as_openssl_failure(): void {
        $tester = $this->missingPackageCommand();
        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('Composer package is missing', $tester->getDisplay());
        $this->assertStringContainsString('composer install --no-dev --optimize-autoloader', $tester->getDisplay());
        Storage::disk('local')->assertMissing('webpush-vapid.json');
    }
    public function test_diagnostics_do_not_generate_or_disclose_keys(): void {
        $tester = $this->missingPackageCommand();
        $this->assertSame(0, $tester->execute(['--diagnose' => true]));
        $this->assertStringContainsString('Web Push Composer package: missing', $tester->getDisplay());
        Storage::disk('local')->assertMissing('webpush-vapid.json');
    }
    public function test_existing_encrypted_keys_are_preserved_without_printing_private_material(): void {
        $cipher = Crypt::encryptString(json_encode(['publicKey' => 'existing-public', 'privateKey' => 'keep-this-private']));
        Storage::disk('local')->put('webpush-vapid.json', $cipher);
        $tester = $this->missingPackageCommand();
        $this->assertSame(0, $tester->execute([]));
        $this->assertSame($cipher, Storage::disk('local')->get('webpush-vapid.json'));
        $this->assertStringNotContainsString('keep-this-private', $tester->getDisplay());
    }
}
