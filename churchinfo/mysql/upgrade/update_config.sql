SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

ALTER TABLE `config_cfg`
ADD COLUMN `cfg_order` INT NULL COMMENT '' AFTER `cfg_category`;

UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='0' WHERE `cfg_id`='1003';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='1' WHERE `cfg_id`='1004';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='2' WHERE `cfg_id`='1005';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='3' WHERE `cfg_id`='1006';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='4' WHERE `cfg_id`='1007';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='5' WHERE `cfg_id`='1008';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='7' WHERE `cfg_id`='1009';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='6' WHERE `cfg_id`='1010';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='8' WHERE `cfg_id`='65';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='9' WHERE `cfg_id`='45';
UPDATE `config_cfg` SET `cfg_category`='Step1', `cfg_order`='10' WHERE `cfg_id`='46';

UPDATE `config_cfg` SET `cfg_category`='Step2', `cfg_order`='0' WHERE `cfg_id`='12';
UPDATE `config_cfg` SET `cfg_category`='Step2', `cfg_order`='1' WHERE `cfg_id`='13';
UPDATE `config_cfg` SET `cfg_category`='Step2', `cfg_order`='3' WHERE `cfg_id`='14';
UPDATE `config_cfg` SET `cfg_category`='Step2', `cfg_order`='4' WHERE `cfg_id`='16';
UPDATE `config_cfg` SET `cfg_category`='Step2', `cfg_order`='5' WHERE `cfg_id`='9';
UPDATE `config_cfg` SET `cfg_category`='Step2', `cfg_order`='6' WHERE `cfg_id`='15';

UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='1' WHERE `cfg_id`='25';
UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='2' WHERE `cfg_id`='27';
UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='3' WHERE `cfg_id`='28';
UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='4' WHERE `cfg_id`='29';
UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='5' WHERE `cfg_id`='30';
UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='7' WHERE `cfg_id`='31';
UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='0' WHERE `cfg_id`='24';
UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='6' WHERE `cfg_id`='26';
UPDATE `config_cfg` SET `cfg_category`='Step3', `cfg_order`='8' WHERE `cfg_id`='2000';

UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='0' WHERE `cfg_id`='5';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='1' WHERE `cfg_id`='6';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='2' WHERE `cfg_id`='7';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='3' WHERE `cfg_id`='8';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='4' WHERE `cfg_id`='21';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='5' WHERE `cfg_id`='22';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='6' WHERE `cfg_id`='23';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='7' WHERE `cfg_id`='33';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='8' WHERE `cfg_id`='47';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='9' WHERE `cfg_id`='48';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='10' WHERE `cfg_id`='49';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='11' WHERE `cfg_id`='50';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='12' WHERE `cfg_id`='51';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='13' WHERE `cfg_id`='67';
UPDATE `config_cfg` SET `cfg_category`='Step4', `cfg_order`='15' WHERE `cfg_id`='19';

UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='0' WHERE `cfg_id`='2';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='1' WHERE `cfg_id`='35';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='2' WHERE `cfg_id`='999';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='3' WHERE `cfg_id`='39';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='4' WHERE `cfg_id`='4';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='5' WHERE `cfg_id`='3';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='6' WHERE `cfg_id`='41';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='7' WHERE `cfg_id`='36';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='8' WHERE `cfg_id`='37';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='9' WHERE `cfg_id`='38';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='10' WHERE `cfg_id`='34';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='12' WHERE `cfg_id`='64';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='11' WHERE `cfg_id`='11';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='13' WHERE `cfg_id`='1';
UPDATE `config_cfg` SET `cfg_category`='Step5', `cfg_order`='14' WHERE `cfg_id`='53';

UPDATE `config_cfg` SET `cfg_category`='Step6', `cfg_order`='0' WHERE `cfg_id`='44';
UPDATE `config_cfg` SET `cfg_category`='Step6', `cfg_order`='1' WHERE `cfg_id`='56';
UPDATE `config_cfg` SET `cfg_category`='Step6', `cfg_order`='2' WHERE `cfg_id`='66';
UPDATE `config_cfg` SET `cfg_category`='Step6', `cfg_order`='3' WHERE `cfg_id`='54';
UPDATE `config_cfg` SET `cfg_category`='Step6', `cfg_order`='4' WHERE `cfg_id`='55';
UPDATE `config_cfg` SET `cfg_category`='Step6', `cfg_order`='5' WHERE `cfg_id`='42';
UPDATE `config_cfg` SET `cfg_category`='Step6', `cfg_order`='6' WHERE `cfg_id`='43';

UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='0' WHERE `cfg_id`='1001';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='1' WHERE `cfg_id`='1002';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='2' WHERE `cfg_id`='1011';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='3' WHERE `cfg_id`='1012';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='4' WHERE `cfg_id`='1013';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='5' WHERE `cfg_id`='1014';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='6' WHERE `cfg_id`='1015';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='7' WHERE `cfg_id`='1016';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='8' WHERE `cfg_id`='1017';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='9' WHERE `cfg_id`='1018';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='10' WHERE `cfg_id`='1019';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='11' WHERE `cfg_id`='1020';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='12' WHERE `cfg_id`='1021';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='13' WHERE `cfg_id`='1022';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='14' WHERE `cfg_id`='1023';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='15' WHERE `cfg_id`='1024';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='16' WHERE `cfg_id`='1025';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='17' WHERE `cfg_id`='1026';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='18' WHERE `cfg_id`='1027';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='19' WHERE `cfg_id`='1028';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='20' WHERE `cfg_id`='1029';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='21' WHERE `cfg_id`='1030';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='22' WHERE `cfg_id`='1031';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='23' WHERE `cfg_id`='1032';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='24' WHERE `cfg_id`='1033';
UPDATE `config_cfg` SET `cfg_category`='Step7', `cfg_order`='25' WHERE `cfg_id`='1034';

UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='0' WHERE `cfg_id`='20';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='1' WHERE `cfg_id`='40';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='2' WHERE `cfg_id`='52';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='3' WHERE `cfg_id`='57';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='4' WHERE `cfg_id`='58';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='5' WHERE `cfg_id`='73';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='6' WHERE `cfg_id`='61';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='7' WHERE `cfg_id`='62';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='8' WHERE `cfg_id`='63';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='9' WHERE `cfg_id`='72';
UPDATE `config_cfg` SET `cfg_category`='Step8', `cfg_order`='10' WHERE `cfg_id`='10';

UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1011';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1012';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1013';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1015';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1017';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1018';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1019';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1020';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1021';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1022';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1023';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1024';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1026';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1027';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1028';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1029';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1031';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1032';
UPDATE `config_cfg` SET `cfg_type`='textarea' WHERE `cfg_id`='1033';
