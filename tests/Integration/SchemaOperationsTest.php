<?php

namespace Rufhausen\DB2Driver\Tests\Integration;

use Rufhausen\DB2Driver\Schema\DB2Builder;
use Rufhausen\DB2Driver\Schema\DB2Blueprint;
use Rufhausen\DB2Driver\Schema\DB2SchemaGrammar;
use Rufhausen\DB2Driver\Tests\TestCase;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Schema;
use Mockery as m;

class SchemaOperationsTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        
        $app['config']->set('database.connections.testing', [
            'driver' => 'db2',
            'database' => 'test_db',
            'host' => 'localhost',
            'username' => 'test',
            'password' => 'test',
            'schema' => 'TEST',
            'prefix' => '',
            'date_format' => 'Y-m-d H:i:s',
            'offset_compatibility_mode' => true,
        ]);
        
        $app['config']->set('database.default', 'testing');
    }

    /** @test */
    public function it_can_instantiate_db2_blueprint()
    {
        $blueprint = new DB2Blueprint('test_table');
        
        $this->assertInstanceOf(DB2Blueprint::class, $blueprint);
        $this->assertEquals('test_table', $blueprint->getTable());
    }

    /** @test */
    public function db2_blueprint_can_add_system_name()
    {
        $blueprint = new DB2Blueprint('test_table');
        $blueprint->forSystemName('TSTTBL');
        
        $this->assertEquals('TSTTBL', $blueprint->systemName);
    }

    /** @test */
    public function db2_blueprint_can_add_table_label()
    {
        $blueprint = new DB2Blueprint('test_table');
        $command = $blueprint->label('Test Table Description');
        
        $this->assertEquals('label', $command->name);
        $this->assertEquals('Test Table Description', $command->label);
    }

    /** @test */
    public function db2_blueprint_handles_boolean_columns_with_schema_prefix()
    {
        $blueprint = new DB2Blueprint('MYSCHEMA.test_table');
        $column = $blueprint->boolean('is_active');
        
        $this->assertEquals('boolean', $column->type);
        $this->assertEquals('is_active', $column->name);
        // Should use table name without schema for prefix
        $this->assertEquals('test_table', $column->prefix);
    }

    /** @test */
    public function db2_blueprint_handles_numeric_columns()
    {
        $blueprint = new DB2Blueprint('test_table');
        $column = $blueprint->numeric('price', 10, 2);
        
        $this->assertEquals('numeric', $column->type);
        $this->assertEquals('price', $column->name);
        $this->assertEquals(10, $column->total);
        $this->assertEquals(2, $column->places);
    }

    /** @test */
    public function schema_grammar_compiles_create_table()
    {
        $grammar = new DB2SchemaGrammar();
        $blueprint = new DB2Blueprint('test_users');
        $blueprint->increments('id');
        $blueprint->string('name', 100);
        $blueprint->string('email', 255);
        $blueprint->timestamps();
        
        $connection = m::mock(Connection::class);
        $command = $blueprint->createCommand('create');
        
        $sql = $grammar->compileCreate($blueprint, $command, $connection);
        
        $this->assertStringContainsString('create table', strtolower($sql));
        $this->assertStringContainsString('test_users', strtolower($sql));
    }

    /** @test */
    public function schema_grammar_compiles_create_table_with_system_name()
    {
        $grammar = new DB2SchemaGrammar();
        $blueprint = new DB2Blueprint('test_users');
        $blueprint->forSystemName('TSTUSRS');
        $blueprint->increments('id');
        $blueprint->string('name', 100);
        
        $connection = m::mock(Connection::class);
        $command = $blueprint->createCommand('create');
        
        $sql = $grammar->compileCreate($blueprint, $command, $connection);
        
        $this->assertStringContainsString('create table', strtolower($sql));
        $this->assertStringContainsString('test_users', strtolower($sql));
        $this->assertStringContainsString('for system name tstusrs', strtolower($sql));
    }

    /** @test */
    public function schema_grammar_compiles_table_label()
    {
        $grammar = new DB2SchemaGrammar();
        $blueprint = new DB2Blueprint('test_users');
        
        $connection = m::mock(Connection::class);
        $command = $blueprint->createCommand('label', ['label' => 'User Management Table']);
        
        $sql = $grammar->compileLabel($blueprint, $command, $connection);
        
        $this->assertStringContainsString('label on table', strtolower($sql));
        $this->assertStringContainsString('test_users', strtolower($sql));
        $this->assertStringContainsString('user management table', strtolower($sql));
    }

    /** @test */
    public function schema_grammar_compiles_primary_key()
    {
        $grammar = new DB2SchemaGrammar();
        $blueprint = new DB2Blueprint('test_users');
        
        $command = $blueprint->createCommand('primary', [
            'index' => 'test_users_primary',
            'columns' => ['id']
        ]);
        
        $sql = $grammar->compilePrimary($blueprint, $command);
        
        $this->assertStringContainsString('alter table', strtolower($sql));
        $this->assertStringContainsString('add constraint', strtolower($sql));
        $this->assertStringContainsString('primary key', strtolower($sql));
    }

    /** @test */
    public function schema_grammar_compiles_foreign_key()
    {
        $grammar = new DB2SchemaGrammar();
        $blueprint = new DB2Blueprint('test_posts');
        
        $command = $blueprint->createCommand('foreign', [
            'index' => 'test_posts_user_id_foreign',
            'columns' => ['user_id'],
            'on' => 'test_users',
            'references' => ['id'],
            'onDelete' => 'cascade'
        ]);
        
        $sql = $grammar->compileForeign($blueprint, $command);
        
        $this->assertStringContainsString('alter table', strtolower($sql));
        $this->assertStringContainsString('add constraint', strtolower($sql));
        $this->assertStringContainsString('foreign key', strtolower($sql));
        $this->assertStringContainsString('references', strtolower($sql));
        $this->assertStringContainsString('on delete cascade', strtolower($sql));
    }

    /** @test */
    public function schema_grammar_compiles_unique_constraint()
    {
        $grammar = new DB2SchemaGrammar();
        $blueprint = new DB2Blueprint('test_users');
        
        $command = $blueprint->createCommand('unique', [
            'index' => 'test_users_email_unique',
            'columns' => ['email']
        ]);
        
        $sql = $grammar->compileUnique($blueprint, $command);
        
        $this->assertStringContainsString('alter table', strtolower($sql));
        $this->assertStringContainsString('add constraint', strtolower($sql));
        $this->assertStringContainsString('unique', strtolower($sql));
    }

    /** @test */
    public function schema_grammar_compiles_index()
    {
        $grammar = new DB2SchemaGrammar();
        $blueprint = new DB2Blueprint('test_users');
        
        $command = $blueprint->createCommand('index', [
            'index' => 'test_users_name_index',
            'columns' => ['name'],
            'indexSystem' => false
        ]);
        
        $sql = $grammar->compileIndex($blueprint, $command);
        
        $this->assertStringContainsString('create index', strtolower($sql));
        $this->assertStringContainsString('test_users_name_index', strtolower($sql));
        $this->assertStringContainsString('on test_users', strtolower($sql));
    }

    /** @test */
    public function schema_grammar_compiles_drop_table()
    {
        $grammar = new DB2SchemaGrammar();
        $blueprint = new DB2Blueprint('test_users');
        
        $command = $blueprint->createCommand('drop');
        
        $sql = $grammar->compileDrop($blueprint, $command);
        
        $this->assertStringContainsString('drop table', strtolower($sql));
        $this->assertStringContainsString('test_users', strtolower($sql));
    }

    /** @test */
    public function schema_grammar_handles_column_types()
    {
        $grammar = new DB2SchemaGrammar();
        
        // Test various column types
        $column = (object)['type' => 'string', 'length' => 255];
        $this->assertEquals('varchar(255)', $grammar->typeString($column));
        
        $column = (object)['type' => 'char', 'length' => 10];
        $this->assertEquals('char(10)', $grammar->typeChar($column));
        
        $column = (object)['type' => 'text'];
        $this->assertEquals('clob(64K)', $grammar->typeText($column));
        
        $column = (object)['type' => 'integer'];
        $this->assertEquals('int', $grammar->typeInteger($column));
        
        $column = (object)['type' => 'bigInteger'];
        $this->assertEquals('bigint', $grammar->typeBigInteger($column));
        
        $column = (object)['type' => 'decimal', 'total' => 8, 'places' => 2];
        $this->assertEquals('decimal(8, 2)', $grammar->typeDecimal($column));
        
        $column = (object)['type' => 'boolean', 'name' => 'is_active', 'prefix' => 'users', 'default' => null];
        $result = $grammar->typeBoolean($column);
        $this->assertStringContainsString('smallint', strtolower($result));
        $this->assertStringContainsString('constraint', strtolower($result));
        $this->assertStringContainsString('check', strtolower($result));
        
        $column = (object)['type' => 'date', 'nullable' => false];
        $this->assertEquals('date default current_date', $grammar->typeDate($column));
        
        $column = (object)['type' => 'timestamp', 'nullable' => false];
        $this->assertEquals('timestamp default current_timestamp', $grammar->typeTimestamp($column));
        
        $column = (object)['type' => 'uuid'];
        $this->assertEquals('char(36)', $grammar->typeUuid($column));
    }

    /** @test */
    public function schema_grammar_compiles_savepoint()
    {
        $grammar = new DB2SchemaGrammar();
        $sql = $grammar->compileSavepoint('test_savepoint');
        
        $this->assertEquals('SAVEPOINT test_savepoint ON ROLLBACK RETAIN CURSORS', $sql);
    }

    /** @test */
    public function db2_builder_creates_db2_blueprint()
    {
        $connection = m::mock(Connection::class);
        $builder = new DB2Builder($connection);
        
        $blueprint = $builder->createBlueprint('test_table');
        
        $this->assertInstanceOf(DB2Blueprint::class, $blueprint);
    }

    /** @test */
    public function schema_grammar_strips_schema_from_constraint_names()
    {
        $grammar = new DB2SchemaGrammar();
        $blueprint = new DB2Blueprint('MYSCHEMA.test_table');
        
        $command = $blueprint->createCommand('primary', [
            'index' => 'MYSCHEMA_test_table_primary',
            'columns' => ['id']
        ]);
        
        $sql = $grammar->compilePrimary($blueprint, $command);
        
        // Should strip schema prefix from constraint name
        $this->assertStringContainsString('test_table_primary', strtolower($sql));
        $this->assertStringNotContainsString('myschema_test_table_primary', strtolower($sql));
    }
}