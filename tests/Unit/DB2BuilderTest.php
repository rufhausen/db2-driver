<?php

namespace Rufhausen\DB2Driver\Tests\Unit;

use Rufhausen\DB2Driver\DB2Connection;
use Rufhausen\DB2Driver\Schema\DB2Builder;
use Rufhausen\DB2Driver\Schema\DB2SchemaGrammar;
use Rufhausen\DB2Driver\DB2Processor;
use Rufhausen\DB2Driver\Tests\TestCase;
use Mockery as m;
use PHPUnit\Framework\Attributes\Test;

class DB2BuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    #[Test]
    public function it_can_instantiate_builder()
    {
        $connection = m::mock(DB2Connection::class);
        $grammar = m::mock(DB2SchemaGrammar::class);
        
        $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        
        $builder = new DB2Builder($connection);

        $this->assertInstanceOf(DB2Builder::class, $builder);
    }

    #[Test]
    public function has_table_method_works_with_schema_prefix()
    {
        $connection = m::mock(DB2Connection::class);
        $grammar = m::mock(DB2SchemaGrammar::class);
        
        $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $connection->shouldReceive('getTablePrefix')->andReturn('');
        $connection->shouldReceive('getDefaultSchema')->andReturn('DEFAULTSCHEMA');
        
        $grammar->shouldReceive('compileTableExists')
            ->with('TESTSCHEMA', 'USERS')
            ->andReturn('SELECT * FROM information_schema.tables WHERE table_schema = upper(?) AND table_name = upper(?)');
            
        $connection->shouldReceive('select')
            ->with(m::type('string'), ['TESTSCHEMA', 'USERS'])
            ->andReturn([['table_name' => 'USERS']]);

        $builder = new DB2Builder($connection);
        $result = $builder->hasTable('TESTSCHEMA.USERS');

        $this->assertTrue($result);
    }

    #[Test]
    public function has_table_method_works_without_schema_prefix()
    {
        $connection = m::mock(DB2Connection::class);
        $grammar = m::mock(DB2SchemaGrammar::class);
        
        $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $connection->shouldReceive('getTablePrefix')->andReturn('');
        $connection->shouldReceive('getDefaultSchema')->andReturn('DEFAULTSCHEMA');
        
        $grammar->shouldReceive('compileTableExists')
            ->with('DEFAULTSCHEMA', 'USERS')
            ->andReturn('SELECT * FROM information_schema.tables WHERE table_schema = upper(?) AND table_name = upper(?)');
            
        $connection->shouldReceive('select')
            ->with(m::type('string'), ['DEFAULTSCHEMA', 'USERS'])
            ->andReturn([['table_name' => 'USERS']]);

        $builder = new DB2Builder($connection);
        $result = $builder->hasTable('USERS');

        $this->assertTrue($result);
    }

    #[Test]
    public function get_column_listing_works_with_schema_prefix()
    {
        $connection = m::mock(DB2Connection::class);
        $grammar = m::mock(DB2SchemaGrammar::class);
        $processor = m::mock(DB2Processor::class);
        
        $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $connection->shouldReceive('getDatabaseName')->andReturn('DEFAULTDB');
        $connection->shouldReceive('getTablePrefix')->andReturn('');
        $connection->shouldReceive('getPostProcessor')->andReturn($processor);
        
        $grammar->shouldReceive('compileColumns')
            ->with('TESTSCHEMA', 'USERS')
            ->andReturn('SELECT column_name FROM information_schema.columns WHERE table_schema = upper(?) AND table_name = upper(?)');
            
        $connection->shouldReceive('select')
            ->with(m::type('string'), ['TESTSCHEMA', 'USERS'])
            ->andReturn([
                (object)['column_name' => 'ID'],
                (object)['column_name' => 'NAME'],
                (object)['column_name' => 'EMAIL']
            ]);

        $processor->shouldReceive('processColumnListing')
            ->andReturn([
                (object)['column_name' => 'ID'],
                (object)['column_name' => 'NAME'],
                (object)['column_name' => 'EMAIL']
            ]);

        $builder = new DB2Builder($connection);
        $result = $builder->getColumnListing('TESTSCHEMA.USERS');

        $this->assertEquals(['ID', 'NAME', 'EMAIL'], $result);
    }

    #[Test]
    public function get_column_listing_works_without_schema_prefix()
    {
        $connection = m::mock(DB2Connection::class);
        $grammar = m::mock(DB2SchemaGrammar::class);
        $processor = m::mock(DB2Processor::class);
        
        $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);
        $connection->shouldReceive('getDatabaseName')->andReturn('DEFAULTDB');
        $connection->shouldReceive('getTablePrefix')->andReturn('');
        $connection->shouldReceive('getPostProcessor')->andReturn($processor);
        
        $grammar->shouldReceive('compileColumns')
            ->with('DEFAULTDB', 'USERS')
            ->andReturn('SELECT column_name FROM information_schema.columns WHERE table_schema = upper(?) AND table_name = upper(?)');
            
        $connection->shouldReceive('select')
            ->with(m::type('string'), ['DEFAULTDB', 'USERS'])
            ->andReturn([
                (object)['column_name' => 'ID'],
                (object)['column_name' => 'NAME']
            ]);

        $processor->shouldReceive('processColumnListing')
            ->andReturn([
                (object)['column_name' => 'ID'],
                (object)['column_name' => 'NAME']
            ]);

        $builder = new DB2Builder($connection);
        $result = $builder->getColumnListing('USERS');

        $this->assertEquals(['ID', 'NAME'], $result);
    }
}