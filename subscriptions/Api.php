<?php

namespace Uberlights\Zoho\Subscriptions;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\Description;

class Api
{
    private $DEFAULTS = [
        'authtoken' => '',
        'zohoOrgId' => '',
        'description' => [
            'jsonPath' => './config/api.json',
            'options' => [],
        ],
        'client' => [
            'base_uri' => 'https://subscriptions.zoho.com/api/v1/',
            'headers' => [
                'Authorization' => 'Zoho-authtoken {{authtoken}}',
                'X-com-zoho-subscriptions-organizationid' => "{{zohoOrgId}}",
                'Content-Type' => 'application/json',
            ],
        ],
    ];

    private $client;
    private $description;
    private $consumer;
    private $options;

    public function __construct($config = [])
    {

        $this->setOptions($config);
        $mergedOptions = $this->options;

        /** @var $description */
        /** @var $client */
        extract($mergedOptions);

        $this->setDescription($description);

        $this->setClient($client);

        return $this->consumer;

    }

    public function setOptions($options = [])
    {

        $defaults = $this->DEFAULTS;
        $defaults['description']['jsonPath'] = __DIR__ . '/' . $defaults['description']['jsonPath'];
        $mergedOptions = array_merge_recursive($defaults, $options);
        $parsedOptioons = $this->parseOptions($mergedOptions, $options);
        $this->options = $parsedOptioons;

        return $this;
    }

    private function parseOptions($options, $rootOption = [])
    {

        // @todo: need to make it is_iterable
        if (is_array($options)) {
            foreach ($options as $key => $option) {
                $options[$key] = $this->parseOptions($option, $rootOption);
            }
        } else {
            if (preg_match('/\{\{/', $options)) {
                foreach ($rootOption as $key => $value) {
                    if (is_scalar($value)) {
                        $options = str_replace('{{' . $key . '}}', $value, $options);
                    }
                }
                return $options;
            }
        }

        return $options;

    }

    public function setDescription($descriptionOptions = [])
    {

        /** @var $jsonPath */
        /** @var $options */
        extract($descriptionOptions);

        if ($descriptionOptions instanceof Description) {
            $this->description = $descriptionOptions;
        } else {
            if (empty($jsonPath)) {
                $jsonPath = __DIR__ . '/' . $this->options['description']['jsonPath'];
            }

            try {

                $description = json_decode(file_get_contents($jsonPath), true);
                if (empty($description['baseUri'])) {
                    $description['baseUri'] = $this->options['client']['base_uri'];
                }

                $this->description = new Description($description, $options);

            } catch (\Exception $ex) {
            }

        }

        return $this;
    }

    public function createClient($clientOptions = [])
    {
        $this->client = new Client($clientOptions);
        return $this;
    }

    private function setClient($clientOptions = [])
    {

        $this->createClient($clientOptions);
        $this->consumer = new GuzzleClient($this->client, $this->description);

        return $this;
    }

    public function __call($name, $arguments)
    {
        $api = $this->consumer;
        if (is_callable([$api, $name])) {
            return call_user_func_array([$api, $name], $arguments);
        }

        throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s().', get_called_class(), $name));
    }
}