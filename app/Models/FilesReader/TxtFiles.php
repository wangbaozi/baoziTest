<?php
/**
 * Created by PhpStorm.
 * User: 55haitao
 * Date: 2018/9/6
 * Time: 下午6:06
 */

namespace App\Models\FilesReader;

use Illuminate\Support\Facades\Log;

class TxtFiles {
    private $csv_file;
    private $spl_object = null;
    private $error;
    private $separator = '';

    public function __construct($csv_file = '', $separator = ',',$rela=0) {
        if($csv_file && $rela == 0) $csv_file = storage_path().'/'.$csv_file;
        if($separator == '\t') $separator = "\t";
        if($csv_file && file_exists($csv_file)) {
            $this->csv_file = $csv_file;
        }
        $this->separator = $separator;
    }

    public function set_csv_file($csv_file) {
        if(!$csv_file || !file_exists($csv_file)) {
            Log::error('cronLog TxtFiles set_csv_file  File invalid 文件不存在 ');
            $this->error = 'File invalid';
            return false;
        }
        $this->csv_file = $csv_file;
        $this->spl_object = null;
    }

    public function get_csv_file() {
        return $this->csv_file;
    }

    private function _file_valid($file = '') {
        $file = $file ? $file : $this->csv_file;
        if(!$file || !file_exists($file)) {
            return false;
        }
        if(!is_readable($file)) {
            return false;
        }
        return true;
    }

    private function _open_file() {
        if(!$this->_file_valid()) {
            $this->error = 'File invalid';
            return false;
        }
        if($this->spl_object == null) {
            $this->spl_object = new \SplFileObject($this->csv_file, 'rb');
        }
        return true;
    }

    public function get_data($length = 0, $start = 0) {
        if(!$this->_open_file()) {
            return false;
        }
        $length = $length ? $length : $this->get_lines();
        $start = $start - 1;
        $start = ($start < 0) ? 0 : $start;
        $data = array();
        $this->spl_object->seek($start);
        while ($length-- && !$this->spl_object->eof()) {
            $data[] = $this->spl_object->fgetcsv($this->separator);
            $this->spl_object->next();
        }
        return $data;
    }

    public function get_lines() {
        if(!$this->_open_file()) {
            Log::error('cronLog TxtFiles _open_file false 文件打开失败 this->csv_file = '.$this->csv_file);
            return false;
        }
        $this->spl_object->seek(filesize($this->csv_file));
        return $this->spl_object->key();
    }

    public function get_error() {
        return $this->error;
    }

}
