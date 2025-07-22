<?php

namespace Rufhausen\DB2Driver\Tests\Integration;

use Rufhausen\DB2Driver\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

// Test model for our tests
class TestUser extends Model
{
    protected $table = 'test_users';
    protected $fillable = ['name', 'email', 'created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];
    public $timestamps = true;
}

class EloquentModelTest extends TestCase
{
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        
        // Set up test database configuration
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
    public function it_can_instantiate_eloquent_model()
    {
        $user = new TestUser();
        
        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertEquals('test_users', $user->getTable());
        $this->assertTrue($user->usesTimestamps());
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $user = new TestUser();
        
        $expected = ['name', 'email', 'created_at', 'updated_at'];
        $this->assertEquals($expected, $user->getFillable());
    }

    /** @test */
    public function it_can_fill_model_attributes()
    {
        $user = new TestUser();
        $user->fill([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
    }

    /** @test */
    public function it_handles_date_attributes()
    {
        $user = new TestUser();
        
        // Test that date fields are in the dates array
        $this->assertContains('created_at', $user->getDates());
        $this->assertContains('updated_at', $user->getDates());
    }

    /** @test */
    public function it_can_create_query_builder_instance()
    {
        $query = TestUser::query();
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
        $this->assertEquals('test_users', $query->getModel()->getTable());
    }

    /** @test */
    public function eloquent_model_uses_correct_connection_when_configured()
    {
        $user = new TestUser();
        
        // The connection should be the default one we set up
        $connectionName = $user->getConnectionName();
        
        // If no specific connection is set, it should use the default
        $this->assertNull($connectionName); // Uses default connection
    }

    /** @test */
    public function it_can_build_basic_where_queries()
    {
        $query = TestUser::where('name', '=', 'John Doe');
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
        
        // Test the SQL compilation
        $sql = $query->toSql();
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_users', strtolower($sql));
        $this->assertStringContainsString('where', strtolower($sql));
    }

    /** @test */
    public function it_can_build_queries_with_limits()
    {
        $query = TestUser::limit(10);
        
        $sql = $query->toSql();
        
        // DB2 should use FETCH FIRST syntax
        $this->assertStringContainsString('fetch first 10 rows only', strtolower($sql));
    }

    /** @test */
    public function it_can_build_queries_with_ordering()
    {
        $query = TestUser::orderBy('name', 'asc');
        
        $sql = $query->toSql();
        
        $this->assertStringContainsString('order by', strtolower($sql));
        $this->assertStringContainsString('name', strtolower($sql));
        $this->assertStringContainsString('asc', strtolower($sql));
    }

    /** @test */
    public function it_can_build_paginated_queries()
    {
        $query = TestUser::skip(10)->take(5);
        
        $sql = $query->toSql();
        
        // With offset, should use ROW_NUMBER() approach
        $this->assertStringContainsString('row_number()', strtolower($sql));
    }

    /** @test */
    public function it_handles_model_timestamps_configuration()
    {
        $user = new TestUser();
        
        // Test timestamp constants
        $this->assertEquals('created_at', $user->getCreatedAtColumn());
        $this->assertEquals('updated_at', $user->getUpdatedAtColumn());
    }
}

// Test model without timestamps
class TestProduct extends Model
{
    protected $table = 'test_products';
    protected $fillable = ['name', 'price'];
    public $timestamps = false;
}

class EloquentModelWithoutTimestampsTest extends TestCase
{
    /** @test */
    public function it_can_create_model_without_timestamps()
    {
        $product = new TestProduct();
        
        $this->assertInstanceOf(TestProduct::class, $product);
        $this->assertFalse($product->usesTimestamps());
        $this->assertEquals('test_products', $product->getTable());
    }
}