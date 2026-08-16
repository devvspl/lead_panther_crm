<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $guarded = [];

    /**
     * Render template body replacing {{token}} placeholders.
     */
    public function render(array $tokens): string
    {
        $content = $this->body ?? '';
        foreach ($tokens as $key => $val) {
            $content = str_replace("{{" . $key . "}}", (string) $val, $content);
        }
        return $content;
    }
}
