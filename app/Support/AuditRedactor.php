<?php
namespace App\Support;

class AuditRedactor
{
    public static function sensitive(string $key): bool
    {
        $key = strtolower(preg_replace('/[^a-z0-9]/i', '', $key));
        return (bool) preg_match('/password|passwd|secret|token|authorization|cookie|apikey|privatekey|credential|otp|twofactor|recoverycode|csrf|signature|sessionid|sessionkey|^pin$|^code$/', $key);
    }

    public static function clean(mixed $value): mixed
    {
        if (is_array($value)) {
            $secretSetting = isset($value['key']) && is_string($value['key']) && self::sensitive($value['key']);
            foreach ($value as $key => $item) {
                $sensitive = self::sensitive((string) $key) && ! (strtolower((string) $key) === 'credentials' && is_array($item));
                $value[$key] = $sensitive || ($secretSetting && in_array($key, ['value', 'from_value', 'to_value']))
                    ? '********' : self::clean($item);
            }
            return $value;
        }
        if (! is_string($value)) return $value;
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return json_encode(self::clean($decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $value = preg_replace('/\bBearer\s+[^\s"\'<>]+/i', 'Bearer ********', $value);
        $value = preg_replace('/((?:password|passwd|secret|access[_-]?token|refresh[_-]?token|api[_-]?key|otp|token|signature|authorization)\s*[=:]\s*)[^\s&,;"\'<>]+/i', '$1********', $value);
        $value = preg_replace('/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/', '********', $value);
        return preg_replace('/(https?:\/\/)[^\s\/@]+:[^\s\/@]+@/i', '$1********@', $value);
    }

    public static function payload(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) $value = $decoded;
        }
        return self::clean($value);
    }
}
