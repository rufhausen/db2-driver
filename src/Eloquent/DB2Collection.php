<?php

namespace Rufhausen\DB2Driver\Eloquent;

use Illuminate\Database\Eloquent\Collection;

/**
 * Enhanced DB2 Collection with DB2-specific optimizations
 */
class DB2Collection extends Collection
{
    /**
     * Load a set of relationships onto the collection with DB2 optimizations.
     *
     * @param  array|string  $relations
     * @return $this
     */
    public function load($relations)
    {
        $query = $this->first()?->newQueryWithoutRelationships();

        if ($query) {
            // Apply DB2-specific loading optimizations
            $this->applyDB2LoadOptimizations($query, $relations);
        }

        return parent::load($relations);
    }

    /**
     * Apply DB2-specific optimizations for relationship loading.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array|string  $relations
     * @return void
     */
    protected function applyDB2LoadOptimizations($query, $relations)
    {
        $connection = $query->getConnection();
        
        // For DB2, we might want to optimize how we load relationships
        // to avoid issues with long table names or schema prefixes
        if (method_exists($connection, 'setCurrentSchema')) {
            // Ensure we're in the right schema context for relationship loading
            $connection->resetCurrentSchema();
        }
    }

    /**
     * Get the keys of the models in the collection with DB2 compatibility.
     *
     * @return \Illuminate\Support\Collection
     */
    public function modelKeys()
    {
        return parent::modelKeys()->map(function ($key) {
            // Ensure key values are properly formatted for DB2
            return is_string($key) ? trim($key) : $key;
        });
    }

    /**
     * Make the given, typically visible, attributes hidden across the collection.
     *
     * @param  array|string  $attributes
     * @return $this
     */
    public function makeHidden($attributes)
    {
        return $this->each(function ($model) use ($attributes) {
            if (method_exists($model, 'makeHidden')) {
                $model->makeHidden($attributes);
            }
        });
    }

    /**
     * Make the given, typically hidden, attributes visible across the collection.
     *
     * @param  array|string  $attributes
     * @return $this
     */
    public function makeVisible($attributes)
    {
        return $this->each(function ($model) use ($attributes) {
            if (method_exists($model, 'makeVisible')) {
                $model->makeVisible($attributes);
            }
        });
    }

    /**
     * Append attributes to each model in the collection.
     *
     * @param  array|string  $attributes
     * @return $this
     */
    public function append($attributes)
    {
        return $this->each(function ($model) use ($attributes) {
            if (method_exists($model, 'append')) {
                $model->append($attributes);
            }
        });
    }

    /**
     * Get a dictionary keyed by the given attribute with DB2 compatibility.
     *
     * @param  string  $keyBy
     * @return \Illuminate\Support\Collection
     */
    public function getDictionary($keyBy = null)
    {
        $keyBy = $keyBy ?: $this->first()?->getKeyName();

        $dictionary = [];

        foreach ($this->items as $model) {
            $key = $model->getAttribute($keyBy);
            
            // Handle DB2 specific key formatting
            if (is_string($key)) {
                $key = trim($key);
            }
            
            $dictionary[$key] = $model;
        }

        return collect($dictionary);
    }

    /**
     * Find a model in the collection by key with DB2 compatibility.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return \Illuminate\Database\Eloquent\Model|static|null
     */
    public function find($key, $default = null)
    {
        if ($key instanceof \Illuminate\Database\Eloquent\Model) {
            $key = $key->getKey();
        }

        if (is_array($key)) {
            if ($this->isEmpty()) {
                return new static;
            }

            return $this->whereIn($this->first()->getKeyName(), $key);
        }

        // Normalize key for DB2 comparison
        if (is_string($key)) {
            $key = trim($key);
        }

        return $this->first(function ($model) use ($key) {
            $modelKey = $model->getKey();
            
            if (is_string($modelKey)) {
                $modelKey = trim($modelKey);
            }
            
            return $modelKey == $key;
        }, $default);
    }
}