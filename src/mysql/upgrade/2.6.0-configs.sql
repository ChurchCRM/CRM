/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
/**
 * Author:  Charles
 * Created: Jan 28, 2017
 */

DELETE FROM config_cfg WHERE cfg_value = cfg_default;

ALTER TABLE config_cfg DROP COLUMN cfg_type;
ALTER TABLE config_cfg DROP COLUMN cfg_default;
ALTER TABLE config_cfg DROP COLUMN cfg_tooltip;
ALTER TABLE config_cfg DROP COLUMN cfg_section;
ALTER TABLE config_cfg DROP COLUMN cfg_category;
ALTER TABLE config_cfg DROP COLUMN cfg_order;
ALTER TABLE config_cfg DROP COLUMN cfg_data;