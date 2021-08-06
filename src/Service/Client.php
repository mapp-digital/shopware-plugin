<?php

namespace Mapp\Connect\Shopware\Service;

use \Firebase\JWT\JWT;
use \Psr\Http\Message\RequestInterface;

class Client {

  protected $integrationId = null;
  protected $secret = null;
  protected $baseUrl = null;
  protected $client = null;

  const USERAGENT = 'MappConnectClientPHP/0.1.0';

  public function __construct($baseUrl, $integrationId, $secret, $options = array()) {
    $this->baseUrl = $baseUrl;
    $this->integrationId = $integrationId;
    $this->secret = $secret;

    $handlerStack = \GuzzleHttp\HandlerStack::create(\GuzzleHttp\choose_handler());
    $handlerStack->push($this->handleAuthorizationHeader());

    $this->client = new \GuzzleHttp\Client([
      'base_uri' => $this->baseUrl,
      'headers'  => [
        'User-Agent' => self::USERAGENT,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'handler' => $handlerStack,
      'timeout'  => isset($options['timeout']) ? $options['timeout'] : 10.0
    ]);
  }

  public function ping() {
    $pong = $this->get('integration/'.$this->integrationId.'/ping');
    if (!$pong || !$pong['pong'])
      return false;
    return true;
  }

  public function connect($config) {
    return $this->post('integration/'.$this->integrationId.'/connect', json_encode($config));
  }

  public function getMessages() {
    return $this->get('integration/'.$this->integrationId.'/message');
  }

  public function getGroups() {
    return $this->get('integration/'.$this->integrationId.'/group');
  }

  public function get($url, $query = null) {
    $req = $this->client->get($url, [
      'headers' => [
        'User-Agent' => self::USERAGENT,
        'Accept'     => 'application/json'
      ],
      'query' => $query
    ]);
    return json_decode( $req->getBody(), true);
  }

  public function event($subtype, $data) {
    return $this->post('integration/'.$this->integrationId.'/event?subtype='.urlencode($subtype), json_encode($data));
  }

  public function put($url, $data = NULL) {
    $req = $this->client->request('PUT', $url, [
      'headers' => [
        'User-Agent' => self::USERAGENT,
        'Accept'     => 'application/json',
      ],
      'body' => $data
    ]);

    return json_decode( $req->getBody(), true);
  }

  public function post($url, $data = NULL) {
    $req = $this->client->request('POST', $url, [
      'headers' => [
        'User-Agent' => self::USERAGENT,
        'Accept'     => 'application/json'
      ],
      'body' => $data
    ]);
    $body = $req->getBody();
    return json_decode( $req->getBody(), true);
  }

  public function getToken(RequestInterface $request) {
    $token = [
      "request-hash" => $this->getRequestHash(
          $request->getUri()->getPath(),
          $request->getBody(),
          $request->getUri()->getQuery()),
      "exp" => time()+3600
    ];
    return JWT::encode($token, $this->secret);
  }

  private function handleAuthorizationHeader() {
    return function (callable $handler) {
        return function (RequestInterface $request, array $options) use ($handler) {
            if ($this->secret) {
                $request = $request->withHeader('auth-token', $this->getToken($request));
            }
            return $handler($request, $options);
        };
    };
  }

  public function getRequestHash(String $url, String $body = NULL, String $queryString = NULL) {
    $url = preg_replace('/^.*\/api\/v/', '/api/v', $url);
    $data = $url;
    if (!empty($body))
      $data .= "|" . $body;
    if (!empty($queryString))
      $data .= "|" . $queryString;
    return sha1($data);
  }
}
