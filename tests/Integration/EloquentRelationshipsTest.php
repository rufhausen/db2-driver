<?php

namespace Rufhausen\DB2Driver\Tests\Integration;

use Rufhausen\DB2Driver\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Test models for relationship testing (using unique names to avoid conflicts)
class RelationshipTestUser extends Model
{
    protected $table = 'test_users';
    protected $fillable = ['name', 'email'];
    public $timestamps = true;

    public function posts()
    {
        return $this->hasMany(RelationshipTestPost::class, 'user_id', 'id');
    }

    public function roles()
    {
        return $this->belongsToMany(RelationshipTestRole::class, 'user_roles', 'user_id', 'role_id');
    }
}

class RelationshipTestPost extends Model
{
    protected $table = 'test_posts';
    protected $fillable = ['title', 'content', 'user_id'];
    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(RelationshipTestUser::class, 'user_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany(RelationshipTestComment::class, 'post_id', 'id');
    }
}

class RelationshipTestComment extends Model
{
    protected $table = 'test_comments';
    protected $fillable = ['content', 'post_id'];
    public $timestamps = true;

    public function post()
    {
        return $this->belongsTo(RelationshipTestPost::class, 'post_id', 'id');
    }
}

class RelationshipTestRole extends Model
{
    protected $table = 'test_roles';
    protected $fillable = ['name'];
    public $timestamps = false;

    public function users()
    {
        return $this->belongsToMany(RelationshipTestUser::class, 'user_roles', 'role_id', 'user_id');
    }
}

class EloquentRelationshipsTest extends TestCase
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
    public function it_can_define_has_many_relationship()
    {
        $user = new RelationshipTestUser();
        $relationship = $user->posts();
        
        $this->assertInstanceOf(HasMany::class, $relationship);
        $this->assertEquals('test_posts', $relationship->getRelated()->getTable());
    }

    /** @test */
    public function it_compiles_has_many_query_correctly()
    {
        $user = new RelationshipTestUser();
        $user->id = 1;
        
        $query = $user->posts();
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_posts', strtolower($sql));
        $this->assertStringContainsString('user_id', strtolower($sql));
        $this->assertStringContainsString('where', strtolower($sql));
    }

    /** @test */
    public function it_can_define_belongs_to_relationship()
    {
        $post = new RelationshipTestPost();
        $relationship = $post->user();
        
        $this->assertInstanceOf(BelongsTo::class, $relationship);
        $this->assertEquals('test_users', $relationship->getRelated()->getTable());
    }

    /** @test */
    public function it_compiles_belongs_to_query_correctly()
    {
        $post = new RelationshipTestPost();
        $post->user_id = 1;
        
        $query = $post->user();
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_users', strtolower($sql));
        $this->assertStringContainsString('where', strtolower($sql));
    }

    /** @test */
    public function it_can_define_belongs_to_many_relationship()
    {
        $user = new RelationshipTestUser();
        $relationship = $user->roles();
        
        $this->assertInstanceOf(BelongsToMany::class, $relationship);
        $this->assertEquals('test_roles', $relationship->getRelated()->getTable());
        $this->assertEquals('user_roles', $relationship->getTable());
    }

    /** @test */
    public function it_compiles_belongs_to_many_query_correctly()
    {
        $user = new RelationshipTestUser();
        $user->id = 1;
        
        $query = $user->roles();
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_roles', strtolower($sql));
        $this->assertStringContainsString('user_roles', strtolower($sql));
        $this->assertStringContainsString('inner join', strtolower($sql));
    }

    /** @test */
    public function it_can_eager_load_relationships()
    {
        $query = RelationshipTestUser::with('posts');
        $sql = $query->toSql();
        
        // The main query should still be for users
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_users', strtolower($sql));
    }

    /** @test */
    public function it_can_query_relationship_existence()
    {
        $query = RelationshipTestUser::has('posts');
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_users', strtolower($sql));
        $this->assertStringContainsString('exists', strtolower($sql));
    }

    /** @test */
    public function it_can_query_relationship_with_conditions()
    {
        $query = RelationshipTestUser::whereHas('posts', function ($query) {
            $query->where('title', 'like', '%test%');
        });
        
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_users', strtolower($sql));
        $this->assertStringContainsString('exists', strtolower($sql));
    }

    /** @test */
    public function it_handles_nested_relationships()
    {
        $query = RelationshipTestUser::with('posts.comments');
        $sql = $query->toSql();
        
        // The main query should be for users
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_users', strtolower($sql));
    }

    /** @test */
    public function it_can_count_related_models()
    {
        $query = RelationshipTestUser::withCount('posts');
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_users', strtolower($sql));
        // Should have a subquery for counting
        $this->assertStringContainsString('count', strtolower($sql));
    }

    /** @test */
    public function it_can_join_with_relationships()
    {
        $query = RelationshipTestPost::join('test_users', 'test_posts.user_id', '=', 'test_users.id');
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_posts', strtolower($sql));
        $this->assertStringContainsString('inner join', strtolower($sql));
        $this->assertStringContainsString('test_users', strtolower($sql));
    }
}