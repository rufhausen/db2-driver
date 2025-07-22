<?php

namespace Rufhausen\DB2Driver\Tests;

// This basic test verifies the test environment is working
it('can test basic functionality', function () {
    expect(true)->toBeTrue();
    expect(2 + 2)->toBe(4);
});

// Test that we can instantiate core classes
it('can instantiate core driver classes', function () {
    $grammar = new \Rufhausen\DB2Driver\DB2QueryGrammar();
    expect($grammar)->toBeInstanceOf(\Rufhausen\DB2Driver\DB2QueryGrammar::class);
    
    $schemaGrammar = new \Rufhausen\DB2Driver\Schema\DB2SchemaGrammar();
    expect($schemaGrammar)->toBeInstanceOf(\Rufhausen\DB2Driver\Schema\DB2SchemaGrammar::class);
});
