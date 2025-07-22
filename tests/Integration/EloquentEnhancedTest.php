<?php

namespace Rufhausen\DB2Driver\Tests\Integration;

use Rufhausen\DB2Driver\Eloquent\DB2Model;
use Rufhausen\DB2Driver\Eloquent\DB2SoftDeletes;
use Rufhausen\DB2Driver\Eloquent\Casts\DB2Json;
use Rufhausen\DB2Driver\Eloquent\Casts\DB2AsCollection;
use Rufhausen\DB2Driver\Tests\TestCase;
use Carbon\Carbon;

// Enhanced test models using DB2Model
class EnhancedUser extends DB2Model
{
    protected $table = 'enhanced_users';
    protected $fillable = ['name', 'email', 'profile_data', 'settings_collection', 'created_at', 'updated_at'];
    
    protected $casts = [
        'profile_data' => DB2Json::class,
        'settings_collection' => DB2AsCollection::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function posts()
    {
        return $this->hasMany(EnhancedPost::class, 'user_id');
    }

    public function roles()
    {
        return $this->belongsToMany(EnhancedRole::class, 'user_roles', 'user_id', 'role_id');
    }
}

class EnhancedPost extends DB2Model
{
    use DB2SoftDeletes;

    protected $table = 'enhanced_posts';
    protected $fillable = ['title', 'content', 'user_id', 'meta_data', 'published_at', 'created_at', 'updated_at'];
    
    protected $casts = [
        'meta_data' => DB2Json::class,
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(EnhancedUser::class, 'user_id');
    }

    public function comments()
    {
        return $this->hasMany(EnhancedComment::class, 'post_id');
    }
}

class EnhancedComment extends DB2Model
{
    protected $table = 'enhanced_comments';
    protected $fillable = ['content', 'post_id', 'user_id', 'created_at', 'updated_at'];

    public function post()
    {
        return $this->belongsTo(EnhancedPost::class, 'post_id');
    }

    public function user()
    {
        return $this->belongsTo(EnhancedUser::class, 'user_id');
    }
}

class EnhancedRole extends DB2Model
{
    protected $table = 'enhanced_roles';
    protected $fillable = ['name', 'permissions_data'];
    public $timestamps = false;
    
    protected $casts = [
        'permissions_data' => DB2Json::class,
    ];

    public function users()
    {
        return $this->belongsToMany(EnhancedUser::class, 'user_roles', 'role_id', 'user_id');
    }
}

class EloquentEnhancedTest extends TestCase
{
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
    public function it_can_create_enhanced_db2_model()
    {
        $user = new EnhancedUser([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'profile_data' => ['age' => 30, 'city' => 'New York'],
            'settings_collection' => collect(['theme' => 'dark', 'notifications' => true])
        ]);

        $this->assertInstanceOf(DB2Model::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals(['age' => 30, 'city' => 'New York'], $user->profile_data);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $user->settings_collection);
    }

    /** @test */
    public function it_handles_db2_json_casting()
    {
        $user = new EnhancedUser();
        
        // Test setting JSON data
        $profileData = ['age' => 25, 'skills' => ['PHP', 'Laravel', 'DB2']];
        $user->profile_data = $profileData;
        
        // The cast should serialize to JSON string
        $attributes = $user->getAttributes();
        $this->assertIsString($attributes['profile_data']);
        $this->assertJson($attributes['profile_data']);
        
        // When accessed via attribute, should return original array
        $this->assertEquals($profileData, $user->profile_data);
    }

    /** @test */
    public function it_handles_db2_collection_casting()
    {
        $user = new EnhancedUser();
        
        // Test setting collection data
        $settings = collect(['theme' => 'light', 'language' => 'en']);
        $user->settings_collection = $settings;
        
        // Should store as JSON string
        $attributes = $user->getAttributes();
        $this->assertIsString($attributes['settings_collection']);
        
        // When accessed, should return as Collection
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $user->settings_collection);
        $this->assertEquals('light', $user->settings_collection->get('theme'));
    }

