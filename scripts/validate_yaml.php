<?php
require __DIR__ . '/../vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
try {
    $data = Yaml::parseFile(__DIR__ . '/../docs/openapi-v1.yaml');
    echo "YAML OK\n";
} catch (Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
