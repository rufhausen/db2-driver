<?php

namespace Rufhausen\DB2Driver\Eloquent\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Array Object Cast for DB2 that handles serialized PHP objects stored as CLOB
 */
class DB2AsArrayObject implements CastsAttributes
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

        if (!is_array($value) && !is_object($value)) {
            throw new InvalidArgumentException(
                "The given value for attribute [{$key}] must be an array or object."
            );
        }

        // Serialize the value for DB2 CLOB storage
        return serialize($value);
    }

    /**
     * Cast the given value from storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return array|null
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

        try {
            $unserialized = unserialize($value);
            
            // Ensure we return an array
            if (is_object($unserialized)) {
                return (array) $unserialized;
            }

            return $unserialized;
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                "Unable to unserialize attribute [{$key}] for model [" . get_class($model) . ']: ' . $e->getMessage()
            );
        }
    }
}