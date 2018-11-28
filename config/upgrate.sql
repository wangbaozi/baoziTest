
-- add by chen 2018.09.04
--
-- 表的结构 `sl_link_acount`
--
CREATE TABLE `sl_link_account` (
  `account_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自动编号',
  `name` varchar(100) DEFAULT NULL COMMENT '账号标准名称，唯一',
  `username` varchar(100) DEFAULT NULL COMMENT '账号',
  `password` varchar(100) DEFAULT NULL COMMENT '密码',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  `creator` bigint(20) DEFAULT NULL COMMENT '创建人',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  `updator` bigint(20) DEFAULT NULL COMMENT '更新人',
  `status` tinyint(1) unsigned DEFAULT '1' COMMENT '状态，默认0未生效、1生效',
  `desc` text COMMENT '描述',
  PRIMARY KEY (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='链接账号表';


--
-- 表的结构 `sl_link_cron`
--

CREATE TABLE `sl_link_cron` (
  `cron_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自动编号',
  `supp_id` int(11) NOT NULL DEFAULT '0' COMMENT '商家id、冗余字段',
  `mcid` varchar(100) NOT NULL COMMENT '商家mcid',
  `link_type` varchar(50) NOT NULL COMMENT '链接类型',
  `fd_id` int(11) DEFAULT NULL COMMENT '映射模板id',
  `account_id` int(11) NOT NULL COMMENT '链接账号ID',
  `link_host` varchar(100) NOT NULL COMMENT '链接地址host',
  `file_path` varchar(255) NOT NULL COMMENT '文件地址',
  `file_ext` varchar(50) NOT NULL COMMENT '文件格式',
  `hour_run` int(11) NOT NULL DEFAULT '-1' COMMENT '设置执行时间',
  `hour_rate` int(11) NOT NULL DEFAULT '-1' COMMENT '设置每隔多少小时执行任务',
  `lastrun` int(11) NOT NULL COMMENT '最近执行时间',
  `nextrun` int(11) NOT NULL COMMENT '下次执行时间',
  `cron_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '定时脚本执行状态，0默认，1执行中，2结束',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `creator` bigint(20) DEFAULT NULL COMMENT '创建人',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  `updator` bigint(20) DEFAULT NULL COMMENT '更新人',
  `status` tinyint(1) DEFAULT '1' COMMENT '该记录是否失效状态：1生效、0失效',
  `cron_time` datetime DEFAULT NULL COMMENT '执行时间',
  PRIMARY KEY (`cron_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='文件cron设置表';



--
-- 表的结构 `sl_link_download`
--

CREATE TABLE `sl_link_download` (
  `down_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自动编号',
  `cron_id` int(11) NOT NULL COMMENT 'cron执行id',
  `down_url` varchar(255) NOT NULL COMMENT '下载路径',
  `file_url` varchar(255) NOT NULL COMMENT '保存路径',
  `file_size` decimal(12,2) DEFAULT NULL COMMENT '文件大小',
  `file_unit` varchar(10) DEFAULT NULL COMMENT '单位',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '下载状态，0默认，1下载中，2结束，3失败',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `creator` int(11) DEFAULT NULL COMMENT '创建人',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  `updator` int(11) DEFAULT NULL COMMENT '更新人',
  `read_status` tinyint(1) DEFAULT '0' COMMENT '读取状态',
  `read_time` datetime DEFAULT NULL COMMENT '解析时间',
  `down_time` datetime DEFAULT NULL COMMENT '下载时间',
  PRIMARY KEY (`down_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='文件下载表';



--
-- 表的结构 `sl_link_filedb`
--

CREATE TABLE `sl_link_filedb` (
  `fd_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自动编号',
  `fd_name` varchar(100) NOT NULL COMMENT '模板名称-唯一',
  `fd_type` tinyint(1) NOT NULL COMMENT '1表示xml、2表示有头部标题、3表示无头部标题、4表示json',
  `fd_prefix` varchar(10) NOT NULL COMMENT '前缀符号',
  `file_db` text NOT NULL COMMENT '文件字段映射数据库字段-json格式',
  `desc` text NOT NULL COMMENT '模板描述',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `creator` bigint(20) NOT NULL COMMENT '创建人',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  `updator` bigint(20) DEFAULT NULL COMMENT '更新人',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '模板状态是否有效 ：1有效、0无效',
  PRIMARY KEY (`fd_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='文件与数据库映射模板表';




-- add by baozi 2018.09.18
//品牌
//加is_valid字段
alter table sl_basic_brand add is_valid tinyint(1) DEFAULT '1'
COMMENT '1有效、0无效（针对新版datafeed后台品牌管理)' AFTER `status`;

//加索引
ALTER TABLE sl_basic_brand ADD INDEX index_basic_is_valid (`is_valid`);

ALTER TABLE `shoplooks`.`sl_basic_brand` CHANGE COLUMN `brand_relation` `brand_relation` int(11) NOT NULL DEFAULT 0 COMMENT '关联标准品牌';

UPDATE sl_basic_brand set is_valid=0 where 1=1 and brand_id in
(SELECT brand_id FROM
	(SELECT
		a.brand_id
			FROM
					`sl_basic_brand` as a
		where 1=1
		and exists(
				select 1 from sl_basic_brand as b where a.brand_name_en=b.brand_name_en and b.brand_id>a.brand_id
			)
	) n
);


-- --------------------------
//标准品牌


//加is_valid字段
alter table sl_brand_rela add is_valid tinyint(1) DEFAULT '1'
COMMENT '1有效、0无效（针对新版datafeed后台品牌管理)' AFTER `status`;

//加索引
ALTER TABLE sl_brand_rela ADD INDEX index_basic_is_valid (`is_valid`);


UPDATE sl_brand_rela set is_valid=0 where 1=1 and brand_id in
(SELECT brand_id FROM
	(SELECT
		a.brand_id
			FROM
					`sl_brand_rela` as a
		where 1=1
		and exists(
				select 1 from sl_brand_rela as b where a.brand_name_en=b.brand_name_en and b.brand_id>a.brand_id
			)
	) n
);

--已执行
-- ALTER TABLE sl_brand_rela modify COLUMN brand_region varchar(50) NOT NULL DEFAULT '' COMMENT '品牌所在国家';
--
-- ALTER TABLE sl_basic_supp modify COLUMN supp_region varchar(50) NOT NULL DEFAULT '' COMMENT '商家所在国家';


//品类
ALTER TABLE `shoplooks`.`sl_basic_category` CHANGE COLUMN `cat_relation` `cat_relation` int(11) NOT NULL DEFAULT 0 COMMENT '关联标准品类';

//加is_valid字段
alter table sl_basic_category add is_valid tinyint(1) DEFAULT '1'
COMMENT '1有效、0无效（针对新版datafeed后台品牌管理)' AFTER `status`;

//加索引
ALTER TABLE sl_basic_category ADD INDEX index_basic_cat_name_en (`cat_name_en`);
ALTER TABLE sl_basic_category ADD INDEX index_basic_is_valid (`is_valid`);


UPDATE sl_basic_category set is_valid=0 where 1=1 and cat_id in
(SELECT cat_id FROM
	(SELECT
		a.cat_id
			FROM
					`sl_basic_category` as a
		where 1=1
		and exists(
				select 1 from sl_basic_category as b where a.cat_name_en=b.cat_name_en and b.cat_id>a.cat_id
			)
	) n
);
-- add by baozi 2018.09.18






-- add by lee 2018.09.18
--
-- 表的结构 `sl_users`
--
CREATE TABLE `sl_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned DEFAULT '0' COMMENT '对应角色表的id',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `truename` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '真实姓名',
  `mobile` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '手机号',
  `telphone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '电话，固话',
  `identitycard` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '身份证号',
  `status` tinyint(1) DEFAULT '0' COMMENT '用户状态（0：正常，1，禁用，2冻结，3未激活）',
  `login_time` int(11) DEFAULT '0',
  `last_login_time` int(11) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 表的结构 `sl_admin_role`
--
CREATE TABLE `sl_admin_role` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '@pk',
  `title` varchar(32) NOT NULL DEFAULT '' COMMENT '组别名称名称',
  `description` varchar(128) NOT NULL DEFAULT '' COMMENT '描述',
  `is_deleted` enum('Y','N') NOT NULL DEFAULT 'N' COMMENT '逻辑删除标记符',
  `created_by` int(10) NOT NULL DEFAULT '0' COMMENT '创建者ID @fk account:id',
  `updated_by` int(10) NOT NULL DEFAULT '0' COMMENT '修改者ID @fk account:id',
  `created_time` datetime NOT NULL DEFAULT '2018-09-07 09:00:00',
  `updated_time` datetime NOT NULL DEFAULT '2018-09-07 09:00:00',
  PRIMARY KEY (`id`),
  KEY `is_deleted` (`is_deleted`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='管理员角色表';
--
-- 表的结构 `sl_role_permissions`
--
CREATE TABLE `sl_role_permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '@pk',
  `p_id` int(10) NOT NULL DEFAULT '0' COMMENT '权限ID @fk permissions:id',
  `roleid` int(10) NOT NULL DEFAULT '0' COMMENT '管理员组别ID @fk admin_roles:id',
  `created_by` int(10) NOT NULL DEFAULT '0' COMMENT '创建者ID @fk account:id',
  `updated_by` int(10) NOT NULL DEFAULT '0' COMMENT '修改者ID @fk account:id',
  `created_time` datetime NOT NULL DEFAULT '2018-09-07 09:00:00',
  `updated_time` datetime NOT NULL DEFAULT '2018-09-07 09:00:00',
  PRIMARY KEY (`id`),
  KEY `p_id` (`p_id`),
  KEY `roleid` (`roleid`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='管理员组别与权限关联表';
--
-- 表的结构 `sl_permissions_groups`
--
CREATE TABLE `sl_permissions_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '@pk',
  `title` varchar(32) NOT NULL DEFAULT '' COMMENT '权限名称',
  `is_deleted` enum('Y','N') NOT NULL DEFAULT 'N' COMMENT '逻辑删除标记符',
  `created_by` int(10) NOT NULL DEFAULT '0' COMMENT '创建者ID @fk account:id',
  `updated_by` int(10) NOT NULL DEFAULT '0' COMMENT '修改者ID @fk account:id',
  `created_time` datetime NOT NULL DEFAULT '2018-09-07 09:00:00',
  `updated_time` datetime NOT NULL DEFAULT '2018-09-07 09:00:00',
  PRIMARY KEY (`id`),
  KEY `is_deleted` (`is_deleted`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='权限组别表';

--
-- 表的结构 `sl_permissions`
--
CREATE TABLE `sl_permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '@pk',
  `pg_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '权限组别ID @fk permission_groups:id',
  `title` varchar(32) NOT NULL DEFAULT '' COMMENT '权限名称',
  `mod_name` varchar(32) NOT NULL DEFAULT '' COMMENT '控制器名',
  `op_name` varchar(32) NOT NULL DEFAULT '' COMMENT '操作方法名',
  `is_deleted` enum('Y','N') NOT NULL DEFAULT 'N' COMMENT '逻辑删除标记符',
  `created_by` int(10) NOT NULL DEFAULT '0' COMMENT '创建者ID @fk account:id',
  `updated_by` int(10) NOT NULL DEFAULT '0' COMMENT '修改者ID @fk account:id',
  `created_time` datetime NOT NULL DEFAULT '2018-09-07 09:00:00',
  `updated_time` datetime NOT NULL DEFAULT '2018-09-07 09:00:00',
  PRIMARY KEY (`id`),
  KEY `is_deleted` (`is_deleted`),
  KEY `pg_id` (`pg_id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='权限选项表';


--
-- add by lonn.chen 2018-09-25
-- 下载失败重试次数

ALTER TABLE sl_link_cron CHANGE COLUMN file_path file_path text NOT NULL COMMENT '文件地址';

ALTER TABLE `sl_link_download` CHANGE COLUMN `down_url` `down_url` text NOT NULL COMMENT '下载路径';

ALTER TABLE `sl_link_cron` ADD COLUMN `is_full` tinyint(1) DEFAULT 0 COMMENT '0:全量，1:增量' AFTER `status`;

ALTER TABLE `sl_link_download` ADD COLUMN `down_num` int(10) DEFAULT 0 COMMENT '下载次数' AFTER `down_time`;

ALTER TABLE `sl_link_download` CHANGE COLUMN `read_status` `read_status` tinyint(1) DEFAULT 0 COMMENT '0待解析，1解析中，2已解析，3解析失败';


--
-- add by lonn.chen 2018-10-22
-- 下载异常信息归类
ALTER TABLE `sl_link_download` ADD COLUMN `fail_no` int(10) DEFAULT 0 COMMENT '下载失败信息编号' AFTER `down_num`;


ALTER TABLE `sl_link_cron` ADD COLUMN `fail_no` int(11) DEFAULT 0 COMMENT '失败信息编号' AFTER `cron_time`;

--
-- add by lonn.chen 2018-10-24
-- 手动添加已经下载的文件待解析
ALTER TABLE `sl_link_download` ADD COLUMN `fd_id` int(11) DEFAULT 0 COMMENT '映射模板ID' AFTER `fail_no`;

ALTER TABLE `sl_link_download` ADD COLUMN `down_text` text COMMENT '文件解析必填参数' AFTER `fd_id`;



--
-- add by lonn.chen 2018-11-06
-- 商品图片更新到CDN保存

ALTER TABLE `sl_product` ADD COLUMN `product_img_id` int(11) DEFAULT 0 COMMENT '商品CDN图片ID' AFTER `product_skuid`;

ALTER TABLE `sl_product` ADD COLUMN `status_img` tinyint(2) DEFAULT 0 COMMENT '状态有[0,1,2,3]0: 初始状态，需要上传的数据;1: 已保存到CDN;2: 上传中，中间状态;3: url无法保存或保存失败' AFTER `product_img_id`;

CREATE TABLE `sl_product_img` (
  `product_img_id` int(10) NOT NULL AUTO_INCREMENT COMMENT '自动编号',
  `product_id` int(10) DEFAULT NULL COMMENT '商品ID',
  `product_img` varchar(500) DEFAULT NULL COMMENT '图片CDN地址',
  `product_imageurl` varchar(500) DEFAULT NULL COMMENT '原图片URL',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  `status` tinyint(2) DEFAULT '0' COMMENT '0默认新增,1有效,2失败,3上传中,4无效地址,5删除',
  PRIMARY KEY (`product_img_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='商品CDN图片保存表'



CREATE TABLE `sl_link_host` (
  `host_id` int(10) NOT NULL AUTO_INCREMENT COMMENT '自动编号',
  `host_name` varchar(200) DEFAULT NULL COMMENT '完整域名',
  `status` tinyint(2) DEFAULT '1' COMMENT '1有效，0失效',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  `create_time` datetime DEFAULT NULL,
  `creator` varchar(20) DEFAULT '0' COMMENT '创建者',
  `updator` varchar(20) DEFAULT '0' COMMENT '更新者',
  PRIMARY KEY (`host_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='需要CDN保存的域名'