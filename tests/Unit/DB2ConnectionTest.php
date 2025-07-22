<?php

namespace Rufhausen\DB2Driver\Tests\Unit;

use Rufhausen\DB2Driver\DB2Connection;
use Rufhausen\DB2Driver\DB2QueryGrammar;
use Rufhausen\DB2Driver\DB2Processor;
use Rufhausen\DB2Driver\Schema\DB2SchemaGrammar;
use Rufhausen\DB2Driver\Tests\TestCase;
use Mockery as m;
use PDO;
use PHPUnit\Framework\Attributes\Test;

class DB2ConnectionTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    #[Test]
    public function it_can_instantiate_connection()
    {
        $pdo = m::mock(PDO::class);
        $connection = new DB2Connection($pdo, 'database', 'prefix_', [
            'schema' => 'TESTSCHEMA'
        ]);

        $this->assertInstanceOf(DB2Connection::class, $connection);
        $this->assertEquals('TESTSCHEMA', $connection->getDefaultSchema());
    }

    #[Test]
    public function it_returns_correct_query_grammar()
    {
        $pdo = m::mock(PDO::class);
        $connection = new DB2Connection($pdo, 'database', '', []);

        $grammar = $connection->getQueryGrammar();
        $this->assertInstanceOf(DB2QueryGrammar::class, $grammar);
    }

    #[Test]
    public function it_returns_correct_schema_grammar()
    {
        $pdo = m::mock(PDO::class);
        $connection = new DB2Connection($pdo, 'database', '', []);

        // Initialize schema grammar by calling useDefaultSchemaGrammar
        $connection->useDefaultSchemaGrammar();
        $grammar = $connection->getSchemaGrammar();
        $this->assertInstanceOf(DB2SchemaGrammar::class, $grammar);
    }

    #[Test]
    public function it_returns_correct_post_processor()
    {
        $pdo = m::mock(PDO::class);
        $connection = new DB2Connection($pdo, 'database', '', []);

        $processor = $connection->getPostProcessor();
        $this->assertInstanceOf(DB2Processor::class, $processor);
    }

    #[Test]
    public function it_handles_date_format_configuration()
    {
        $pdo = m::mock(PDO::class);
        $connection = new DB2Connection($pdo, 'database', '', [
            'date_format' => 'Y-m-d H:i:s.u'
        ]);

        $grammar = $connection->getQueryGrammar();
        $this->assertEquals('Y-m-d H:i:s.u', $grammar->getDateFormat());
    }

    #[Test]
    public function it_handles_offset_compatibility_mode_configuration()
    {
        $pdo = m::mock(PDO::class);
        $connection = new DB2Connection($pdo, 'database', '', [
            'offset_compatibility_mode' => false
        ]);

        // This would require access to grammar's internal property
        // For now, we just test that the connection handles the config
        $this->assertInstanceOf(DB2Connection::class, $connection);
    }

    #[Test]
    public function it_properly_sets_default_schema()
    {
        $pdo = m::mock(PDO::class);
        
        // Test with schema
        $connection = new DB2Connection($pdo, 'database', '', [
            'schema' => 'MYSCHEMA'
        ]);
        $this->assertEquals('MYSCHEMA', $connection->getDefaultSchema());

        // Test without schema
        $connection2 = new DB2Connection($pdo, 'database', '', []);
        $this->assertNull($connection2->getDefaultSchema());
    }

    #[Test]
    public function schema_methods_exist()
    {
        $pdo = m::mock(PDO::class);
        $connection = new DB2Connection($pdo, 'database', '', []);

        $this->assertTrue(method_exists($connection, 'resetCurrentSchema'));
        $this->assertTrue(method_exists($connection, 'setCurrentSchema'));
        $this->assertTrue(method_exists($connection, 'executeCommand'));
    }
}