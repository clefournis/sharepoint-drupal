<?php

/**
 * A component that communicates with a Drupal 7 website.
 */
class DrupalRest
{
    private $username;
    private $password;
    private $session;
    private $host;
    private $hostendpoint;
    private $debug;
    private $csrf_token;

  /**
   *
   * @param $host
   *   The host of the site e.g. http://yoursite.com
   * @param $endpoint
   *   The endpoint that you want to access. e.g. rest (from http://yoursite.com/rest)
   * @param $username
   *   The username of the user you want to login with to the drupal site
   * @param $password
   *   The password of the user you want to login with to the drupal site
   * @param $debug
   *   A bool value if you want it to be in debug mode
   */
  public function __construct($host, $endpoint, $username, $password, $debug)
  {
      $this->username = $username;
      $this->password = $password;
      $this->hostendpoint = $this->_trailSlashFilter($host) . '/' . $this->_trailSlashFilter($endpoint) . '/';
      $this->host = $this->_trailSlashFilter($host) . '/';
      $this->debug = $debug;
  }

  /**
   * Logging in to the remote drupal system.
   */
  public function login()
  {
      $ch = curl_init($this->hostendpoint . 'user/login.json');
      $post_data = array(
        'name' => $this->username,
        'pass' => $this->password,
      );
      $post = http_build_query($post_data, '', '&');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HEADER, false);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Accept: application/json",
        "Content-type: application/x-www-form-urlencoded",
      ));
      $response = json_decode(curl_exec($ch));

      // Save Session information to be sent as cookie with future calls.
      $this->session = $response->session_name . '=' . $response->sessid;

      // GET CSRF Token.
      curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => 1,
       CURLOPT_URL => $this->host . 'services/session/token',
      ));
      curl_setopt($ch, CURLOPT_COOKIE, "$this->session");

      $ret = new stdClass();
      $ret->response = curl_exec($ch);
      $ret->error    = curl_error($ch);
      $ret->info     = curl_getinfo($ch);

      $this->csrf_token = $ret->response;
  }

  /**
   * Retrieve a node from a node id.
   */
  public function retrieveNode($nid)
  {
      $result = new stdClass();
      $result->ErrorCode = null;

      $nid = (int) $nid;
      $ch = curl_init($this->hostendpoint . 'node/' . $nid);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HEADER, true);
      curl_setopt($ch, CURLINFO_HEADER_OUT, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Accept: application/json",
        "Content-type: application/x-www-form-urlencoded",
        "Cookie: $this->session",
        'X-CSRF-Token: ' . $this->csrf_token,
      ));

      $result = $this->_handleResponse($ch);
      curl_close($ch);

      return $result;
  }

  /**
   *
   * @param $node
   *   An array of the node that you want to create
   * @param $node['title']
   *   The title of the node
   * @param $node['type']
   *   The content type of the node you want to create. It is required.
   * @param $node['body']['und'][0]['value']
   *   You can specify the other fields you want to change the same way.
   *   E.g. $node['body']['und'][0]['value']
   */
  public function createNode($node)
  {
      $post = http_build_query($node, '', '&');
      $ch = curl_init($this->hostendpoint . 'node');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HEADER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
      curl_setopt($ch, CURLOPT_HTTPHEADER,
      array(
        "Accept: application/json",
        "Content-type: application/x-www-form-urlencoded",
        "Cookie: $this->session",
        'X-CSRF-Token: ' . $this->csrf_token,
      ));

      $result = $this->_handleResponse($ch);
      curl_close($ch);

      return $result;
  }

  /**
   *
   * @param $node
   *   An array of the node that you want to create
   * @param $node['nid']
   *   The id of the node you want to edit. It is required.
   * @param $node['body']['und'][0]['value']
   *   You can specify the other fields you want to change the same way.
   *   E.g. $node['title'] or $node['body']['und'][0]['value']
   */
  public function updateNode($node)
  {
      $post = http_build_query($node, '', '&');
      $ch = curl_init($this->hostendpoint . 'node/' . $node['nid']);

      // Emulate file.
      $putData = fopen('php://temp', 'rw+');
      fwrite($putData, $post);
      fseek($putData, 0);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HEADER, true);
      curl_setopt($ch, CURLOPT_PUT, true);
      curl_setopt($ch, CURLOPT_INFILE, $putData);
      curl_setopt($ch, CURLOPT_INFILESIZE, mb_strlen($post));
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Accept: application/json",
        "Content-type: application/x-www-form-urlencoded",
        "Cookie: $this->session",
        'X-CSRF-Token: ' . $this->csrf_token,
      ));

      $result = $this->_handleResponse($ch);
      curl_close($ch);

      return $result;
  }

  /**
   * Retrieve a file based on fid.
   */
  public function retrieveFile($fid)
  {
      $result = new stdClass();
      $result->ErrorCode = null;

      $fid = (int) $fid;
      $ch = curl_init($this->hostendpoint . 'file/' . $fid);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HEADER, true);
      curl_setopt($ch, CURLINFO_HEADER_OUT, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Accept: application/json",
        "Cookie: $this->session",
      ));

      $result = $this->_handleResponse($ch);
      curl_close($ch);

      return $result;
  }

  /**
   * Create a file - see the examples for more info.
   */
  public function createFile($file)
  {
      $post = http_build_query($file, '', '&');
      $ch = curl_init($this->hostendpoint . 'file');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HEADER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Accept: application/json",
        "Content-type: application/x-www-form-urlencoded",
        "Cookie: $this->session",
        'X-CSRF-Token: ' . $this->csrf_token,
      ));

      $result = $this->_handleResponse($ch);
      curl_close($ch);

      return $result;
  }

  /**
   *
   * @param $string
   *   The string of the host or the endpoint that have to be checked for slashes
   *
   *   A helper function for removing the trailing slash from the $host variable
   *   at the end or from the $endpoint variable from the beginning
   */
  private function _trailSlashFilter($string)
  {
      if (substr($string, -1) == '/') {
          $string = substr($string, 0, -1);
      }

      if (substr($string, 0, 1) == '/') {
          $string = substr($string, 1);
      }

      return $string;
  }

  /**
   *
   * @param $ch
   *   The cURL handle
   */
  private function _handleResponse($ch)
  {
      $response = curl_exec($ch);
      $info = curl_getinfo($ch);

      // Break apart header & body.
      $header = substr($response, 0, $info['header_size']);
      $body = substr($response, $info['header_size']);

      $result = new stdClass();

      if ($info['http_code'] != '200') {
          $header_arrray = explode("\n", $header);
          $result->ErrorCode = $info['http_code'];
          $result->ErrorText = $header_arrray['0'];
      } else {
          $result->ErrorCode = null;
          $decodedBody = json_decode($body);
          $result = (object) array_merge((array) $result, (array) $decodedBody);
      }

      if ($this->debug) {
          $result->header = $header;
          $result->body = $body;
      }

      return $result;
  }
}


