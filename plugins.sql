/*
Navicat MySQL Data Transfer

Source Server         : Local DB
Source Server Version : 50141
Source Host           : localhost:3306
Source Database       : plugins

Target Server Type    : MYSQL
Target Server Version : 50141
File Encoding         : 65001

Date: 2011-03-30 08:06:36
*/

SET FOREIGN_KEY_CHECKS=0;
-- ----------------------------
-- Table structure for `plugins`
-- ----------------------------
DROP TABLE IF EXISTS `plugins`;
CREATE TABLE `plugins` (
  `plugin_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plugin_system_name` varchar(255) NOT NULL,
  `plugin_name` varchar(255) NOT NULL,
  `plugin_uri` varchar(120) DEFAULT NULL,
  `plugin_version` varchar(30) NOT NULL,
  `plugin_description` text,
  `plugin_author` varchar(120) DEFAULT NULL,
  `plugin_author_uri` varchar(120) DEFAULT NULL,
  `plugin_data` longtext,
  PRIMARY KEY (`plugin_id`),
  UNIQUE KEY `plugin_index` (`plugin_system_name`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=latin1;

-- ----------------------------
-- Records of plugins
-- ----------------------------
INSERT INTO plugins VALUES ('1', 'cimarkdown', 'CI Markdown', 'http://ilikekillnerds.com', '1.0', 'Parses text for Markdown', 'Dwayne Charrington', 'http://ilikekillnerds.com', null);
INSERT INTO plugins VALUES ('2', 'helloworld', 'Hello World', 'http://ilikekillnerds.com', '1.0', 'A simple hello world plugin', 'Dwayne Charrington', 'http://ilikekillnerds.com', null);
