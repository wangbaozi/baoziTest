<?php
/**
 * Created by PhpStorm.
 * User: 55haitao
 * Date: 2018/9/6
 * Time: 下午6:06
 */

namespace App\Models\FilesReader;
use Illuminate\Support\Facades\Log;

class RarFiles {

    //允许下载的文件类型
    public $allowExt = ['rar','gz','zip','txt','txt.gz','csv','json','xml','xml.gz','csv.zip','csv.gz'];
    //允许下载的文件类型
    public $readExt = ['txt','csv','json','xml'];
    //需要解压的问文件类型
    public $unzipExt = ['rar','gz','zip','txt.gz','xml.gz','csv.zip','csv.gz'];
    //文件夹下所有文件
    public $dirFiles = [];


    /**
     * 解压文件，使用命令模式解压文件
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    public function unzipFiles($fileName,$saveDir,$newName,$ext='txt.gz'){
        $dirZip = storage_path().'/'.$saveDir.'/';
        $fullName = $dirZip.$newName;
        if (!file_exists($dirZip) && !mkdir($dirZip, 0777, true)) {
            Log::error('cronLog unzipFiles '.$fileName.' 文件夹创建失败 ');
            return false;
        }
        $fileName = storage_path().'/'.$fileName;

        if (!file_exists($fileName)) {
            Log::error('cronLog unzipFiles '.$fileName.' 文件不存在 ');
            return false;
        }

        //使用命令模式解压文件
        $com = "tar -zxvf $fileName -C $dirZip";
        if($ext == 'txt.gz' || $ext == 'xml.gz'){
            $com = "gunzip -c $fileName > $fullName ";
        }elseif ($ext == 'zip'){
            $com = "unzip -d $dirZip $fileName";
        }

        Log::info('cronLog '.$com.' 解压文件 ');
        $handle = popen($com, 'r');
        pclose($handle);

        return true;
    }

    /**
     * ftp保存文件
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    public function ftpDown($fileUrl,$fileInfo,$ftpUser='',$ftpPwd=''){
        $fileSavePath = storage_path().'/'.$fileInfo['newFilename'];
        $tmpFile = "/tmp/".$fileInfo['newName'].".".$fileInfo['fileExt'];
        $curlobj = curl_init();//初始化
        //传入ftp的目标文件，如'ftp://192.168.3.1/test/1.jpg'
        curl_setopt($curlobj,CURLOPT_URL,$fileUrl);
        curl_setopt($curlobj,CURLOPT_HEADER,0);//不输出header
        curl_setopt($curlobj,CURLOPT_RETURNTRANSFER,0);
        //time out after 300s
        curl_setopt($curlobj,CURLOPT_TIMEOUT,3600);//超时时间，1小时

        if(strpos($fileUrl ,'https://') !== false){
            curl_setopt($curlobj, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curlobj, CURLOPT_SSL_VERIFYHOST, false);
        }

        //通过这个函数设置ftp的用户名和密码,没设置就不需要!
        if($ftpUser && $ftpPwd)
            curl_setopt($curlobj,CURLOPT_USERPWD,$ftpUser.':'.$ftpPwd);

        $outfile = fopen($tmpFile,'w+'); //保存到本地文件的文件名
        curl_setopt($curlobj,CURLOPT_FILE,$outfile);

        $rtn = curl_exec($curlobj);
        if(curl_errno($curlobj)){
            Log::error('cronLog ftpDown curl_errno = '.curl_errno($curlobj).', fileUrl = '.$fileUrl.' 文件保存失败 ');
            if(file_exists($fileSavePath)) unlink($fileSavePath);//如果下载失败，但是本地open了这个文件，所以要删除
            return false;
        }
        fclose($outfile);
        curl_close($curlobj);

        if($rtn != 1){
            Log::error('cronLog ftpDown fileUrl = '.$fileUrl.' 文件保存失败 ');
            if(file_exists($fileSavePath)) unlink($fileSavePath);//如果下载失败，但是本地open了这个文件，所以要删除
            return false;
        }

        //转移文件
        $com = "mv $tmpFile $fileSavePath ";
        $handle = popen($com, 'r');
        pclose($handle);
        Log::info('cronLog ftpDown fileUrl = '.$fileUrl.', '.$com );

        return true;
    }

    /**
     * http保存文件
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    public function httpDown($fileUrl,$newFilename){
        //设置脚本的最大执行时间，设置为0则无时间限制
        $newFilename = storage_path()."/".$newFilename;
        set_time_limit(0);
        $fr = fopen($fileUrl, 'r');
        $fw = fopen($newFilename, 'w');
        while (!feof($fr)) {
            $output = fread($fr, 1024*1024);
            fwrite($fw, $output);
        }
        fclose($fr);
        fclose($fw);
        Log::info('cronLog httpDown fileUrl = '.$fileUrl.', newFilename = '.$newFilename );
        return true;
    }

    /**
     * 遍历文件夹下的文件
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    public function getDirFiles($dir){
        if(!is_dir($dir)) return false;

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $tempFile = $dir . '/' . $file;
                if (is_dir($tempFile)) {
                    $this->dirFiles = $this->getDirFiles($tempFile);
                    $this->dirFiles = array_merge($this->dirFiles);
                } else {
                    //获取文件的扩展名
                    $fileExt = strtolower(pathinfo($tempFile,PATHINFO_EXTENSION));
                    //检测文件类型是否允许直接读取
                    if(in_array($fileExt,$this->readExt)) {
                        $this->dirFiles[] = $tempFile;
                    }else{
                        //Log::info('cronLog getDirFiles = '.$tempFile.', fileExt = '.$fileExt );
                    }
                }
            }
        }
        return $this->dirFiles;
    }

}
