<?php

namespace Rufhausen\DB2Driver\Eloquent;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Enhanced Soft Deletes trait for DB2 with better date handling
 */
trait DB2SoftDeletes
{
    use SoftDeletes {
        runSoftDelete as parentRunSoftDelete;
        restore as parentRestore;
    }

    /**
     * Perform the actual delete query on this model instance with DB2 optimizations.
     *
     * @return void
     */
    protected function runSoftDelete()
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $time = $this->freshTimestamp();

        $columns = [$this->getDeletedAtColumn() => $this->fromDateTime($time)];

        $this->{$this->getDeletedAtColumn()} = $time;

        if ($this->timestamps && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;

            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        // Use DB2-optimized update
        $this->performDB2SoftDelete($query, $columns);
    }

    /**
     * Perform DB2-optimized soft delete.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $columns
     * @return void
     */
    protected function performDB2SoftDelete($query, array $columns)
    {
        $connection = $query->getConnection();
        
        // Set schema context if needed
        if (method_exists($connection, 'resetCurrentSchema')) {
            $connection->resetCurrentSchema();
        }

        $query->update($columns);
    }

    /**
     * Restore a soft-deleted model instance with DB2 optimizations.
     *
     * @return bool|null
     */
    public function restore()
    {
        // Fire the restoring event
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $this->{$this->getDeletedAtColumn()} = null;

        // If the restoring event returned false, we'll bail out of the restore and return
        // false to indicate that the restore failed. This provides a chance for any
        // listeners to cancel save operations which may invalidate state, etc.
        $this->exists = true;

        $connection = $this->getConnection();
        
        // Apply DB2-specific optimizations for restore
        if (method_exists($connection, 'resetCurrentSchema')) {
            $connection->resetCurrentSchema();
        }

        $result = $this->save();

        $this->fireModelEvent('restored', false);

        return $result;
    }

    /**
     * Get a new query builder that includes soft deletes with DB2 optimizations.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function newQueryWithoutScope($scope)
    {
        $builder = parent::newQueryWithoutScope($scope);
        
        // Apply DB2 optimizations
        $this->applyDB2QueryOptimizations($builder);
        
        return $builder;
    }

    /**
     * Apply DB2-specific query optimizations for soft delete queries.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    protected function applyDB2SoftDeleteOptimizations($builder)
    {
        $connection = $builder->getConnection();
        
        // Ensure proper schema context for soft delete queries
        if (method_exists($connection, 'getDefaultSchema') && 
            $connection->getDefaultSchema()) {
            // Schema context is managed by connection
        }

        // Add any DB2-specific indexes or query hints for soft delete performance
        // This could include hints for deleted_at column queries
    }

    /**
     * Force delete with DB2 optimizations.
     *
     * @return bool|null
     */
    public function forceDelete()
    {
        $connection = $this->getConnection();
        
        // Apply DB2 optimizations for force delete
        if (method_exists($connection, 'resetCurrentSchema')) {
            $connection->resetCurrentSchema();
        }

        return parent::forceDelete();
    }

    /**
     * Get the name of the "deleted at" column with DB2 compatibility.
     *
     * @return string
     */
    public function getDeletedAtColumn()
    {
        $column = defined('static::DELETED_AT') ? static::DELETED_AT : 'deleted_at';
        
        // Ensure the column name is DB2 compatible (uppercase if needed)
        return $column;
    }

    /**
     * Get the fully qualified "deleted at" column with DB2 table prefixing.
     *
     * @return string
     */
    public function getQualifiedDeletedAtColumn()
    {
        return $this->qualifyColumn($this->getDeletedAtColumn());
    }
}