<?php

namespace Rufhausen\DB2Driver\Tests\Unit;

use Rufhausen\DB2Driver\DB2QueryGrammar;
use Rufhausen\DB2Driver\Schema\DB2SchemaGrammar;
use Rufhausen\DB2Driver\Tests\TestCase;
use Illuminate\Database\Query\Builder;
use Mockery as m;
use PHPUnit\Framework\Attributes\Test;

class DB2GrammarTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    #[Test]
    public function it_can_instantiate_query_grammar()
    {
        $grammar = new DB2QueryGrammar();
        $this->assertInstanceOf(DB2QueryGrammar::class, $grammar);
    }

    #[Test]
    public function it_can_instantiate_schema_grammar()
    {
        $grammar = new DB2SchemaGrammar();
        $this->assertInstanceOf(DB2SchemaGrammar::class, $grammar);
    }

    #[Test]
    public function it_compiles_basic_select_query()
    {
        $connection = m::mock(\Illuminate\Database\Connection::class);
        $connection->shouldReceive('getTablePrefix')->andReturn('');
        
        $grammar = new DB2QueryGrammar($connection);
        $query = m::mock(Builder::class);
        
        $query->shouldReceive('getBindings')->andReturn([]);
        $query->offset = 0;
        $query->columns = ['*'];
        $query->from = 'users';
        $query->wheres = null;
        $query->groups = null;
        $query->havings = null;
        $query->orders = null;
        $query->limit = null;
        $query->unions = null;
        $query->lock = null;

        $sql = $grammar->compileSelect($query);
        $this->assertStringContainsString('select *', strtolower($sql));
        $this->assertStringContainsString('from users', strtolower($sql));
    }

    #[Test]
    public function it_compiles_limit_with_offset_compatibility_mode()
    {
        $connection = m::mock(\Illuminate\Database\Connection::class);
        $connection->shouldReceive('getTablePrefix')->andReturn('');
        
        $grammar = new DB2QueryGrammar($connection);
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
        
        $this->assertStringContainsString('FETCH FIRST 10 ROWS ONLY', $sql);
    }

    #[Test]
    public function it_handles_date_format_configuration()
    {
        $grammar = new DB2QueryGrammar();
        
        // Test default date format
        $defaultFormat = $grammar->getDateFormat();
        $this->assertIsString($defaultFormat);
        
        // Test custom date format
        $customFormat = 'Y-m-d H:i:s';
        $grammar->setDateFormat($customFormat);
        $this->assertEquals($customFormat, $grammar->getDateFormat());
    }

    #[Test]
    public function schema_grammar_compiles_table_exists_query()
    {
        $grammar = new DB2SchemaGrammar();
        $sql = $grammar->compileTableExists('MYSCHEMA', 'USERS');
        
        $this->assertStringContainsString('information_schema.tables', strtolower($sql));
        $this->assertStringContainsString('table_schema', strtolower($sql));
        $this->assertStringContainsString('table_name', strtolower($sql));
        $this->assertStringContainsString('upper(?)', strtolower($sql));
    }

    #[Test]
    public function schema_grammar_compiles_columns_query()
    {
        $grammar = new DB2SchemaGrammar();
        $sql = $grammar->compileColumns('MYSCHEMA', 'USERS');
        
        $this->assertStringContainsString('column_name', strtolower($sql));
        $this->assertStringContainsString('information_schema.columns', strtolower($sql));
        $this->assertStringContainsString('table_schema', strtolower($sql));
        $this->assertStringContainsString('table_name', strtolower($sql));
    }

    #[Test]
    public function schema_grammar_compiles_tables_query()
    {
        $grammar = new DB2SchemaGrammar();
        $sql = $grammar->compileTables('MYSCHEMA');
        
        $this->assertStringContainsString('table_name', strtolower($sql));
        $this->assertStringContainsString('information_schema.tables', strtolower($sql));
        $this->assertStringContainsString('order by table_name', strtolower($sql));
    }
}