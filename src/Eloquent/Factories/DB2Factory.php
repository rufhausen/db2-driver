<?php

namespace Rufhausen\DB2Driver\Eloquent\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Enhanced Factory for DB2 models with DB2-specific data handling
 */
abstract class DB2Factory extends Factory
{
    /**
     * Create a new factory instance for the given model.
     *
     * @param  string  $model
     * @param  string  $name
     * @return static
     */
    public static function factoryForModel(string $model, string $name = 'default')
    {
        $factory = parent::factoryForModel($model, $name);
        
        // Apply DB2-specific factory configurations
        return $factory->configureDB2();
    }

    /**
     * Configure this factory for DB2 compatibility.
     *
     * @return $this
     */
    protected function configureDB2()
    {
        return $this->afterMaking(function ($model) {
            // Apply DB2-specific model configurations
            $this->applyDB2ModelConfigurations($model);
        });
    }

    /**
     * Apply DB2-specific configurations to the model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    protected function applyDB2ModelConfigurations($model)
    {
        // Ensure timestamps use DB2-compatible formats
        if ($model->usesTimestamps()) {
            $dateFormat = $model->getConnection()->getQueryGrammar()->getDateFormat();
            
            if (method_exists($model, 'setDateFormat')) {
                $model->setDateFormat($dateFormat);
            }
        }

        // Handle any string attributes that need trimming for DB2 CHAR columns
        foreach ($model->getAttributes() as $key => $value) {
            if (is_string($value)) {
                // Ensure no trailing spaces for DB2 storage
                $model->setAttribute($key, rtrim($value));
            }
        }
    }

    /**
     * Generate a DB2-compatible string value.
     *
     * @param  int  $maxLength
     * @param  bool  $padded
     * @return string
     */
    protected function db2String($maxLength = 255, $padded = false)
    {
        $value = $this->faker->text($maxLength);
        
        // Ensure the value fits within DB2 constraints
        $value = substr($value, 0, $maxLength);
        
        // Apply padding if needed for CHAR columns
        if ($padded) {
            $value = str_pad($value, $maxLength);
        }
        
        return $value;
    }

    /**
     * Generate a DB2-compatible date.
     *
     * @param  string|null  $format
     * @return string
     */
    protected function db2Date($format = null)
    {
        $date = $this->faker->dateTime();
        
        // Use DB2-compatible date format
        $format = $format ?: 'Y-m-d H:i:s';
        
        return $date->format($format);
    }

    /**
     * Generate a DB2-compatible timestamp.
     *
     * @param  bool  $withMicroseconds
     * @return string
     */
    protected function db2Timestamp($withMicroseconds = false)
    {
        $date = $this->faker->dateTime();
        
        if ($withMicroseconds) {
            return $date->format('Y-m-d H:i:s.u');
        }
        
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Generate a DB2-compatible decimal value.
     *
     * @param  int  $precision
     * @param  int  $scale
     * @return string
     */
    protected function db2Decimal($precision = 10, $scale = 2)
    {
        $maxValue = pow(10, $precision - $scale) - 1;
        $value = $this->faker->randomFloat($scale, 0, $maxValue);
        
        return number_format($value, $scale, '.', '');
    }

    /**
     * Generate a DB2-compatible JSON string for CLOB storage.
     *
     * @param  array  $data
     * @return string
     */
    protected function db2Json(?array $data = null)
    {
        $data = $data ?: [
            'key1' => $this->faker->word,
            'key2' => $this->faker->numberBetween(1, 100),
            'key3' => $this->faker->boolean,
        ];
        
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate a value that fits DB2 naming constraints.
     *
     * @param  int  $maxLength
     * @return string
     */
    protected function db2Name($maxLength = 30)
    {
        // DB2 names are often limited to 30 characters and must be uppercase
        $name = $this->faker->lexify('????_????_????');
        $name = substr(strtoupper($name), 0, $maxLength);
        
        // Ensure it starts with a letter
        if (!ctype_alpha($name[0])) {
            $name = 'A' . substr($name, 1);
        }
        
        return $name;
    }

    /**
     * Create models and persist them to the database with DB2 optimizations.
     *
     * @param  iterable|callable|int|null  $count
     * @param  callable|array  $state
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection
     */
    public function create($count = null, $state = [])
    {
        if (is_callable($count)) {
            [$count, $state] = [null, $count];
        }

        $results = $this->make($count, $state);

        if ($results instanceof \Illuminate\Database\Eloquent\Model) {
            $this->saveDB2Model($results);
            return $results;
        }

        $results->each(function ($model) {
            $this->saveDB2Model($model);
        });

        return $results;
    }

    /**
     * Save a model with DB2-specific optimizations.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    protected function saveDB2Model($model)
    {
        $connection = $model->getConnection();
        
        // Apply schema context if needed
        if (method_exists($connection, 'resetCurrentSchema')) {
            $connection->resetCurrentSchema();
        }

        $model->save();
    }
}