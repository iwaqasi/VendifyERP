<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class System extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'system';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Cached result of whether the `system` table exists.
     *
     * Module service providers query this table during application boot
     * (via ModuleUtil::isModuleInstalled / System::getProperty). On a
     * FRESH database — before `php artisan migrate` has run — the table
     * does not exist yet and those queries crash the whole application.
     * These guards make boot safe on fresh installs and test databases.
     *
     * @var bool|null
     */
    private static $systemTableExists = null;

    /**
     * Check (once per process) whether the `system` table exists.
     *
     * @return bool
     */
    private static function systemTableExists(): bool
    {
        if (self::$systemTableExists === null) {
            try {
                self::$systemTableExists = Schema::hasTable('system');
            } catch (\Throwable $e) {
                // No DB connection / mid-migration state — treat as missing.
                self::$systemTableExists = false;
            }
        }

        return self::$systemTableExists;
    }

    /**
     * Return the value of the property
     *
     * @param $key string
     * @return mixed
     */
    public static function getProperty($key)
    {
        if (! self::systemTableExists()) {
            return null;
        }

        $row = System::where('key', $key)
                ->first();

        if (isset($row->value)) {
            return $row->value;
        } else {
            return null;
        }
    }

    /**
     * Return the value of the multiple properties
     *
     * @param $keys array
     * @return array
     */
    public static function getProperties($keys, $pluck = false)
    {
        if (! self::systemTableExists()) {
            return $pluck == true ? collect() : [];
        }

        if ($pluck == true) {
            return System::whereIn('key', $keys)
                ->pluck('value', 'key');
        } else {
            return System::whereIn('key', $keys)
                ->get()
                ->toArray();
        }
    }

    /**
     * Return the system default currency details
     *
     * @param void
     * @return object|null
     */
    public static function getCurrency()
    {
        if (! self::systemTableExists()) {
            return null;
        }

        $c_id = System::where('key', 'app_currency_id')
                ->first()
                ->value;

        $currency = Currency::find($c_id);

        return $currency;
    }

    /**
     * Set the property
     *
     * @param $key
     * @param $value
     * @return void
     */
    public static function setProperty($key, $value)
    {
        System::where('key', $key)
            ->update(['value' => $value]);
    }

    /**
     * Remove the specified property
     *
     * @param $key
     * @return void
     */
    public static function removeProperty($key)
    {
        System::where('key', $key)
            ->delete();
    }

    /**
     * Add a new property, if exist update the value
     *
     * @param $key
     * @param $value
     * @return void
     */
    public static function addProperty($key, $value)
    {
        System::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
