<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Storage, Crypt};
use Minishlink\WebPush\VAPID;
class ConfigureBrowserPush extends Command {
    protected $signature = 'webpush:configure {--diagnose : Show PHP and push dependency readiness without exposing keys}';
    protected $description = 'Create persistent encrypted browser push keys without exposing private keys';
    protected function libraryAvailable(): bool { return class_exists(VAPID::class); }
    public function handle(): int {
        if ($this->option('diagnose')) {
            $this->line('CLI PHP: '.PHP_VERSION);
            $this->line('OpenSSL extension: '.(extension_loaded('openssl') ? 'available' : 'missing'));
            $this->line('Web Push Composer package: '.($this->libraryAvailable() ? 'available' : 'missing'));
            $this->line('OpenSSL config override: '.((getenv('OPENSSL_CONF') || config('webpush.openssl_conf')) ? 'set' : 'not set'));
            $this->line('Encrypted key file: '.(Storage::disk('local')->exists('webpush-vapid.json') ? 'present' : 'absent'));
            return self::SUCCESS;
        }
        if ((bool) config('webpush.public_key') !== (bool) config('webpush.private_key')) {
            $this->error('Set both environment VAPID keys or clear both to use private file storage.'); return self::FAILURE;
        }
        if (Storage::disk('local')->exists('webpush-vapid.json') || config('webpush.private_key')) {
            $this->info('Browser push keys already exist; existing subscriptions were preserved.'); return self::SUCCESS;
        }
        if (! $this->libraryAvailable()) {
            $this->error('Web Push Composer package is missing on this server.');
            $this->line('Run: composer install --no-dev --optimize-autoloader');
            $this->line('Then run: php artisan webpush:configure');
            return self::FAILURE;
        }
        if (! extension_loaded('openssl')) {
            $this->error('The CLI PHP OpenSSL extension is missing. Use the same PHP 8.2+ installation as the website, with OpenSSL enabled.');
            return self::FAILURE;
        }
        app(\App\Services\BrowserPushService::class)->prepareCrypto();
        try { $keys = VAPID::createVapidKeys(); }
        catch (\Throwable $e) {
            $this->error('Web Push key generation failed ('.get_class($e).').');
            $this->line('CLI PHP: '.PHP_VERSION.'; OpenSSL: '.(defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'unavailable'));
            $count = 0;
            while ($count++ < 4 && ($error = openssl_error_string())) $this->line('OpenSSL diagnostic: '.$error);
            $this->line('Run php artisan webpush:configure --diagnose. Check the CLI PHP version and OpenSSL configuration.');
            $this->line('On Linux, use the real openssl.cnf path from your host (commonly /etc/ssl/openssl.cnf or /etc/pki/tls/openssl.cnf). Set OPENSSL_CONF before starting PHP.');
            return self::FAILURE;
        }
        $keys['subject'] = config('webpush.subject') ?: 'mailto:'.config('mail.from.address');
        Storage::disk('local')->put('webpush-vapid.json', Crypt::encryptString(json_encode($keys, JSON_THROW_ON_ERROR)));
        $this->info('Browser push keys saved in private encrypted storage. Keep this file and APP_KEY persistent.');
        return self::SUCCESS;
    }
}
