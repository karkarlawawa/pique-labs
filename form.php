<?php

define('DB_USER', 'piquelab_root');
define('DB_PASSWORD', 'berC-aP-ev-arr-u-Qui');
define('DB_HOST', 'localhost');
define('DB_NAME', 'piquelab_contact');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

/*FUNCTIONS*/

/* Open the connection to database */
function f_sqlConnect($host, $user, $pass, $db) {
    $link = mysql_connect($host, $user, $pass);

    if (!$link) {
        die('Could not connect: ' . mysql_error());
    }

    $db_selected = mysql_select_db($db, $link);

    if (!$db_selected) {
        die('Can\'t use ' . $db . ': ' . mysql_error());
    }
}

/* Clean &_POST array to prevent against SQL injection attacks */
function f_clean($array) {
    return array_map('mysql_real_escape_string', $array);
}

/*Check to see if table exists, otherwise create one*/
function f_tableExists($tablename, $database = false) {

    if(!$database) {
        $res = mysql_query("SELECT DATABASE()");
        $database = mysql_result($res, 0);
    }

    $res = mysql_query("
        SELECT COUNT(*) AS count
        FROM information_schema.tables
        WHERE table_schema = '$database'
        AND table_name = '$tablename'
    ");

    return mysql_result($res, 0) == 1;

}

/*Check to see if field exists, otherwise create one*/
function f_fieldExists($table, $column, $column_attr = "VARCHAR( 255 ) NULL" ) {
    $exists = false;
    $columns = mysql_query("show columns from $table");
    while($c = mysql_fetch_assoc($columns)){
        if($c['Field'] == $column){
            $exists = true;
            break;
        }
    }
    if(!$exists){
        mysql_query("ALTER TABLE `$table` ADD `$column`  $column_attr");
    }
}

/*Checks the validity of the retrieved IP address.*/
function f_validIP($ip) {

    if (!empty($ip) && ip2long($ip)!=-1) {
        $reserved_ips = array (
            array('0.0.0.0','2.255.255.255'),
            array('10.0.0.0','10.255.255.255'),
            array('127.0.0.0','127.255.255.255'),
            array('169.254.0.0','169.254.255.255'),
            array('172.16.0.0','172.31.255.255'),
            array('192.0.2.0','192.0.2.255'),
            array('192.168.0.0','192.168.255.255'),
            array('255.255.255.0','255.255.255.255')
        );

        foreach ($reserved_ips as $r) {
            $min = ip2long($r[0]);
            $max = ip2long($r[1]);
            if ((ip2long($ip) >= $min) && (ip2long($ip) <= $max)) return false;
        }

        return true;
    } else {
        return false;
    }
 }

/*Gets the IP address of the current user for storage in the database.*/
function f_getIP() {
    if (f_validIP($_SERVER["HTTP_CLIENT_IP"])) {
        return $_SERVER["HTTP_CLIENT_IP"];
    }

    foreach (explode(",",$_SERVER["HTTP_X_FORWARDED_FOR"]) as $ip) {
        if (f_validIP(trim($ip))) {
            return $ip;
        }
    }

    if (f_validIP($_SERVER["HTTP_X_FORWARDED"])) {
        return $_SERVER["HTTP_X_FORWARDED"];
    } elseif (f_validIP($_SERVER["HTTP_FORWARDED_FOR"])) {
        return $_SERVER["HTTP_FORWARDED_FOR"];
    } elseif (f_validIP($_SERVER["HTTP_FORWARDED"])) {
        return $_SERVER["HTTP_FORWARDED"];
    } elseif (f_validIP($_SERVER["HTTP_X_FORWARDED"])) {
        return $_SERVER["HTTP_X_FORWARDED"];
    } else {
        return $_SERVER["REMOTE_ADDR"];
    }
 }

/*PROCESS*/

// $domain = $_SERVER['HTTP_HOST'];
// $uri = parse_url($_SERVER['HTTP_REFERER']);
// $r_domain = $uri['host'];

// if ( $domain == $r_domain) {

    $link = f_sqlConnect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    $_POST = f_clean($_POST);

    /*Main variables*/
    $table = $_POST['formID'];
    $keys = implode(", ", (array_keys($_POST)));
    $values = implode("', '", (array_values($_POST)));

    /*Redirect variables*/
    $redirect = $_POST['redirect_to'];
    $referred = $_SERVER['HTTP_REFERER'];
    $query = parse_url($referred, PHP_URL_QUERY);
    $referred = str_replace(array('?', $query), '', $referred);

    /*Extra fields collected upon form submission*/
    $x_fields = 'timestamp, ip';
    $x_values = time() . "', '" . f_getIP();

    if ( !f_tableExists($table, DB_NAME) ) {
        $sql = "CREATE TABLE $table (
            ID int NOT NULL AUTO_INCREMENT,
            PRIMARY KEY(ID),
            timestamp int NOT NULL,
            ip int NOT NULL
        )";

        $result = mysql_query($sql);

        if (!$result) {
            die('Invalid query: ' . mysql_error());
        }

    }

    foreach ($_POST as $key => $value) {
        $column = mysql_real_escape_string($key);
        $alter = f_fieldExists($table, $column, $column_attr = "VARCHAR( 255 ) NULL" );

        if (!alter) {
            echo 'Unable to add column: ' . $column;
        }
    }

    $sql="INSERT INTO $table ($keys, $x_fields) VALUES ('$values', '$x_values')";

    if (!mysql_query($sql)) {
        die('Error: ' . mysql_error());
    }

    mysql_close();

    if ( !empty ( $redirect ) ) {
        header("Location: $redirect?msg=1");
    } else {
        header("Location: $referred?msg=1");
    }
// } else {
//     die('You are not allowed to submit data to this form');
// }
?>