<?php

namespace Rufhausen\DB2Driver\Tests\Integration;

use Rufhausen\DB2Driver\DB2QueryGrammar;
use Rufhausen\DB2Driver\Tests\TestCase;
use Illuminate\Database\Query\Builder;
use Mockery as m;

class UpsertOperationsTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    /** @test */
    public function it_compiles_basic_upsert_query()
    {
        $grammar = new DB2QueryGrammar();
        $query = m::mock(Builder::class);
        $query->from = 'users';
        
        $values = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30],
            ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'age' => 25]
        ];
        
        $uniqueBy = ['email'];
        $update = ['name', 'age'];
        
        $sql = $grammar->compileUpsert($query, $values, $uniqueBy, $update);
        
        $this->assertStringContainsString('merge into', strtolower($sql));
        $this->assertStringContainsString('users', strtolower($sql));
        $this->assertStringContainsString('as target using', strtolower($sql));
        $this->assertStringContainsString('values', strtolower($sql));
        $this->assertStringContainsString('as source', strtolower($sql));
        $this->assertStringContainsString('when matched then update set', strtolower($sql));
        $this->assertStringContainsString('when not matched then insert', strtolower($sql));
    }

    /** @test */
    public function it_compiles_upsert_with_single_unique_column()
    {
        $grammar = new DB2QueryGrammar();
        $query = m::mock(Builder::class);
        $query->from = 'products';
        
        $values = [
            ['sku' => 'PROD001', 'name' => 'Product 1', 'price' => 19.99]
        ];
        
        $uniqueBy = ['sku'];
        $update = ['name', 'price'];
        
        $sql = $grammar->compileUpsert($query, $values, $uniqueBy, $update);
        
        // Should have proper ON clause for single column
        $this->assertStringContainsString('target.sku = source.sku', strtolower($sql));
        $this->assertStringContainsString('merge into products', strtolower($sql));
    }

    /** @test */
    public function it_compiles_upsert_with_multiple_unique_columns()
    {
        $grammar = new DB2QueryGrammar();
        $query = m::mock(Builder::class);
        $query->from = 'inventory';
        
        $values = [
            ['product_id' => 1, 'location_id' => 2, 'quantity' => 100]
        ];
        
        $uniqueBy = ['product_id', 'location_id'];
        $update = ['quantity'];
        
        $sql = $grammar->compileUpsert($query, $values, $uniqueBy, $update);
        
        // Should have AND condition for multiple unique columns
        $this->assertStringContainsString('target.product_id = source.product_id and target.location_id = source.location_id', strtolower($sql));
    }

    /** @test */
    public function it_compiles_upsert_without_update_clause()
    {
        $grammar = new DB2QueryGrammar();
        $query = m::mock(Builder::class);
        $query->from = 'logs';
        
        $values = [
            ['event' => 'user_login', 'user_id' => 1, 'timestamp' => '2024-01-01 12:00:00']
        ];
        
        $uniqueBy = ['event', 'user_id', 'timestamp'];
        $update = []; // No update, just insert if not exists
        
        $sql = $grammar->compileUpsert($query, $values, $uniqueBy, $update);
        
        // Should not have UPDATE clause when $update is empty
        $this->assertStringNotContainsString('when matched then update', strtolower($sql));
        $this->assertStringContainsString('when not matched then insert', strtolower($sql));
    }

    /** @test */
    public function it_handles_empty_values_for_upsert()
    {
        $grammar = new DB2QueryGrammar();
        $query = m::mock(Builder::class);
        $query->from = 'users';
        
        $values = [];
        $uniqueBy = ['email'];
        $update = ['name'];
        
        $sql = $grammar->compileUpsert($query, $values, $uniqueBy, $update);
        
        $this->assertEquals('', $sql);
    }

    /** @test */
    public function it_compiles_insert_or_ignore_query()
    {
        $grammar = new DB2QueryGrammar();
        $query = m::mock(Builder::class);
        $query->from = 'users';
        
        $values = [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Jane Doe', 'email' => 'jane@example.com']
        ];
        
        $sql = $grammar->compileInsertOrIgnore($query, $values);
        
        $this->assertStringContainsString('insert into', strtolower($sql));
        $this->assertStringContainsString('users', strtolower($sql));
        $this->assertStringContainsString('(name, email)', strtolower($sql));
        $this->assertStringContainsString('values', strtolower($sql));
        $this->assertStringContainsString('(?, ?)', $sql);
    }

    /** @test */
    public function it_handles_empty_values_for_insert_or_ignore()
    {
        $grammar = new DB2QueryGrammar();
        $query = m::mock(Builder::class);
        $query->from = 'users';
        
        $values = [];
        
        $sql = $grammar->compileInsertOrIgnore($query, $values);
        
        $this->assertEquals('', $sql);
    }

    /** @test */
    public function it_compiles_upsert_with_table_schema_prefix()
    {
        $grammar = new DB2QueryGrammar();
        $query = m::mock(Builder::class);
        $query->from = 'MYSCHEMA.users';
        
        $values = [
            ['name' => 'John Doe', 'email' => 'john@example.com']
        ];
        
        $uniqueBy = ['email'];
        $update = ['name'];
        
        $sql = $grammar->compileUpsert($query, $values, $uniqueBy, $update);
        
        $this->assertStringContainsString('merge into MYSCHEMA.users', $sql);
    }

    /** @test */
    public function it_properly_handles_column_wrapping_in_upsert()
    {
        $grammar = new DB2QueryGrammar();
        $query = m::mock(Builder::class);
        $query->from = 'users';
        
        // Use column names that might need wrapping
        $values = [
            ['user_name' => 'John', 'email_address' => 'john@test.com']
        ];
        
        $uniqueBy = ['email_address'];
        $update = ['user_name'];
        
        $sql = $grammar->compileUpsert($query, $values, $uniqueBy, $update);
        
        // Check that columns are properly referenced
        $this->assertStringContainsString('user_name', $sql);
        $this->assertStringContainsString('email_address', $sql);
        $this->assertStringContainsString('target.email_address = source.email_address', $sql);
    }

    /** @test */
    public function it_compiles_complex_upsert_with_many_columns()
    {
        $grammar = new DB2QueryGrammar();
        $query = m::mock(Builder::class);
        $query->from = 'complex_table';
        
        $values = [
            [
                'id' => 1,
                'name' => 'Test',
                'description' => 'Test Description',
                'status' => 'active',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00'
            ]
        ];
        
        $uniqueBy = ['id'];
        $update = ['name', 'description', 'status', 'updated_at'];
        
        $sql = $grammar->compileUpsert($query, $values, $uniqueBy, $update);
        
        $this->assertStringContainsString('merge into complex_table', $sql);
        $this->assertStringContainsString('(id, name, description, status, created_at, updated_at)', $sql);
        $this->assertStringContainsString('name = source.name', $sql);
        $this->assertStringContainsString('description = source.description', $sql);
        $this->assertStringContainsString('status = source.status', $sql);
        $this->assertStringContainsString('updated_at = source.updated_at', $sql);
    }
}