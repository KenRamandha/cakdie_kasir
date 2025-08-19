<?php

namespace Illuminate\Http\Middleware;

use Closure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * The CORS options.
     *
     * @var array
     */
    protected $options;

    /**
     * Create a new middleware instance.
     *
     * @param  array  $options
     * @return void
     */
    public function __construct(array $options = [])
    {
        $this->options = $this->normalizeOptions($options);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (! $this->shouldRun($request)) {
            return $next($request);
        }

        // For Preflight requests, return the Preflight response
        if ($this->isPreflightRequest($request)) {
            $response = $this->createEmptyResponse();

            return $this->addPreflightRequestHeaders($response, $request);
        }

        $response = $next($request);

        return $this->addActualRequestHeaders($response, $request);
    }

    /**
     * Determine if the request should run CORS.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function shouldRun($request)
    {
        if (! $this->options['enabled']) {
            return false;
        }

        return $this->isMatchingPath($request);
    }

    /**
     * Check if the path matches the CORS paths.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function isMatchingPath($request)
    {
        if (empty($this->options['paths'])) {
            return false;
        }

        foreach ($this->options['paths'] as $path) {
            if ($path !== '/') {
                $path = trim($path, '/');
            }

            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the request is a preflight request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function isPreflightRequest($request)
    {
        return $request->isMethod('OPTIONS') && $request->headers->has('Access-Control-Request-Method');
    }

    /**
     * Create an empty response for preflight requests.
     *
     * @return \Illuminate\Http\Response
     */
    protected function createEmptyResponse()
    {
        return new Response('', 204);
    }

    /**
     * Add CORS headers for preflight requests.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function addPreflightRequestHeaders($response, $request)
    {
        $this->configureAllowedOrigins($response, $request);

        if ($response->headers->has('Access-Control-Allow-Origin')) {
            $this->configureAllowedMethods($response, $request);
            $this->configureAllowedHeaders($response, $request);
            $this->configureSupportsCredentials($response);
            $this->configureMaxAge($response);
        }

        return $response;
    }

    /**
     * Add CORS headers for actual requests.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function addActualRequestHeaders($response, $request)
    {
        $this->configureAllowedOrigins($response, $request);

        if ($response->headers->has('Access-Control-Allow-Origin')) {
            $this->configureSupportsCredentials($response);
            $this->configureExposedHeaders($response);
        }

        return $response;
    }

    /**
     * Configure the allowed origins.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function configureAllowedOrigins($response, $request)
    {
        $origins = $this->options['allowed_origins'];
        $origin = $request->headers->get('Origin');

        if (in_array('*', $origins)) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
            return;
        }

        if (empty($origin) || ! in_array($origin, $origins)) {
            return;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);

        if (count($this->options['allowed_origins_patterns']) > 0) {
            $response->headers->set('Vary', 'Origin');
        }
    }

    /**
     * Configure the allowed HTTP methods.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function configureAllowedMethods($response, $request)
    {
        $allowMethods = $this->options['allowed_methods'];

        if (in_array('*', $allowMethods)) {
            $allowMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        }

        $response->headers->set('Access-Control-Allow-Methods', implode(', ', $allowMethods));
    }

    /**
     * Configure the allowed headers.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function configureAllowedHeaders($response, $request)
    {
        $allowHeaders = $this->options['allowed_headers'];

        if (in_array('*', $allowHeaders)) {
            $allowHeaders = $request->headers->get('Access-Control-Request-Headers');
        }

        $response->headers->set('Access-Control-Allow-Headers', implode(', ', $allowHeaders));
    }

    /**
     * Configure the exposed headers.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    protected function configureExposedHeaders($response)
    {
        if (! empty($this->options['exposed_headers'])) {
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $this->options['exposed_headers']));
        }
    }

    /**
     * Configure the max age.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    protected function configureMaxAge($response)
    {
        if ($this->options['max_age'] !== null) {
            $response->headers->set('Access-Control-Max-Age', $this->options['max_age']);
        }
    }

    /**
     * Configure the supports credentials.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    protected function configureSupportsCredentials($response)
    {
        if ($this->options['supports_credentials'] === true) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
    }

    /**
     * Normalize the options.
     *
     * @param  array  $options
     * @return array
     */
    protected function normalizeOptions(array $options = [])
    {
        $options += [
            'enabled' => true,
            'paths' => [],
            'allowed_methods' => ['*'],
            'allowed_origins' => ['*'],
            'allowed_origins_patterns' => [],
            'allowed_headers' => ['*'],
            'exposed_headers' => [],
            'max_age' => 0,
            'supports_credentials' => false,
        ];

        // Transform wildcard pattern
        if (in_array('*', $options['allowed_origins'])) {
            $options['allowed_origins'] = ['*'];
        }

        // Transform wildcard pattern
        if (in_array('*', $options['allowed_headers'])) {
            $options['allowed_headers'] = ['*'];
        }

        // Transform wildcard pattern
        if (in_array('*', $options['allowed_methods'])) {
            $options['allowed_methods'] = ['*'];
        }

        return $options;
    }
}