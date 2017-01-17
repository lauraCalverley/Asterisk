<?php namespace Calverley\Asterisk;

use GuzzleHttp\Client;

class Ari {

    public $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'http://192.168.1.86:8088',
            'timeout' => 2.0,
            'auth' => ['tagadmin', 'Install1']
        ]);
    }

    public function getExtensions()
    {
        return $this->client->get('ari/endpoints/PJSIP')->getBody()->getContents();
    }

    public function getChannels()
    {
        return $this->client->get('ari/channels')->getBody()->getContents();
    }

    public function getChannel($channelid)
    {
        return $this->client->get('ari/channels/'.$channelid)->getBody()->getContents();
    }

} 