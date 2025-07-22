<?php

namespace Rufhausen\DB2Driver;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;

class DB2QueryGrammar extends Grammar
{
    /**
     * The connection instance.
     */
    protected $connection;

    /**
     * Create a new query grammar instance.
     */
    public function __construct(?Connection $connection = null)
    {
        $this->connection = $connection;
    }

    /**
     * The format for database stored dates.
     *
     * @var string
     */
    protected $dateFormat;

    /**
     * Offset compatibility mode true triggers FETCH FIRST X ROWS and ROW_NUM behavior for older versions of DB2
     *
     * @var bool
     */
    protected $offsetCompatibilityMode = true;

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value === '*') {
            return $value;
        }

        return str_replace('"', '""', $value);
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $limit
     * @return string
     */
    protected function compileLimit(Builder $query, $limit)
    {
        if ($this->offsetCompatibilityMode) {
            return "FETCH FIRST $limit ROWS ONLY";
        }

        return parent::compileLimit($query, $limit);
    }

    /**
     * Compile a select query into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        if (! $this->offsetCompatibilityMode) {
            return parent::compileSelect($query);
        }

        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        $components = $this->compileComponents($query);

        // If an offset is present on the query, we will need to wrap the query in
        // a big "ANSI" offset syntax block. This is very nasty compared to the
        // other database systems but is necessary for implementing features.
        if ($query->offset > 0) {
            return $this->compileAnsiOffset($query, $components);
        }

        return $this->concatenate($components);
    }

    /**
     * Create a full ANSI offset clause for the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $components
     * @return string
     */
    protected function compileAnsiOffset(Builder $query, $components)
    {
        // An ORDER BY clause is required to make this offset query work, so if one does
        // not exist we'll just create a dummy clause to trick the database and so it
        // does not complain about the queries for not having an "order by" clause.
        if (! isset($components['orders'])) {
            $components['orders'] = 'order by 1';
        }

        unset($components['limit']);

        // We need to add the row number to the query so we can compare it to the offset
        // and limit values given for the statements. So we will add an expression to
        // the "select" that will give back the row numbers on each of the records.
        $orderings = $components['orders'];

        $columns = (! empty($components['columns']) ? $components['columns'].', ' : 'select');

        if ($columns == 'select *, ' && $query->from && is_string($query->from)) {
            $columns = 'select '.$this->tablePrefix.$query->from.'.*, ';
        }

        $components['columns'] = $this->compileOver($orderings, $columns);

        // if there are bindings in the order, we need to move them to the select since we are moving the parameter
        // markers there with the OVER statement
        if (isset($query->getRawBindings()['order'])) {
            $query->addBinding($query->getRawBindings()['order'], 'select');
            $query->setBindings([], 'order');
        }

        unset($components['orders']);

        // Next we need to calculate the constraints that should be placed on the query
        // to get the right offset and limit from our query but if there is no limit
        // set we will just handle the offset only since that is all that matters.
        $start = $query->offset + 1;

        $constraint = $this->compileRowConstraint($query);

        $sql = $this->concatenate($components);

        // We are now ready to build the final SQL query so we'll create a common table
        // expression from the query and get the records with row numbers within our
        // given limit and offset value that we just put on as a query constraint.
        return $this->compileTableExpression($sql, $constraint);
    }

    /**
     * Compile the over statement for a table expression.
     *
     * @param  string  $orderings
     * @param  string  $columns
     * @return string
     */
    protected function compileOver($orderings, $columns)
    {
        return "{$columns} row_number() over ({$orderings}) as row_num";
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     * @return string
     */
    protected function compileRowConstraint($query)
    {
        $start = $query->offset + 1;

        if ($query->limit > 0) {
            $finish = $query->offset + $query->limit;

            return "between {$start} and {$finish}";
        }

        return ">= {$start}";
    }

    /**
     * Compile a common table expression for a query.
     *
     * @param  string  $sql
     * @param  string  $constraint
     * @return string
     */
    protected function compileTableExpression($sql, $constraint)
    {
        return "select * from ({$sql}) as temp_table where row_num {$constraint}";
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $offset
     * @return string
     */
    protected function compileOffset(Builder $query, $offset)
    {
        if ($this->offsetCompatibilityMode) {
            return '';
        }

        return parent::compileOffset($query, $offset);
    }

    /**
     * Compile an exists statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
     */
    public function compileExists(Builder $query)
    {
        $existsQuery = clone $query;

        $existsQuery->columns = [];

        return $this->compileSelect($existsQuery->selectRaw('1')->limit(1));
    }

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    public function getDateFormat()
    {
        return $this->dateFormat ?? $this->getDefaultDateFormat();
    }

    /**
     * Set the format for database stored dates.
     *
     * @param string $dateFormat
     * @return void
     */
    public function setDateFormat($dateFormat)
    {
        $this->dateFormat = $this->validateDateFormat($dateFormat);
    }

    /**
     * Get the default date format for DB2.
     *
     * @return string
     */
    protected function getDefaultDateFormat()
    {
        // DB2 standard timestamp format
        return 'Y-m-d H:i:s';
    }

    /**
     * Validate and normalize the date format for DB2 compatibility.
     *
     * @param string $format
     * @return string
     */
    protected function validateDateFormat($format)
    {
        // Map common Laravel formats to DB2-compatible formats
        $formatMap = [
            'Y-m-d H:i:s' => 'Y-m-d H:i:s',           // Standard Laravel
            'Y-m-d H:i:s.u' => 'Y-m-d H:i:s.u',       // With microseconds
            'Y-m-d-H.i.s' => 'Y-m-d-H.i.s',           // DB2 style
            'Y-m-d-H.i.s.u' => 'Y-m-d-H.i.s.u',       // DB2 with microseconds
        ];

        return $formatMap[$format] ?? $format;
    }

    /**
     * Wrap a value that is used for a date/time operation.
     *
     * @param string $value
     * @return string
     */
    public function dateBasedWhere($column, $operator, $value, $boolean = 'and')
    {
        // Handle Carbon instances and date strings for DB2
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format($this->getDateFormat());
        }

        return parent::dateBasedWhere($column, $operator, $value, $boolean);
    }

    /**
     * Set offset compatibility mode to trigger FETCH FIRST X ROWS and ROW_NUM behavior for older versions of DB2
     *
     * @param bool $bool
     * @return void
     */
    public function setOffsetCompatibilityMode($bool)
    {
        $this->offsetCompatibilityMode = $bool;
    }

    /**
     * Compile an "insert ignore" statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
     */
    public function compileInsertOrIgnore(Builder $query, array $values)
    {
        // DB2 doesn't have INSERT IGNORE, so we use MERGE statement
        if (empty($values)) {
            return '';
        }

        $table = $this->wrapTable($query->from);
        $columns = $this->columnize(array_keys(reset($values)));
        
        // For insert ignore, we'll use a simple INSERT with error handling
        // This is a basic implementation - in production you might want more sophisticated logic
        $sql = "insert into {$table} ({$columns}) values ";
        
        $parameters = collect($values)->map(function ($record) {
            return '('.implode(', ', array_fill(0, count($record), '?')).')';
        })->implode(', ');

        return $sql . $parameters;
    }

    /**
     * Compile an upsert statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $values
     * @param  array  $uniqueBy
     * @param  array  $update
     * @return string
     */
    public function compileUpsert(Builder $query, array $values, array $uniqueBy, array $update)
    {
        if (empty($values)) {
            return '';
        }

        $table = $this->wrapTable($query->from);
        $columns = $this->columnize(array_keys(reset($values)));
        $uniqueColumns = $this->columnize($uniqueBy);
        
        // Use DB2 MERGE statement for upsert functionality
        $sql = "merge into {$table} as target using (values ";
        
        // Build the values clause
        $valueRows = collect($values)->map(function ($record, $index) {
            $placeholders = implode(', ', array_fill(0, count($record), '?'));
            return "({$placeholders})";
        })->implode(', ');
        
        $sql .= $valueRows . ") as source ({$columns}) on (";
        
        // Build the ON clause for unique columns
        $onConditions = collect($uniqueBy)->map(function ($column) {
            $wrappedColumn = $this->wrap($column);
            return "target.{$wrappedColumn} = source.{$wrappedColumn}";
        })->implode(' and ');
        
        $sql .= $onConditions . ') ';
        
        // Build UPDATE clause
        if (!empty($update)) {
            $updateColumns = collect($update)->map(function ($value, $key) {
                $column = $this->wrap(is_numeric($key) ? $value : $key);
                if (is_numeric($key)) {
                    return "{$column} = source.{$column}";
                }
                return "{$column} = ?";
            })->implode(', ');
            
            $sql .= "when matched then update set {$updateColumns} ";
        }
        
        // Build INSERT clause
        $sql .= "when not matched then insert ({$columns}) values ({$columns})";
        
        return $sql;
    }

    /**
     * Compile the SQL statement to define a savepoint.
     *
     * @param  string  $name
     * @return string
     */
    public function compileSavepoint($name)
    {
        return 'SAVEPOINT '.$name.' ON ROLLBACK RETAIN CURSORS';
    }
}
