<?php
// tests/ResponseTest.php

require_once __DIR__ . '/../src/Response.php';

class ResponseTest {
    public function run() {
        echo "Running ResponseTest...\n";
        
        // This is a manual test script since we are using vanilla PHP without PHPUnit
        // In a production environment, you would use PHPUnit.
        
        try {
            // Test if methods exist
            if (!method_exists('Response', 'json')) throw new Exception('Response::json missing');
            if (!method_exists('Response', 'error')) throw new Exception('Response::error missing');
            if (!method_exists('Response', 'success')) throw new Exception('Response::success missing');
            
            echo "✓ Methods exist\n";
            echo "All tests passed!\n";
        } catch (Exception $e) {
            echo "✗ Test failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
}

$test = new ResponseTest();
$test->run();
