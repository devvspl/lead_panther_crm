<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Storage, Crypt};
use Minishlink\WebPush\VAPID;
class ConfigureBrowserPush extends Command {
    protected $signature = 'webpush:configure';
    protected $description = 'Create persistent encrypted browser push keys without exposing private keys';
    public function handle(): int {
        if ((bool) config('webpush.public_key') !== (bool) config('webpush.private_key')) {
            $this->error('Set both environment VAPID keys or clear both to use private file storage.'); return self::FAILURE;
        }
        if (Storage::disk('local')->exists('webpush-vapid.json') || config('webpush.private_key')) {
            $this->info('Browser push keys already exist; existing subscriptions were preserved.'); return self::SUCCESS;
        }
        app(\App\Services\BrowserPushService::class)->prepareCrypto();
        try { $keys = VAPID::createVapidKeys(); }
        catch (\Throwable $e) { $this->error('OpenSSL P-256 key generation failed. Set WEB_PUSH_OPENSSL_CONF to your PHP OpenSSL configuration file.'); return self::FAILURE; }
        $keys['subject'] = config('webpush.subject') ?: 'mailto:'.config('mail.from.address');
        Storage::disk('local')->put('webpush-vapid.json', Crypt::encryptString(json_encode($keys, JSON_THROW_ON_ERROR)));
        $this->info('Browser push keys saved in private encrypted storage. Keep this file and APP_KEY persistent.');
        return self::SUCCESS;
    }
}
