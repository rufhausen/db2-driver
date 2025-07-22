<?php

namespace Rufhausen\DB2Driver\Tests\Integration;

use Rufhausen\DB2Driver\DB2QueryGrammar;
use Rufhausen\DB2Driver\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class TestEvent extends Model
{
    protected $table = 'test_events';
    protected $fillable = ['name', 'start_date', 'end_date', 'created_at', 'updated_at'];
    protected $dates = ['start_date', 'end_date', 'created_at', 'updated_at'];
    public $timestamps = true;
}

class DateTimeHandlingTest extends TestCase
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
    public function it_uses_default_date_format()
    {
        $grammar = new DB2QueryGrammar();
        $format = $grammar->getDateFormat();
        
        // Should return a valid date format string
        $this->assertIsString($format);
        $this->assertNotEmpty($format);
    }

    /** @test */
    public function it_can_set_custom_date_format()
    {
        $grammar = new DB2QueryGrammar();
        $customFormat = 'Y-m-d H:i:s.u';
        
        $grammar->setDateFormat($customFormat);
        $this->assertEquals($customFormat, $grammar->getDateFormat());
    }

    /** @test */
    public function it_handles_standard_laravel_date_format()
    {
        $grammar = new DB2QueryGrammar();
        $laravelFormat = 'Y-m-d H:i:s';
        
        $grammar->setDateFormat($laravelFormat);
        $this->assertEquals($laravelFormat, $grammar->getDateFormat());
    }

    /** @test */
    public function it_handles_microsecond_date_format()
    {
        $grammar = new DB2QueryGrammar();
        $microsecondFormat = 'Y-m-d H:i:s.u';
        
        $grammar->setDateFormat($microsecondFormat);
        $this->assertEquals($microsecondFormat, $grammar->getDateFormat());
    }

    /** @test */
    public function it_handles_db2_specific_date_format()
    {
        $grammar = new DB2QueryGrammar();
        $db2Format = 'Y-m-d-H.i.s.u';
        
        $grammar->setDateFormat($db2Format);
        $this->assertEquals($db2Format, $grammar->getDateFormat());
    }

    /** @test */
    public function eloquent_model_handles_date_attributes()
    {
        $event = new TestEvent();
        
        $dateFields = $event->getDates();
        $this->assertContains('start_date', $dateFields);
        $this->assertContains('end_date', $dateFields);
        $this->assertContains('created_at', $dateFields);
        $this->assertContains('updated_at', $dateFields);
    }

    /** @test */
    public function eloquent_model_can_set_date_attributes()
    {
        $event = new TestEvent();
        $now = Carbon::now();
        
        $event->start_date = $now;
        $event->end_date = $now->addHours(2);
        
        // Should be Carbon instances
        $this->assertInstanceOf(Carbon::class, $event->start_date);
        $this->assertInstanceOf(Carbon::class, $event->end_date);
    }

    /** @test */
    public function eloquent_model_can_query_with_date_conditions()
    {
        $query = TestEvent::where('start_date', '>=', Carbon::today());
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_events', strtolower($sql));
        $this->assertStringContainsString('start_date', strtolower($sql));
        $this->assertStringContainsString('where', strtolower($sql));
    }

    /** @test */
    public function eloquent_model_can_query_with_date_between()
    {
        $startDate = Carbon::today();
        $endDate = Carbon::tomorrow();
        
        $query = TestEvent::whereBetween('start_date', [$startDate, $endDate]);
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('test_events', strtolower($sql));
        $this->assertStringContainsString('start_date', strtolower($sql));
        $this->assertStringContainsString('between', strtolower($sql));
    }

    /** @test */
    public function eloquent_model_handles_timestamp_columns()
    {
        $event = new TestEvent();
        
        $this->assertEquals('created_at', $event->getCreatedAtColumn());
        $this->assertEquals('updated_at', $event->getUpdatedAtColumn());
        $this->assertTrue($event->usesTimestamps());
    }

    /** @test */
    public function eloquent_model_can_order_by_dates()
    {
        $query = TestEvent::orderBy('start_date', 'desc');
        $sql = $query->toSql();
        
        $this->assertStringContainsString('order by', strtolower($sql));
        $this->assertStringContainsString('start_date', strtolower($sql));
        $this->assertStringContainsString('desc', strtolower($sql));
    }

    /** @test */
    public function eloquent_model_can_group_by_date_parts()
    {
        $query = TestEvent::selectRaw('DATE(start_date) as date, COUNT(*) as count')
                          ->groupByRaw('DATE(start_date)');
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('date(start_date)', strtolower($sql));
        $this->assertStringContainsString('count(*)', strtolower($sql));
        $this->assertStringContainsString('group by', strtolower($sql));
    }

    /** @test */
    public function it_can_handle_null_dates()
    {
        $query = TestEvent::whereNull('end_date');
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('end_date', strtolower($sql));
        $this->assertStringContainsString('is null', strtolower($sql));
    }

    /** @test */
    public function it_can_handle_not_null_dates()
    {
        $query = TestEvent::whereNotNull('end_date');
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('end_date', strtolower($sql));
        $this->assertStringContainsString('is not null', strtolower($sql));
    }

    /** @test */
    public function it_can_query_with_date_functions()
    {
        $query = TestEvent::whereRaw('YEAR(start_date) = ?', [2024]);
        $sql = $query->toSql();
        
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('year(start_date)', strtolower($sql));
    }
}