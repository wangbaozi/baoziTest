<?php
/**
 * Created by PhpStorm.
 * User: 55haitao
 * Date: 2018/9/6
 * Time: 下午6:06
 */

namespace App\Models\FilesReader;

use Illuminate\Support\Facades\Log;

class XmlFiles {

    private $xml_file;
    private $error;
    private $key = '';
    public  $spl_object = null;

    public function __construct($xml_file = '',$rela=0) {
        if($xml_file && $rela == 0) $xml_file = storage_path().'/'.$xml_file;

        if($xml_file && file_exists($xml_file)) {
            $this->xml_file = $xml_file;
        }

        $this->spl_object = simplexml_load_file($xml_file);
    }


    private function _file_valid($file = '') {
        $file = $file ? $file : $this->xml_file;
        if(!$file || !file_exists($file)) {
            Log::error('cronLog XmlFiles _file_valid  文件不存在 ');
            return false;
        }
        if(!is_readable($file)) {
            return false;
        }
        return true;
    }

    public function get_lines($key='product') {
        if(!$this->_open_file()) {
            return false;
        }
        $this->key = $key;
        return  $this->spl_object->$key->count();
    }

    private function _open_file() {
        if(!$this->_file_valid()) {
            $this->error = 'File invalid';
            return false;
        }
        if($this->spl_object == null) {
            $this->spl_object = simplexml_load_file($this->xml_file);
        }
        return true;
    }

    public function get_data($length = 0, $start = 0) {
        if(!$this->_open_file()) {
            return false;
        }
        $length = $length ? $length : $this->get_lines();
        $start = ($start < 0) ? 0 : $start;
        $key = $this->key;
        $data = array();
        for ($i = $start;$i < ($start+$length);$i++){
            $data[] = $this->spl_object->$key[$i];
        }
        return $data;
    }

    public function xml2array ( $xmlObject, $out = array () )
    {
        foreach ( (array) $xmlObject as $index => $node )
            $out[$index] = ( is_object ( $node ) ) ? $this->xml2array ( $node ) : $node;

        return $out;
    }


}
