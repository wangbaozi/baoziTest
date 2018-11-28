<?php
return [
    'ossServer'         => env('ALIOSS_SERVER', 'oss-cn-hangzhou.aliyuncs.com'),       // 外网
    'ossServerInternal' => env('ALIOSS_SERVERINTERNAL', null),      // 内网
    'AccessKeyId'       => env('ALIOSS_KEYID', '2o5AluqRcHR9Gdzj'),                     // keyId
    'AccessKeySecret'   => env('ALIOSS_KEYSECRET', 'Qur4eHZJIXWnb7wa65wxV8435ELr1S'),   // secret
    'BucketName'        => env('ALIOSS_BUCKETNAME', 'linkhaitao'),                       // bucket
    'PREFIXURLCDNIMG'   => env('PREFIX_URL_CDN_IMG', 'https://cdn.linkhaitao.com/')      // 图片地址前缀
];