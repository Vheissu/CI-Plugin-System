/*
Navicat MySQL Data Transfer

Source Server         : Localhost
Source Server Version : 50141
Source Host           : localhost:3306
Source Database       : plugins

Target Server Type    : MYSQL
Target Server Version : 50141
File Encoding         : 65001

Date: 2011-03-08 16:07:58
*/

SET FOREIGN_KEY_CHECKS=0;
-- ----------------------------
-- Table structure for `plugins`
-- ----------------------------
DROP TABLE IF EXISTS `plugins`;
CREATE TABLE `plugins` (
  `plugin_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plugin_name` varchar(255) NOT NULL,
  `plugin_system_name` varchar(255) NOT NULL,
  `plugin_description` text,
  `plugin_version` varchar(30) NOT NULL,
  `plugin_data` longtext,
  `plugin_status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`plugin_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

-- ----------------------------
-- Records of plugins
-- ----------------------------
INSERT INTO plugins VALUES ('1', 'Hello World', 'helloworld', 'A simple hello world plugin.', '1.0', null, '1');
