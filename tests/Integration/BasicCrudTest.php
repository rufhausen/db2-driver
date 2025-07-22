<?php

namespace Rufhausen\DB2Driver\Tests\Integration;

use Rufhausen\DB2Driver\DB2Connection;
use Rufhausen\DB2Driver\DB2QueryGrammar;
use Rufhausen\DB2Driver\Tests\TestCase;
use Illuminate\Database\Query\Builder;
use Mockery as m;

class BasicCrudTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    /** @test */
    public function it_compiles_basic_insert_query()
    {
        $grammar = new DB2QueryGrammar();
        $query = m::mock(Builder::class);
        
        $query->shouldReceive('getBindings')->andReturn([]);
        $query->from = 'users';
        
        // Mock the insert data
        $values = ['name' => 'John Doe', 'email' => 'john@example.com'];
        
        $sql = $grammar->compileInsert($query, $values);
        
        $this->assertStringContainsString('insert into', strtolower($sql));
        $this->assertStringContainsString('users', strtolower($sql));
        $this->assertStringContainsString('name', strtolower($sql));
        $this->assertStringContainsString('email', strtolower($sql));
    }

    /** @test */
    public function it_compiles_basic_update_query()
    {
        $grammar = new DB2QueryGrammar();
        $query = m::mock(Builder::class);
        
        $query->shouldReceive('getBindings')->andReturn([]);
        $query->from = 'users';
        $query->wheres = [];
        $query->joins = null;
        $query->limit = null;
        
        $values = ['name' => 'Jane Doe'];
        
        $sql = $grammar->compileUpdate($query, $values);
        
        $this->assertStringContainsString('update', strtolower($sql));
        $this->assertStringContainsString('users', strtolower($sql));
        $this->assertStringContainsString('set', strtolower($sql));
    }

    /** @test */
    public function it_compiles_basic_delete_query()
    {
        $grammar = new DB2QueryGrammar();
        $query = m::mock(Builder::class);
        
        $query->shouldReceive('getBindings')->andReturn([]);
        $query->from = 'users';
        $query->wheres = [];
        $query->joins = null;
        $query->limit = null;
        
        $sql = $grammar->compileDelete($query);
        
        $this->assertStringContainsString('delete from', strtolower($sql));
        $this->assertStringContainsString('users', strtolower($sql));
    }

    /** @test */
    public function it_compiles_select_with_where_clause()
    {
        $grammar = new DB2QueryGrammar();
        $query = m::mock(Builder::class);
        
        $query->shouldReceive('getBindings')->andReturn([]);
        $query->offset = 0;
        $query->columns = ['*'];
        $query->from = 'users';
        $query->wheres = [
            [
                'type' => 'Basic',
                'column' => 'email',
                'operator' => '=',
                'value' => 'test@example.com',
                'boolean' => 'and'
            ]
        ];
        $query->groups = null;
        $query->havings = null;
        $query->orders = null;
        $query->limit = null;
        $query->unions = null;
        $query->lock = null;

        $sql = $grammar->compileSelect($query);
        
        $this->assertStringContainsString('select *', strtolower($sql));
        $this->assertStringContainsString('from users', strtolower($sql));
        $this->assertStringContainsString('where', strtolower($sql));
    }

    /** @test */
    public function it_compiles_select_with_limit()
    {
        $grammar = new DB2QueryGrammar();
        $grammar->setOffsetCompatibilityMode(true);
        
        $query = m::mock(Builder::class);
        
        $query->shouldReceive('getBindings')->andReturn([]);
        $query->offset = 0;
        $query->columns = ['*'];
        $query->from = 'users';
        $query->wheres = null;
        $query->groups = null;
        $query->havings = null;
        $query->orders = null;
        $query->limit = 10;
        $query->unions = null;
        $query->lock = null;

        $sql = $grammar->compileSelect($query);
        
        $this->assertStringContainsString('fetch first 10 rows only', strtolower($sql));
    }

    /** @test */
    public function it_compiles_select_with_offset_using_row_number()
    {
        $grammar = new DB2QueryGrammar();
        $grammar->setOffsetCompatibilityMode(true);
        
        $query = m::mock(Builder::class);
        
        $query->shouldReceive('getBindings')->andReturn([]);
        $query->shouldReceive('getRawBindings')->andReturn(['order' => []]);
        $query->shouldReceive('addBinding')->andReturn();
        $query->shouldReceive('setBindings')->andReturn();
        
        $query->offset = 10;
        $query->columns = ['*'];
        $query->from = 'users';
        $query->wheres = null;
        $query->groups = null;
        $query->havings = null;
        $query->orders = null;
        $query->limit = 5;
        $query->unions = null;
        $query->lock = null;

        $sql = $grammar->compileSelect($query);
        
        // Should use ROW_NUMBER() for offset queries
        $this->assertStringContainsString('row_number()', strtolower($sql));
        $this->assertStringContainsString('temp_table', strtolower($sql));
    }

    /** @test */
    public function it_handles_exists_queries()
    {
        $grammar = new DB2QueryGrammar();
        $query = m::mock(Builder::class);
        
        $query->shouldReceive('selectRaw')->with('1 exists')->andReturnSelf();
        $query->shouldReceive('limit')->with(1)->andReturnSelf();
        $query->columns = [];
        
        // Mock the cloned query properties
        $query->offset = 0;
        $query->from = 'users';
        $query->wheres = null;
        $query->groups = null;
        $query->havings = null;
        $query->orders = null;
        $query->unions = null;
        $query->lock = null;
        $query->limit = 1;
        
        $query->shouldReceive('getBindings')->andReturn([]);

        $sql = $grammar->compileExists($query);
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('exists', strtolower($sql));
    }
}