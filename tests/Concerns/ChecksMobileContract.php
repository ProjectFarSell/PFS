<?php

namespace Tests\Concerns;

use GuzzleHttp\Psr7\Response;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Symfony\Component\Yaml\Yaml;

trait ChecksMobileContract
{
    public function json($method, $uri, array $data = [], array $headers = [], $options = 0)
    {
        $response = parent::json($method, $uri, $data, $headers, $options);

        return $this->assertMobileResponse($response, $method, $uri);
    }

    protected function assertMobileResponse($response, string $method, string $uri)
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if (! str_starts_with($path, '/api/v1/')) {
            return $response;
        }
        $relative = substr($path, strlen('/api/v1'));
        static $schema = null;
        static $validator = null;
        $schema ??= Yaml::parseFile(base_path('docs/api/openapi.yaml'));
        $validator ??= (new ValidatorBuilder)->fromYamlFile(base_path('docs/api/openapi.yaml'))->getResponseValidator();
        foreach ($schema['paths'] as $template => $operations) {
            if (! isset($operations[strtolower($method)]) || ! OperationAddress::isPathMatchesSpec($template, $relative)) {
                continue;
            }
            try {
                $body = $response->getContent();
                if ($body === false) {
                    $body = $response->streamedContent();
                }
                $validator->validate(new OperationAddress($template, strtolower($method)), new Response($response->getStatusCode(), $response->headers->all(), $body));
            } catch (\Throwable $exception) {
                $messages = [];
                do {
                    $messages[] = $exception->getMessage();
                } while ($exception = $exception->getPrevious());
                $this->fail('OpenAPI response drift: '.$method.' '.$template.' HTTP '.$response->getStatusCode().' — '.implode(' > ', $messages));
            }
            break;
        }

        return $response;
    }
}
