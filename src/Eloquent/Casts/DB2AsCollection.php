<?php

namespace Rufhausen\DB2Driver\Eloquent\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Collection Cast for DB2 that handles Collection storage as JSON in CLOB
 */
class DB2AsCollection implements CastsAttributes
{
    /**
     * Cast the given value for storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return string|null
     */
    public function set(Model $model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return null;
        }

        // Convert Collection to array for JSON encoding
        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException(
                "The given value for attribute [{$key}] must be a Collection or array."
            );
        }

        // Encode to JSON with DB2 CLOB compatibility
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(
                "Unable to encode attribute [{$key}] for model [" . get_class($model) . '] to JSON: ' . json_last_error_msg()
            );
        }

        return $encoded;
    }

    /**
     * Cast the given value from storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return \Illuminate\Support\Collection|null
     */
    public function get(Model $model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return null;
        }

        // Handle DB2 CLOB data which might come as a stream
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        // Handle empty strings as null
        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(
                "Unable to decode attribute [{$key}] for model [" . get_class($model) . '] from JSON: ' . json_last_error_msg()
            );
        }

        return collect($decoded);
    }
}