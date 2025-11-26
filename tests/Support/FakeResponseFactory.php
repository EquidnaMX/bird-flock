<?php

namespace Equidna\BirdFlock\Tests\Support;

use BadMethodCallException;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lightweight response factory for package tests.
 */
class FakeResponseFactory implements ResponseFactory
{
    public function make($content = '', $status = 200, array $headers = [])
    {
        return new Response($content, $status, $headers);
    }

    public function noContent($status = 204, array $headers = [])
    {
        return $this->make('', $status, $headers);
    }

    public function view($view, $data = [], $status = 200, array $headers = [])
    {
        $content = is_array($view) ? json_encode($view) : (string) $view;

        if (!empty($data)) {
            $content .= json_encode($data);
        }

        return $this->make($content, $status, $headers);
    }

    public function json($data = [], $status = 200, array $headers = [], $options = 0)
    {
        return new JsonResponse($data, $status, $headers, $options);
    }

    public function jsonp($callback, $data = [], $status = 200, array $headers = [], $options = 0)
    {
        $response = $this->json($data, $status, $headers, $options);
        $response->setCallback($callback);

        return $response;
    }

    public function stream($callback, $status = 200, array $headers = [])
    {
        return new StreamedResponse($callback, $status, $headers);
    }

    public function streamJson($data, $status = 200, $headers = [], $encodingOptions = 15)
    {
        return new StreamedJsonResponse($data, $status, $headers, $encodingOptions);
    }

    public function streamDownload($callback, $name = null, array $headers = [], $disposition = 'attachment')
    {
        $headers = array_merge($headers, [
            'Content-Disposition' => $this->formatDisposition($disposition, $name),
        ]);

        return $this->stream($callback, 200, $headers);
    }

    public function download($file, $name = null, array $headers = [], $disposition = 'attachment')
    {
        throw new BadMethodCallException('File downloads are not supported in test response factory.');
    }

    public function file($file, array $headers = [])
    {
        throw new BadMethodCallException('File responses are not supported in test response factory.');
    }

    public function redirectTo($path, $status = 302, $headers = [], $secure = null)
    {
        return new RedirectResponse($path, $status, $headers);
    }

    public function redirectToRoute($route, $parameters = [], $status = 302, $headers = [])
    {
        $url = (string) $route;

        if (!empty($parameters)) {
            $url .= '?' . http_build_query((array) $parameters);
        }

        return $this->redirectTo($url, $status, $headers);
    }

    public function redirectToAction($action, $parameters = [], $status = 302, $headers = [])
    {
        return $this->redirectToRoute($action, $parameters, $status, $headers);
    }

    public function redirectGuest($path, $status = 302, $headers = [], $secure = null)
    {
        return $this->redirectTo($path, $status, $headers, $secure);
    }

    public function redirectToIntended($default = '/', $status = 302, $headers = [], $secure = null)
    {
        return $this->redirectTo($default, $status, $headers, $secure);
    }

    private function formatDisposition(?string $disposition, ?string $name): string
    {
        if (!$name) {
            return $disposition ?? 'attachment';
        }

        return sprintf('%s; filename=\"%s\"', $disposition ?? 'attachment', $name);
    }
}
