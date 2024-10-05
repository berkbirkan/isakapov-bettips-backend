<?php

class Db {
    // Database credentials for docker container
    //mysql://mysql:LKVlTIPgHtznVeQ5Z111SmdflaJx42PgNAyK0mgH7AnzaRmCCXdrcV13lbUYMe6P@z0s40w8co0cwgcgkss8o4ckk:3306/default
    //mysql://mysql:LKVlTIPgHtznVeQ5Z111SmdflaJx42PgNAyK0mgH7AnzaRmCCXdrcV13lbUYMe6P@z0s40w8co0cwgcgkss8o4ckk:3306/default
    private $dbhost = "z0s40w8co0cwgcgkss8o4ckk:3306";
    private $dbname = "default";
    private $dbuser = "mysql";
    private $dbpass = "LKVlTIPgHtznVeQ5Z111SmdflaJx42PgNAyK0mgH7AnzaRmCCXdrcV13lbUYMe6P";

    public function connect($dbname = null){
        $mysql_connection = "mysql:host=$this->dbhost;dbname=" . ($dbname ?: $this->dbname) . ";charset=utf8";
        $connection = new PDO($mysql_connection, $this->dbuser, $this->dbpass);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $connection;
    }
}