$request = new DrupalRest('http://example.com/', '/restendpoint', 'user', 'name', 0);


print_r($request);
print '<hr>';

/**
 * Login.
 */
$request->login();
print_r($request);
print '<hr>';


/**
 * Create a node.
 */
$node_data = array(
  'title' => 'This is a node created via a RESP API call.',
  'type' => 'article',
  'body[und][0][format]' =>'full_html',
  'body[und][0][value]' => 'Curabitur aliquet quam id dui posuere blandit. Praesent sapien massa, convallis a pellentesque nec, egestas non nisi. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Donec velit neque, auctor sit amet aliquam vel, ullamcorper sit amet ligula. Quisque velit nisi, pretium ut lacinia in, elementum id enim. Cras ultricies ligula sed magna dictum porta. Donec sollicitudin molestie malesuada. Donec sollicitudin molestie malesuada. Vivamus suscipit tortor eget felis porttitor volutpat. Mauris blandit aliquet elit, eget tincidunt nibh pulvinar a. Mauris blandit aliquet elit, eget tincidunt nibh pulvinar a.',
);



$node = $request->createNode($node_data);
$json_node = json_encode($node_data); 
var_dump($json_node);
print '<hr>';

/**
 * Update a node.
 */

// $node_data = array(
//   'nid' => 24166,
//   'title' => 'Vive la France!!',  
//   'field_node_image[und][0][fid]' =>  24206,
//   'field_node_image[und][0][filename]' => 'Vive les Etats Unis',
//   'field_node_image[und][0][field_file_image_alt_text][und][0][value]' => 'beautiful image',
// );

// $node = $request->updateNode($node_data);
// print_r($node);
// print '<hr>';


/**
 * Upload an image.
 */

// $path = 'rtaImage.png';
// $base64 = base64_encode(file_get_contents($path));

// $file_data = array(
//   "filename" => "rtaImage.png",
//   "file" => $base64,
//   "alt" => "Mexico",
//   "title" => "Mexico title",
  // 'field_file_image_alt_text[und][0][value]' => 'This is the alternate text.',
  // 'field_file_image_title_text[und][0][value]' => 'This is the title text.',
// );

// $file = $request->createFile($file_data);
// print '<hr>';

/**
 * Get an image.
 */

// $file = $request->retrieveFile($file->fid);
// $test = json_encode($file);

// print_r($test);
// print '<hr>';




