<?php

namespace Uberlights\Zoho\Subscriptions\Support;


class Import
{
    private $data = '';
    private $options = [];
    private $rootPath;

    public function __construct($filePath = '', $options = [])
    {
        $data = '';
        $this->rootPath = $rootPath = realpath(__DIR__ . './../');
        if (!file_exists($filePath)) {
            $filePath = realpath($rootPath . '/' . $filePath);
        }
        if (!file_exists($filePath)) {
            $filePath = realpath($rootPath . '/config/postman.json');
        }
        if (file_exists($filePath)) {
            $data = $this->importFromJson($filePath, $options);
        }
        $this->data = $data;
        $this->options = $options;
    }

    public function getData()
    {
        return $this->data;
    }
    public function writeData($path = '')
    {
        if (empty($path)) {
            $path = realpath($this->rootPath . '/config/api.json');
        }
        if (file_exists($path)) {
            rename($path, $path.'.bak');
        }
        $json = \GuzzleHttp\json_encode($this->data, JSON_PRETTY_PRINT | JSON_ERROR_NONE | JSON_UNESCAPED_SLASHES);
        return file_put_contents($path, $json);
    }


    private function importFromJson($filePath)
    {
        if (!file_exists($filePath)) {
            $filePath = realpath($this->rootPath . '/' . $filePath);
        }
        $json = \GuzzleHttp\json_decode(file_get_contents($filePath), true);
        $data = [];

        if (!empty($json)) {
            $items = $json['item'];

            $operations = [];
            foreach ($items as $apiGroup) {
                $apiGroupName = $apiGroup['name'];
                $apiGroupDescription = $apiGroup['description'];
                $apiGroupItems = $apiGroup['item'];
                foreach ($apiGroupItems as $api) {
                    $apiName = $this->sluggify($api['name']);
                    $request = $api['request'];
                    $operation = $this->parseOperation($request);
                    $operations[$apiName] = $operation;
                }

            }

            if (!empty($operations)) {
                $data = compact('operations');
                $data['models'] = [
                    'getResponse' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            'location' => 'json',
                        ],
                    ],
                ];
            }

        }

        return $data;
    }

    private function parseOperation($request = [])
    {
        $meta = [$request];
        $method = $request['method'];
        $header = $request['header'];
        $body = $request['body'];
        $url = $request['url'];
        $data = [
            'httpMethod' => $method,
            'uri' => $this->parseUri($url, $meta),
            'responseModel' => 'getResponse',
            'parameters' => $this->parseParams($url['path'], $body['raw'], $meta),
        ];
        return $data;

    }

    private function parseParams($paths = [], $bodyRaw = '', $meta = [])
    {
        $params = [];
        foreach ($paths as $path) {
            if (!!preg_match('/^\{/', $path) !== false) {
                $paramName = preg_replace('/^\{|\}$/', '', $path);
                $params[$paramName] = [
                    'location' => 'uri', //query
                    // 'type' => 'string',
                    // 'default' => '',
                    // 'required' => 'false',
                    // 'sentAs' => '',
                    // 'instanceOf' => '',
                    // 'filters' => [],
                ];

            }
        }
        $rawParams = json_decode($bodyRaw, true);
        if (!empty($rawParams)) {
            foreach ($rawParams as $paramName => $paramItem) {
                $params[$paramName] = [
                    'location' => 'json', //formParam//json//body
                    // 'type' => 'string',
                    // 'default' => '',
                    // 'required' => 'false',
                    // 'sentAs' => '',
                    // 'instanceOf' => '',
                    // 'filters' => [],
                ];
            }
        }
        return $params;

    }

    private function parseUri($url, $meta = [])
    {
        return implode('/', array_slice($url['path'], 2));
    }

    private function sluggify($string = '')
    {
        if (!empty($string)) {
            $regexs = ['/[^\w\s_.-]|\s+?/', '/\-+?/'];
            $replacer = ['-', ' '];
            $string = str_replace(' ', '',
                lcfirst(ucwords(preg_replace($regexs, $replacer, strtolower($string)))));
        }
        return $string;
    }
}