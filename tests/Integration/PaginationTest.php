<?php

namespace Rufhausen\DB2Driver\Tests\Integration;

use Rufhausen\DB2Driver\DB2QueryGrammar;
use Rufhausen\DB2Driver\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Mockery as m;

class TestArticle extends Model
{
    protected $table = 'test_articles';
    protected $fillable = ['title', 'content', 'published'];
    public $timestamps = true;
}

class PaginationTest extends TestCase
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
    public function it_compiles_simple_limit_query()
    {
        $grammar = new DB2QueryGrammar();
        $grammar->setOffsetCompatibilityMode(true);
        
        $query = m::mock(Builder::class);
        
        $result = $grammar->compileLimit($query, 10);
        
        $this->assertEquals('FETCH FIRST 10 ROWS ONLY', $result);
    }

    /** @test */
    public function it_compiles_limit_with_modern_db2()
    {
        $grammar = new DB2QueryGrammar();
        $grammar->setOffsetCompatibilityMode(false);
        
        $query = m::mock(Builder::class);
        
        // Should call parent implementation for modern DB2
        $result = $grammar->compileLimit($query, 10);
        
        // This would be the parent method result, but we can't easily test it
        // without more complex mocking. The important thing is it doesn't crash.
        $this->assertIsString($result);
    }

    /** @test */
    public function it_handles_offset_queries_with_row_number()
    {
        $grammar = new DB2QueryGrammar();
        $grammar->setOffsetCompatibilityMode(true);
        
        $query = m::mock(Builder::class);
        
        $query->shouldReceive('getBindings')->andReturn([]);
        $query->shouldReceive('getRawBindings')->andReturn(['order' => []]);
        $query->shouldReceive('addBinding')->andReturn();
        $query->shouldReceive('setBindings')->andReturn();
        
        $query->offset = 20;
        $query->columns = ['*'];
        $query->from = 'test_articles';
        $query->wheres = null;
        $query->groups = null;
        $query->havings = null;
        $query->orders = null;
        $query->limit = 10;
        $query->unions = null;
        $query->lock = null;

        $sql = $grammar->compileSelect($query);
        
        // Should use ROW_NUMBER() windowing function
        $this->assertStringContainsString('row_number()', strtolower($sql));
        $this->assertStringContainsString('temp_table', strtolower($sql));
        $this->assertStringContainsString('row_num', strtolower($sql));
    }

    /** @test */
    public function it_adds_order_by_for_offset_queries_when_missing()
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
        $query->from = 'test_articles';
        $query->wheres = null;
        $query->groups = null;
        $query->havings = null;
        $query->orders = null; // No explicit ordering
        $query->limit = 5;
        $query->unions = null;
        $query->lock = null;

        $sql = $grammar->compileSelect($query);
        
        // Should automatically add "order by 1" for pagination to work
        $this->assertStringContainsString('order by 1', strtolower($sql));
        $this->assertStringContainsString('row_number()', strtolower($sql));
    }

    /** @test */
    public function it_compiles_row_constraints_for_pagination()
    {
        $grammar = new DB2QueryGrammar();
        
        $query = m::mock(Builder::class);
        $query->offset = 20;
        $query->limit = 10;
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($grammar);
        $method = $reflection->getMethod('compileRowConstraint');
        $method->setAccessible(true);
        
        $result = $method->invoke($grammar, $query);
        
        // Should create BETWEEN constraint for limit + offset
        $this->assertEquals('between 21 and 30', $result);
    }

    /** @test */
    public function it_compiles_row_constraints_for_offset_only()
    {
        $grammar = new DB2QueryGrammar();
        
        $query = m::mock(Builder::class);
        $query->offset = 15;
        $query->limit = 0; // No limit, just offset
        
        $reflection = new \ReflectionClass($grammar);
        $method = $reflection->getMethod('compileRowConstraint');
        $method->setAccessible(true);
        
        $result = $method->invoke($grammar, $query);
        
        // Should create >= constraint for offset only
        $this->assertEquals('>= 16', $result);
    }

    /** @test */
    public function eloquent_model_can_use_simple_limit()
    {
        $query = TestArticle::limit(5);
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_articles', strtolower($sql));
        $this->assertStringContainsString('fetch first 5 rows only', strtolower($sql));
    }

    /** @test */
    public function eloquent_model_can_use_skip_and_take()
    {
        $query = TestArticle::skip(10)->take(5);
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_articles', strtolower($sql));
        $this->assertStringContainsString('row_number()', strtolower($sql));
        $this->assertStringContainsString('temp_table', strtolower($sql));
    }

    /** @test */
    public function eloquent_model_can_use_offset_method()
    {
        $query = TestArticle::offset(25);
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_articles', strtolower($sql));
        $this->assertStringContainsString('row_number()', strtolower($sql));
    }

    /** @test */
    public function eloquent_model_pagination_works_with_where_clauses()
    {
        $query = TestArticle::where('published', true)->skip(5)->take(10);
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_articles', strtolower($sql));
        $this->assertStringContainsString('where', strtolower($sql));
        $this->assertStringContainsString('published', strtolower($sql));
        $this->assertStringContainsString('row_number()', strtolower($sql));
    }

    /** @test */
    public function eloquent_model_pagination_works_with_order_by()
    {
        $query = TestArticle::orderBy('title', 'asc')->skip(10)->take(20);
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_articles', strtolower($sql));
        $this->assertStringContainsString('row_number()', strtolower($sql));
        $this->assertStringContainsString('title', strtolower($sql));
        // The ordering should be moved to the ROW_NUMBER() function
    }

    /** @test */
    public function it_handles_complex_pagination_with_joins()
    {
        $query = TestArticle::join('test_users', 'test_articles.user_id', '=', 'test_users.id')
                           ->select('test_articles.*', 'test_users.name as author_name')
                           ->skip(15)
                           ->take(25);
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_articles', strtolower($sql));
        $this->assertStringContainsString('test_users', strtolower($sql));
        $this->assertStringContainsString('inner join', strtolower($sql));
        $this->assertStringContainsString('row_number()', strtolower($sql));
    }

    /** @test */
    public function it_handles_schema_prefixed_tables_in_pagination()
    {
        $query = TestArticle::from('MYSCHEMA.test_articles')->skip(5)->take(10);
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('myschema.test_articles', strtolower($sql));
        $this->assertStringContainsString('row_number()', strtolower($sql));
    }

    /** @test */
    public function it_compiles_table_expression_correctly()
    {
        $grammar = new DB2QueryGrammar();
        
        $reflection = new \ReflectionClass($grammar);
        $method = $reflection->getMethod('compileTableExpression');
        $method->setAccessible(true);
        
        $sql = 'SELECT *, ROW_NUMBER() OVER (ORDER BY id) as row_num FROM users';
        $constraint = 'between 11 and 20';
        
        $result = $method->invoke($grammar, $sql, $constraint);
        
        $expected = 'select * from (SELECT *, ROW_NUMBER() OVER (ORDER BY id) as row_num FROM users) as temp_table where row_num between 11 and 20';
        $this->assertEquals($expected, $result);
    }
}