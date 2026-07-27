<?php
!defined('EMLOG_ROOT') && exit('access deined!');

function callback_init() {
    $db = Database::getInstance();
    $table = DB_PREFIX . 'lanyebadge';
    $sql = "CREATE TABLE IF NOT EXISTS `$table` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `left_text` varchar(50) NOT NULL DEFAULT '',
        `right_text` varchar(100) NOT NULL DEFAULT '',
        `left_bg` varchar(7) NOT NULL DEFAULT '#000000',
        `right_bg` varchar(7) NOT NULL DEFAULT '#00aff0',
        `left_color` varchar(7) NOT NULL DEFAULT '#ffffff',
        `right_color` varchar(7) NOT NULL DEFAULT '#ffffff',
        `font_size` tinyint(2) NOT NULL DEFAULT '11',
        `width` smallint(4) NOT NULL DEFAULT '120',
        `height` tinyint(2) NOT NULL DEFAULT '28',
        `left_width` smallint(4) NOT NULL DEFAULT '0',
        `font_family` varchar(200) NOT NULL DEFAULT 'Arial, sans-serif',
        `svg_content` text NOT NULL,
        `add_time` int(11) NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;";
    $db->query($sql);
}

function callback_rm() {
    //$db = Database::getInstance();
    //$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "lanyebadge`");
}