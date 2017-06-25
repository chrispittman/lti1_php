<?php

namespace Chrispittman\Lti1;

use DOMDocument;
use DOMException;
use Exception;

class LTIUtils {
    //
    // LAUNCH FUNCTIONS
    //
    public function is_basic_lti_request() {
        $good_message_type = "basic-lti-launch-request" == $_POST['lti_message_type'];
        $good_lti_version = "LTI-1p0" == $_POST['lti_version'];
        return $good_message_type && $good_lti_version;
    }

    public function validate_oauth_signature($oauth_consumer_key, $shared_secret, $request_url) {
        $store = new TrivialOAuthDataStore();
        $store->add_consumer($oauth_consumer_key, $shared_secret);

        $server = new OAuthServer($store);

        $method = new OAuthSignatureMethodHMACSHA1();
        $server->add_signature_method($method);
        $request = OAuthRequest::from_request("POST", $request_url);

        $server->verify_request($request);
    }

    public function is_valid_oauth_signature($oauth_consumer_key, $shared_secret, $request_url) {
        try {
            $this->validate_oauth_signature($oauth_consumer_key, $shared_secret, $request_url);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    //
    // OUTCOME SERVICE FUNCTIONS
    //
    public function update_outcome_in_canvas($oauth_consumer_key, $shared_secret, $lti_sourced_id, $lis_outcome_service_url, $score)
    {
        if ($score>1) {$score = 1;}
        if ($score<0) {$score = 0;}

        $xmlRequest =
            "<?xml version = \"1.0\" encoding = \"UTF-8\"?>
<imsx_POXEnvelopeRequest xmlns=\"http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0\">
    <imsx_POXHeader>
        <imsx_POXRequestHeaderInfo>
            <imsx_version>V1.0</imsx_version>
            <imsx_messageIdentifier>999999123</imsx_messageIdentifier>
        </imsx_POXRequestHeaderInfo>
    </imsx_POXHeader>
    <imsx_POXBody>
        <replaceResultRequest>
            <resultRecord>
                <sourcedGUID>
                    <sourcedId>$lti_sourced_id</sourcedId>
                </sourcedGUID>
                <result>
                    <resultScore>
                        <language>en</language>
                        <textString>".$score."</textString>
                    </resultScore>
                </result>
            </resultRecord>
        </replaceResultRequest>
    </imsx_POXBody>
</imsx_POXEnvelopeRequest>";

        $hash = base64_encode(sha1($xmlRequest, TRUE));
        $params = array('oauth_body_hash'=>$hash);

        $hmac_method = new OAuthSignatureMethodHMACSHA1();
        $consumer = new OAuthConsumer($oauth_consumer_key, $shared_secret, NULL);

        $req = OAuthRequest::from_consumer_and_token($consumer, NULL, 'POST', $lis_outcome_service_url, $params);
        $req->sign_request($hmac_method, $consumer, NULL);
        $header = $req->to_header();
        $header .= "\nContent-type: application/xml";

        $ext_response = LTIUtils::do_post_request($lis_outcome_service_url, $xmlRequest, $header);

        try {
            $ext_doc = new DOMDocument();
            set_error_handler(array($this,'HandleXmlError'));
            $ext_doc->loadXML($ext_response);
            restore_error_handler();
            $ext_nodes = LTIUtils::domnode_to_array($ext_doc->documentElement);
            if (!isset($ext_nodes['imsx_POXHeader']['imsx_POXResponseHeaderInfo']['imsx_statusInfo']['imsx_codeMajor'])) {
                throw new Exception("No imsx_codeMajor from outcome service for ".$lti_sourced_id);
            }
            if ($ext_nodes['imsx_POXHeader']['imsx_POXResponseHeaderInfo']['imsx_statusInfo']['imsx_codeMajor'] != 'success'
                && isset($ext_nodes['imsx_POXHeader']['imsx_POXResponseHeaderInfo']['imsx_statusInfo']['imsx_description'])
                && strpos($ext_nodes['imsx_POXHeader']['imsx_POXResponseHeaderInfo']['imsx_statusInfo']['imsx_description'],'User is no longer in course') === false ) {
                throw new Exception("No success code from outcome service for ".$lti_sourced_id);
            }
        } catch (Exception $e) {
            $msg = "Exception while talking to outcome service for ".$lti_sourced_id." -> ".$ext_response;
            throw new \Exception($msg);
        }
    }

    protected function HandleXmlError($errno, $errstr, $errfile, $errline) {
        if ($errno==E_WARNING && (substr_count($errstr,"DOMDocument::loadXML()")>0)) {
            throw new DOMException($errstr);
        } else {
            return false;
        }
    }

    public function delete_outcome_in_canvas($oauth_consumer_key, $shared_secret, $lti_sourced_id, $lis_outcome_service_url) {
        $xmlRequest =
            "<?xml version = \"1.0\" encoding = \"UTF-8\"?>
<imsx_POXEnvelopeRequest xmlns=\"http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0\">
    <imsx_POXHeader>
        <imsx_POXRequestHeaderInfo>
            <imsx_version>V1.0</imsx_version>
            <imsx_messageIdentifier>999999123</imsx_messageIdentifier>
        </imsx_POXRequestHeaderInfo>
    </imsx_POXHeader>
    <imsx_POXBody>
        <deleteResultRequest>
            <resultRecord>
                <sourcedGUID>
                    <sourcedId>$lti_sourced_id</sourcedId>
                </sourcedGUID>
            </resultRecord>
        </deleteResultRequest>
    </imsx_POXBody>
</imsx_POXEnvelopeRequest>";

        $hash = base64_encode(sha1($xmlRequest, TRUE));
        $params = array('oauth_body_hash'=>$hash);

        $hmac_method = new OAuthSignatureMethodHMACSHA1();
        $consumer = new OAuthConsumer($oauth_consumer_key, $shared_secret, NULL);

        $req = OAuthRequest::from_consumer_and_token($consumer, NULL, 'POST', $lis_outcome_service_url, $params);
        $req->sign_request($hmac_method, $consumer, NULL);
        $header = $req->to_header();
        $header .= "\nContent-type: application/xml";

        $ext_response = $this->do_post_request($lis_outcome_service_url, $xmlRequest, $header);

        try {
            $ext_doc = new DOMDocument();
            set_error_handler(array($this,'HandleXmlError'));
            $ext_doc->loadXML($ext_response);
            restore_error_handler();
            $ext_nodes = $this->domnode_to_array($ext_doc->documentElement);
            if (!isset($ext_nodes['imsx_POXHeader']['imsx_POXResponseHeaderInfo']['imsx_statusInfo']['imsx_codeMajor'])) {
                throw new Exception("No imsx_codeMajor from outcome service for ".$lti_sourced_id);
            }
            if ($ext_nodes['imsx_POXHeader']['imsx_POXResponseHeaderInfo']['imsx_statusInfo']['imsx_codeMajor'] != 'success'
                && isset($ext_nodes['imsx_POXHeader']['imsx_POXResponseHeaderInfo']['imsx_statusInfo']['imsx_description'])
                && $ext_nodes['imsx_POXHeader']['imsx_POXResponseHeaderInfo']['imsx_statusInfo']['imsx_description'] != 'User is no longer in course' ) {
                throw new Exception("No success code from outcome service for ".$lti_sourced_id);
            }
        } catch (Exception $e) {
            $msg = "Exception while talking to outcome service for ".$lti_sourced_id." -> ".$ext_response;
            throw new \Exception($msg);
        }
    }

    protected function domnode_to_array($node) {
        $output = array();
        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
            case XML_TEXT_NODE:
                $output = trim($node->textContent);
                break;
            case XML_ELEMENT_NODE:
                for ($i=0, $m=$node->childNodes->length; $i<$m; $i++) {
                    $child = $node->childNodes->item($i);
                    $v = LTIUtils::domnode_to_array($child);
                    if(isset($child->tagName)) {
                        $t = $child->tagName;
                        if(!isset($output[$t])) {
                            $output[$t] = array();
                        }
                        $output[$t][] = $v;
                    }
                    elseif($v) {
                        $output = (string) $v;
                    }
                }
                if(is_array($output)) {
                    if($node->attributes->length) {
                        $a = array();
                        foreach($node->attributes as $attrName => $attrNode) {
                            $a[$attrName] = (string) $attrNode->value;
                        }
                        $output['@attributes'] = $a;
                    }
                    foreach ($output as $t => $v) {
                        if(is_array($v) && count($v)==1 && $t!='@attributes') {
                            $output[$t] = $v[0];
                        }
                    }
                }
                break;
        }
        return $output;
    }

    private function do_post_request($url, $params, $header = NULL) {
        if (is_array($params)) {
            $data = http_build_query($params);
        } else {
            $data = $params;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!empty($header)) {
            $headers = explode("\n", $header);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        $resp = curl_exec($ch);
        if ($resp === FALSE) {
            throw new Exception("Error code ".curl_getinfo($ch, CURLINFO_HTTP_CODE));
        }
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 500) {
            throw new Exception("Error code ".curl_getinfo($ch, CURLINFO_HTTP_CODE));
        }
        curl_close($ch);

        return $resp;
    }
}
