<?php

namespace Rufhausen\DB2Driver\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Enhanced DB2 Model with better relationship and date handling
 */
class DB2Model extends Model
{
    /**
     * The date format to use with this model.
     *
     * @var string
     */
    protected $dateFormat;

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    public function getDateFormat()
    {
        return $this->dateFormat ?: $this->getConnection()->getQueryGrammar()->getDateFormat();
    }

    /**
     * Set the date format for this model.
     *
     * @param string $format
     * @return $this
     */
    public function setDateFormat($format)
    {
        $this->dateFormat = $format;
        
        return $this;
    }

    /**
     * Get a fresh timestamp for the model.
     *
     * @return \Illuminate\Support\Carbon
     */
    public function freshTimestamp()
    {
        return $this->fromDateTime($this->freshTimestampString());
    }

    /**
     * Get a fresh timestamp string for the model.
     *
     * @return string
     */
    public function freshTimestampString()
    {
        return $this->fromDateTime($this->freshTimestamp());
    }

    /**
     * Convert a DateTime to a storable string with DB2 compatibility.
     *
     * @param  \DateTimeInterface|null  $value
     * @return string|null
     */
    public function fromDateTime($value)
    {
        if (is_null($value)) {
            return $value;
        }

        $format = $this->getDateFormat();

        // Handle microseconds for DB2 if needed
        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        return parent::fromDateTime($value);
    }

    /**
     * Perform a model insert operation.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return bool
     */
    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query)
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        // If the model has an incrementing key, we can use insertGetId
        if ($this->getIncrementing()) {
            $attributes = $this->getAttributesForInsert();

            if ($this->getIncrementing() && ! is_null($keyName = $this->getKeyName())) {
                $id = $query->insertGetId($attributes, $keyName);

                $this->setAttribute($keyName, $id);
            }
        } else {
            $query->insert($this->getAttributesForInsert());
        }

        // The model is successfully inserted, set the exists flag and fire the event
        $this->exists = true;

        $this->wasRecentlyCreated = true;

        $this->fireModelEvent('created', false);

        return true;
    }

    /**
     * Handle dynamic method calls for relationships with better error handling.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        try {
            return parent::__call($method, $parameters);
        } catch (\BadMethodCallException $e) {
            // Provide better error messages for common DB2 issues
            if (str_contains($method, 'Relationship') || str_ends_with($method, 'Relation')) {
                throw new \BadMethodCallException(
                    "Relationship method '{$method}' not found on model. " .
                    "Check that the relationship is properly defined and that any required " .
                    "foreign key columns exist in your DB2 tables."
                );
            }

            throw $e;
        }
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array  $models
     * @return \Rufhausen\DB2Driver\Eloquent\DB2Collection
     */
    public function newCollection(array $models = [])
    {
        return new DB2Collection($models);
    }

    /**
     * Get the database connection for the model with DB2 optimizations.
     *
     * @return \Rufhausen\DB2Driver\DB2Connection
     */
    public function getConnection()
    {
        $connection = parent::getConnection();
        
        // Ensure we're using the DB2 connection
        if (!$connection instanceof \Rufhausen\DB2Driver\DB2Connection) {
            throw new \InvalidArgumentException(
                'Model must use a DB2 database connection. ' .
                'Check your database configuration.'
            );
        }

        return $connection;
    }

    /**
     * Create a new query builder for the model with DB2 optimizations.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function newQuery()
    {
        $builder = parent::newQuery();
        
        // Add any DB2-specific query optimizations here
        $this->applyDB2QueryOptimizations($builder);
        
        return $builder;
    }

    /**
     * Apply DB2-specific query optimizations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    protected function applyDB2QueryOptimizations($builder)
    {
        // Add any DB2-specific optimizations
        // For example, set schema context if needed
        $connection = $builder->getConnection();
        
        if (method_exists($connection, 'getDefaultSchema') && 
            $connection->getDefaultSchema()) {
            // Schema is already set via connection, no additional action needed
        }
    }

    /**
     * Get the casts array with DB2-specific cast mappings.
     *
     * @return array
     */
    public function getCasts()
    {
        $casts = parent::getCasts();

        // Auto-apply DB2 casts for common patterns
        foreach ($this->getAttributes() as $key => $value) {
            // Auto-detect JSON columns and apply DB2Json cast
            if (!isset($casts[$key]) && 
                (str_ends_with($key, '_json') || str_ends_with($key, '_data'))) {
                $casts[$key] = \Rufhausen\DB2Driver\Eloquent\Casts\DB2Json::class;
            }
            
            // Auto-detect collection columns
            if (!isset($casts[$key]) && 
                (str_ends_with($key, '_collection') || str_ends_with($key, '_list'))) {
                $casts[$key] = \Rufhausen\DB2Driver\Eloquent\Casts\DB2AsCollection::class;
            }
        }

        return $casts;
    }

    /**
     * Cast an attribute to a native PHP type with DB2 compatibility.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function castAttribute($key, $value)
    {
        if (is_null($value)) {
            return $value;
        }

        // Handle DB2 CLOB data which might come as a stream
        if (is_resource($value) && get_resource_type($value) === 'stream') {
            $value = stream_get_contents($value);
        }

        return parent::castAttribute($key, $value);
    }

    /**
     * Set a given attribute on the model with DB2 compatibility.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function setAttribute($key, $value)
    {
        // Handle DB2 CLOB data for text fields
        if (is_resource($value) && get_resource_type($value) === 'stream') {
            $value = stream_get_contents($value);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Convert the model's attributes to an array with DB2 compatibility.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        // Handle any DB2-specific attribute formatting
        foreach ($attributes as $key => $value) {
            // Trim string values that might have DB2 padding
            if (is_string($value) && !$this->hasCast($key)) {
                $attributes[$key] = trim($value);
            }
        }

        return $attributes;
    }
}