    /** @test */
    public function it_handles_db2_date_formats_correctly()
    {
        $user = new EnhancedUser();
        $now = Carbon::now();
        
        $user->created_at = $now;
        
        // Should use the DB2 date format
        $this->assertInstanceOf(Carbon::class, $user->created_at);
        
        // Test date format conversion
        $formatted = $user->fromDateTime($now);
        $this->assertIsString($formatted);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $formatted);
    }

    /** @test */
    public function it_can_build_enhanced_relationships()
    {
        $user = new EnhancedUser(['name' => 'John', 'email' => 'john@test.com']);
        
        // Test hasMany relationship
        $postsRelation = $user->posts();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $postsRelation);
        
        // Test belongsToMany relationship
        $rolesRelation = $user->roles();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $rolesRelation);
    }

    /** @test */
    public function it_handles_soft_deletes_with_db2_enhancements()
    {
        $post = new EnhancedPost([
            'title' => 'Test Post',
            'content' => 'Test content',
            'user_id' => 1
        ]);

        $this->assertTrue(in_array(DB2SoftDeletes::class, class_uses_recursive($post)));
        
        // Test soft delete methods exist
        $this->assertTrue(method_exists($post, 'delete'));
        $this->assertTrue(method_exists($post, 'restore'));
        $this->assertTrue(method_exists($post, 'forceDelete'));
    }

    /** @test */
    public function it_builds_complex_queries_with_relationships()
    {
        $query = EnhancedUser::with('posts.comments')
                            ->whereHas('roles', function ($q) {
                                $q->where('name', 'admin');
                            })
                            ->where('created_at', '>=', Carbon::today());

        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('enhanced_users', strtolower($sql));
        $this->assertStringContainsString('exists', strtolower($sql));
    }

    /** @test */
    public function it_handles_json_queries()
    {
        $query = EnhancedUser::whereJsonContains('profile_data->skills', 'PHP');
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('enhanced_users', strtolower($sql));
    }

    /** @test */
    public function it_can_paginate_enhanced_models()
    {
        $query = EnhancedUser::orderBy('created_at', 'desc')->skip(10)->take(20);
        $sql = $query->toSql();
        
        // Should use DB2 pagination with ROW_NUMBER
        $this->assertStringContainsString('row_number()', strtolower($sql));
        $this->assertStringContainsString('temp_table', strtolower($sql));
    }

    /** @test */
    public function it_handles_upsert_operations()
    {
        // This tests the grammar but we can't actually execute without a real DB
        $grammar = new \Rufhausen\DB2Driver\DB2QueryGrammar();
        
        $values = [
            ['name' => 'John', 'email' => 'john@test.com'],
            ['name' => 'Jane', 'email' => 'jane@test.com']
        ];
        $uniqueBy = ['email'];
        $update = ['name'];
        
        $query = \Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $query->from = 'enhanced_users';
        
        $sql = $grammar->compileUpsert($query, $values, $uniqueBy, $update);
        
        $this->assertStringContainsString('merge into', strtolower($sql));
        $this->assertStringContainsString('enhanced_users', strtolower($sql));
        $this->assertStringContainsString('when matched', strtolower($sql));
        $this->assertStringContainsString('when not matched', strtolower($sql));
    }

    /** @test */
    public function it_handles_attribute_trimming()
    {
        $user = new EnhancedUser();
        $user->name = '  John Doe  '; // With spaces
        
        $attributes = $user->attributesToArray();
        
        // Should trim the spaces
        $this->assertEquals('John Doe', $attributes['name']);
    }

    /** @test */
    public function it_creates_db2_collection_instances()
    {
        $users = collect([
            new EnhancedUser(['name' => 'John']),
            new EnhancedUser(['name' => 'Jane'])
        ]);

        $user = new EnhancedUser();
        $collection = $user->newCollection($users->toArray());

        $this->assertInstanceOf(\Rufhausen\DB2Driver\Eloquent\DB2Collection::class, $collection);
        $this->assertCount(2, $collection);
    }

    /** @test */
    public function it_validates_connection_type()
    {
        $user = new EnhancedUser();
        
        // The getConnection method should validate we're using DB2Connection
        // This will pass in our test environment since we configure DB2 connection
        $this->expectNotToPerformAssertions();
        
        try {
            $connection = $user->getConnection();
            // If we get here, connection is valid DB2 connection
            $this->assertTrue(true);
        } catch (\InvalidArgumentException $e) {
            // This would happen if using non-DB2 connection
            $this->fail('Should use DB2 connection in tests');
        }
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}