<?php

namespace reclamestal\PdoDbWrapper;

class Db extends \PDO {
    private $error;
    private $sql;
    private $bind;
    private $errorCallbackFunction;
    private $errorMsgFormat;

    public function __construct($dsn, $user = "", $passwd = "", $options = array()) {
        if (empty($options)) {
            $options = array(
                \PDO::ATTR_PERSISTENT => false,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            );
        }
        try {
            parent::__construct($dsn, $user, $passwd, $options);
        } catch (\PDOException $e) {
            trigger_error($e->getMessage());
            return false;
        }
    }

    private function debug() {
        if (!empty($this->errorCallbackFunction)) {
            $error = array("Error" => $this->error);
            if (!empty($this->sql))
                $error["SQL Statement"] = $this->sql;
            if (!empty($this->bind))
                $error["Bind Parameters"] = trim(print_r($this->bind, true));
            $backtrace = debug_backtrace();
            if (!empty($backtrace)) {
                foreach ($backtrace as $info) {
                    if (isset($info["file"]) && $info["file"] != __FILE__)
                        $error["Backtrace"] = $info["file"] . " at line " . $info["line"];
                }
            }
            $msg = "";
            if ($this->errorMsgFormat == "html") {
                if (!empty($error["Bind Parameters"])) {
                    $error["Bind Parameters"] = "<pre>" . $error["Bind Parameters"] . "</pre>";
                }
                $msg .= '<div><h3>SQL Error</h3><ul>';
                foreach ($error as $key => $val) {
                    $msg .= "<li>$key:$val</li>";
                }
                $msg .= '</ul></div>';
                $msg .='<small>Generated on '.date("Y-m-d H:i:s").' - '.$_SERVER['REQUEST_URI'].'</small>';
            } elseif ($this->errorMsgFormat == "text") {
                $msg .= "SQL Error\n" . str_repeat("-", 50);
                foreach ($error as $key => $val)
                    $msg .= "\n\n$key:\n$val";
            }
            $func = $this->errorCallbackFunction;
            $func($msg);
        }
    }

    public function delete($table, $where, $bind = "") {
        $sql = "DELETE FROM " . $table . " WHERE " . $where . ";";
        return $this->run($sql, $bind);
    }

    private function filter($table, $info) {
        $driver = $this->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver == 'sqlite') {
            $sql = "PRAGMA table_info('" . $table . "');";
            $key = "name";
        } elseif ($driver == 'mysql') {
            $sql = "DESCRIBE `" . $table . "`;";
            $key = "Field";
        } else {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = '" . $table . "';";
            $key = "column_name";
        }
        if (false !== ($list = $this->run($sql))) {
            $fields = array();
            foreach ($list as $record)
                $fields[] = $record[$key];
            return array_values(array_intersect($fields, array_keys($info)));
        }
        return array();
    }

    private function cleanup($bind) {
        if (!is_array($bind)) {
            if (!empty($bind))
                $bind = array($bind);
            else
                $bind = array();
        }
        foreach ($bind as $key => $val)
            $bind[$key] = stripslashes($val);
        return $bind;
    }

    public function insert($table, $info, $ignore = false) {
        $fields = $this->filter($table, $info);
        $sql = ($ignore ? "INSERT IGNORE" : "INSERT") . " INTO `" . $table . "` (`" . implode($fields, "`, `") . "`) VALUES (:" . implode($fields, ", :") . ");";
        $bind = array();
        foreach ($fields as $field)
            $bind[":$field"] = $info[$field];
        return $this->run($sql, $bind);
    }

    public function run($sql, $bind = "") {
        $this->sql = trim($sql);
        $this->bind = $this->cleanup($bind);
        $this->error = "";
        try {
            $pdostmt = $this->prepare($this->sql);
            if ($pdostmt->execute($this->bind) !== false) {
                if (preg_match("/^(" . implode("|", array("select", "describe", "pragma")) . ")\\s/i", $this->sql))
                    return $pdostmt->fetchAll(\PDO::FETCH_ASSOC);
                elseif (preg_match("/^(" . implode("|", array("delete", "insert", "update")) . ")\\s/i", $this->sql))
                    return $pdostmt->rowCount();
            }
        } catch (\PDOException $e) {
            $this->error = $e->getMessage();
            $this->debug();
            return false;
        }
    }

    public function select($tables, $where = "", $bind = "", $fields = "*", $extra = "") {
        $quotedTables = '`' . str_replace(',', '`,`', $tables) . '`';
        $sql = "SELECT " . $fields . " FROM $quotedTables";
        if (!empty($where))
            $sql .= " WHERE " . $where;
        $sql .= " $extra;";
        return $this->run($sql, $bind);
    }

    public function selectSingle($tables, $where = "", $bind = "", $fields = "*", $extra = "") {
        $extra.= " LIMIT 1;";
        return $this->select($tables,$where,$bind,$fields,$extra)[0];
    }

    public function setErrorCallbackFunction($errorCallbackFunction = "print_r", $errorMsgFormat = "html") {
        if (in_array(strtolower($errorCallbackFunction), array("echo", "print")))
            $errorCallbackFunction = "print_r";
        if (function_exists($errorCallbackFunction)) {
            $this->errorCallbackFunction = $errorCallbackFunction;
            if (!in_array(strtolower($errorMsgFormat), array("html", "text")))
                $errorMsgFormat = "html";
            $this->errorMsgFormat = $errorMsgFormat;
        }
    }

    public function update($table, $info, $where, $bind = "") {
        $fields = $this->filter($table, $info);
        $fieldSize = sizeof($fields);
        $sql = "UPDATE `" . $table . "` SET ";
        for ($f = 0; $f < $fieldSize; ++$f) {
            if ($f > 0)
                $sql .= ", ";
            $sql .= $fields[$f] . " = :update_" . $fields[$f];
        }
        $sql .= " WHERE " . $where . ";";
        $bind = $this->cleanup($bind);
        foreach ($fields as $field)
            $bind[":update_$field"] = $info[$field];
        return $this->run($sql, $bind);
    }
}