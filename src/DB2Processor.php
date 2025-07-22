<?php

namespace Rufhausen\DB2Driver;

use Illuminate\Database\Query\Processors\Processor;

class DB2Processor extends Processor
{
    /**
     * Process an "insert get ID" query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $sql
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int
     */
    public function processInsertGetId($query, $sql, $values, $sequence = null)
    {
        $connection = $query->getConnection();
        
        $connection->insert($sql, $values);

        $id = $connection->getPdo()->lastInsertId($sequence);

        return is_numeric($id) ? (int) $id : $id;
    }
}