<?php

/**
 * LMLPHP Framework
 * Copyright (c) 2014 http://lmlphp.com All rights reserved.
 * Licensed ( http://mit-license.org/ )
 * Author: leiminglin <leiminglin@126.com>
 *
 * A fully object-oriented PHP framework.
 * Keep it light, magnificent, lovely.
 *
 */

class MysqlPdo
{
    private static $config;
    private static $instance;
    private $db;

    private function __construct() {
        $dsn = 'mysql:host='.self::$config['hostname'].';dbname='.self::$config['database'];
        $username = self::$config['username'];
        $password = self::$config['password'];
        $options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        );
        $this->db = new PDO($dsn, $username, $password, $options);
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) != 'mysql') {
            die("MySQL support not be enabled");
        }
    }

    public static function getInstance($config){
        self::$config = $config;
        if(isset(self::$instance)){
            return self::$instance;
        }
        return new self();
    }

    public function select($sql){
        $stmt = $this->db->prepare($sql,
            array(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true));
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert($sql, $data){
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

}
