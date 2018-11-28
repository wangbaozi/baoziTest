<?php
namespace App\Services;

/**
 * Description of Util
 *
 */
class Util {

    public static function request($url, $params = array(), $json = false, $multipart = false) {

        $ch = curl_init();

        if (!empty($params['timeout'])) {
            curl_setopt($ch, CURLOPT_TIMEOUT, intval($params['timeout']));
        }

        if (stripos($url, "https://") !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        isset($params['proxy_urlport']) && curl_setopt($ch, CURLOPT_PROXY, trim($params['proxy_urlport']));
        isset($params['proxy_type']) && curl_setopt($ch, CURLOPT_PROXYTYPE, trim($params['proxy_type']));
        isset($params['proxy_userpwd']) && curl_setopt($ch, CURLOPT_PROXYUSERPWD, trim($params['proxy_userpwd']));
        isset($params['proxy_tunnel']) && curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, boolval($params['proxy_tunnel']));

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $headers = array(
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, sdch, br',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36',
        );
        if (!empty($params['header']) && is_array($params['header'])) {
            $headers = array_merge($headers, $params['header']);
        }

        if ($json) {
            $data = empty($params['data']) ? '' : $params['data'];
            $params['data'] = empty($data) ? '' : json_encode($data, JSON_UNESCAPED_UNICODE);
            $headers['Content-Type'] = 'application/json';
            $headers['Content-Length'] = strlen($params['data']);
        }

        foreach ($headers as $key => $value) {
            if ($value !== null) {
                $header[] = $key . ": " . $value;
            }
        }
        !empty($header) && curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, !empty($params['return_header']) ? 1 : 0);
        curl_setopt($ch, CURLOPT_ENCODING, isset($params['compress']) ? $params['compress'] : 'gzip,deflate,sdch');
        if (!empty($params['data'])) {
            if (is_array($params['data'])) {
                if (!$multipart) {
                    curl_setopt($ch, CURLOPT_POST, 1);
                    $params['data'] = http_build_query($params['data']);
                }
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params['data']);
        }
        isset($params['cookie_path']) && curl_setopt($ch, CURLOPT_COOKIEJAR, $params['cookie_path']);
        isset($params['cookie_path']) && is_file($params['cookie_path']) && curl_setopt($ch, CURLOPT_COOKIEFILE, $params['cookie_path']);
        $result = curl_exec($ch);
        if (!empty($params['get_only_info'])) {
            $res['data'] = $result;
            $res['http'] = curl_getinfo($ch);
        } else{
            $res = $result;
        }
        curl_close($ch);

        return $res;
    }

}
