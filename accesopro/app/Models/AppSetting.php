<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/** Ajuste clave/valor, cifrado en la base de datos (p. ej. el refresh token de YouTube). */
final class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public static function get(string $key): ?string
    {
        $value = self::find($key)?->value;

        return $value === null ? null : Crypt::decryptString($value);
    }

    public static function put(string $key, ?string $value): void
    {
        if ($value === null) {
            self::whereKey($key)->delete();

            return;
        }

        self::updateOrCreate(['key' => $key], ['value' => Crypt::encryptString($value)]);
    }
}
