<?php

namespace Chrispittman\Lti1;

/*
 * This product includes software developed at
 * Google Inc. (http://www.google.es/about.html)
 * under Apache 2.0 License (http://www.apache.org/licenses/LICENSE-2.0.html).
 *
 * See http://google-api-dfp-php.googlecode.com.
 *
 */
abstract class OAuthSignatureMethod {
  public function check_signature(OAuthRequest &$request, OAuthConsumer $consumer, OAuthToken $token, $signature) {
    $built = $this->build_signature($request, $consumer, $token);
    return $built == $signature;
  }

  abstract public function get_name();
  abstract public function build_signature(OAuthRequest $request, OAuthConsumer $consumer, OAuthToken $token=null);
}



