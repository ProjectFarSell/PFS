<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Symfony\Component\Yaml\Yaml;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = base_path('docs/api/openapi.yaml');
$validator = (new ValidatorBuilder)->fromYamlFile($path)->getResponseValidator();
$schema = $validator->getSchema();
if (! $schema->validate()) {
    fwrite(STDERR, json_encode($schema->getErrors(), JSON_PRETTY_PRINT).PHP_EOL);
    exit(1);
}
$spec = Yaml::parseFile($path);
$count = 0;
foreach ($spec['paths'] as $template => $operations) {
    foreach ($operations as $method => $operation) {
        if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
            continue;
        }
        $example = preg_replace('/\{[^}]+\}/', '00000000-0000-4000-8000-000000000001', $template);
        $route = $app['router']->getRoutes()->match(Request::create('/api/v1'.$example, strtoupper($method)));
        if (! empty($operation['security']) && ! in_array('auth:sanctum', $route->gatherMiddleware(), true)) {
            throw new RuntimeException('Missing authenticated route for '.$method.' '.$template);
        }
        $count++;
    }
}
echo 'PASS: OpenAPI validates and all '.$count.' operations resolve to routes. Runtime response drift is checked by the Mobile* feature tests.'.PHP_EOL;
