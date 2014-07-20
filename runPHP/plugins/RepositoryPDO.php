<?php

namespace runPHP\plugins;
use runPHP\IRepository, runPHP\ErrorException, runPHP\Logger;
use PDO, PDOException;

/**
 * This class implements the repository interface with PDO technology.
 *
 * @author Miguel Angel Garcia
 *
 * Copyright 2014 TAOSMI Technology
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
class RepositoryPDO implements IRepository {

    /**
     * The PDO object.
     * @var string
     */
    private $pdo;

    /**
     * The DB table.
     * @var string
     */
    private $table;

    /**
     * The fields to retrieve when querying.
     * @var string
     */
    private $fields;

    /**
     * The full class name to cast from the DB results.
     * @var string
     */
    private $objectName;


    /**
     * Initiate the repository connection. The connection string must be
     * formatted as:
     *      tech:host=hostname;dbname=dbname,user,password
     * The available technologies are the same as PHP PDO drivers.
     * This is an example of MySQL connection string:
     *      mysql:host=db18.1and1.es;dbname=db355827412,guest,12345
     *
     * @param string  $connection  A connection string.
     * @throws                     ErrorException if the connection fails.
     */
    public function __construct ($connection) {
        // Get the DB resource.
        try {
            list($resource, $user, $pwd) = explode(',', $connection);
            $start = microtime(true);
            $this->pdo = new PDO($resource, $user, $pwd);
            $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
            Logger::repo('Connecting to the DDBB ('.$resource.')', $start);
            $this->query('SET NAMES utf8');
        } catch (PDOException $e) {
            throw new ErrorException(__('The connection to the persistence has failed.', 'system'), array(
                'code' => 'RPDO-01',
                'error' => $e->getMessage(),
                'resource' => $resource,
                'helpLink' => 'http://runphp.taosmi.es/faq/rpdo01'
            ));
        }
    }


    public function add ($item) {
        // Get the object keys.
        $objData = get_object_vars($item);
        $keys = array_keys($objData);
        // Query time.
        $sql = 'INSERT INTO '.$this->table.' ('.implode(',', $keys).') VALUES (:'.implode(',:', $keys).')';
        $this->query($sql, $objData);
        return $this->pdo->lastInsertId();
    }

    public function find ($options = null) {
        // Get the fields to retrieve.
        $fields = $this->fields ? $this->fields : '*';
        // Query time.
        $sql = 'SELECT '.$fields.' FROM '.$this->table.$this->parseOptions($options);
        $statement = $this->query($sql);
        // Fetch the result.
        if ($this->objectName) {
            $statement->setFetchMode(PDO::FETCH_CLASS, $this->objectName);
        } else {
            $statement->setFetchMode(PDO::FETCH_ASSOC);
        }
        return $statement->fetchAll();
    }

    public function from ($resource) {
        $this->table = $resource;
    }

    public function modify ($item, $options = null) {
        // Update query.
        $sql = 'UPDATE '.$this->table.' SET '.$this->toQuery($item).' '.$this->parseOptions($options);
        $statement = $this->query($sql);
        // Return the number of items updated.
        return $statement->rowCount();
    }

    public function query ($query, $data = null) {
        try {
            // Process the query.
            if ($data) {
                $qStart = microtime(true);
                $statement = $this->pdo->prepare($query);
                $statement->execute($data);
                Logger::repo($query, $qStart);
            } else {
                $qStart = microtime(true);
                $statement = $this->pdo->query($query);
                Logger::repo($query, $qStart);
            }
            return $statement;
        } catch (PDOException $e) {
            throw new ErrorException(__('The query to the persistence has failed.', 'system'), array(
                'code' => 'RPDO-02',
                'error' => $e->getMessage(),
                'query' => $query,
                'helpLink' => 'http://runphp.taosmi.es/faq/rpdo02'
            ));
        }
    }

    public function remove ($options = null) {
        // Delete query.
        $sql = 'DELETE FROM '.$this->table.$this->parseOptions($options);
        $statement = $this->query($sql);
        // Return the number of items deleted.
        return $statement->rowCount();
    }

    public function select ($fields) {
        $this->fields = $fields;
    }

    public function to ($objectName) {
        $this->objectName = $objectName;
    }

    public function beginTransaction () {
        $this->pdo->beginTransaction();
    }

    public function commit () {
        $this->pdo->commit();
    }

    public function rollback () {
        $this->pdo->rollBack();
    }

    public function backup ($fileName = null) {
        // Check if the the table is set.
        if (!$this->table) {
            throw new ErrorException(__('The repository has not a source table.', 'system'), array(
                'code' => 'RPDO-03',
                'helpLink' => 'http://runphp.taosmi.es/faq/rpdo03'
            ));
        }
        // Check the file name.
        if (!$fileName) {
            $fileName = 'repo_'.$this->table;
        }
        $script = '-- Table creation'."\r\n";
        // Get the table creation script.
        $script.= "\n".'DROP TABLE IF EXISTS '.$this->table.';'."\n";
        $stmtTable = $this->pdo->query('SHOW CREATE TABLE '.$this->table.';');
        $stmtTable ->setFetchMode(PDO::FETCH_ASSOC);
        $script.= $stmtTable->fetchColumn(1).";\n";
        // Get the data script.
        $script.= '-- Data'."\r\n";
        $stmtData = $this->pdo->query('SELECT * FROM '.$this->table.';');
        $stmtData->setFetchMode(PDO::FETCH_ASSOC);
        while ($item = $stmtData->fetch()) {
            $script.= 'INSERT INTO '.$this->table.' VALUES(';
            // Clean the parameters.
            foreach ($item as &$value) {
                $value = addslashes(str_replace("\r\n", "\\r\\n", $value));
            }
            $script.= '"'.implode('","', $item).'"';
            $script.= ');'."\n";
        }
        // Write the file.
        $file = fopen(RESOURCES.'/'.$fileName.'.'.date('Ymd.His').'.sql', 'w+');
        fwrite($file, $script);
        fclose($file);
    }


    /**
     * Transform the options array into a string that can be delivered to the DB.
     * If no options, return an empty string.
     *
     * @param  array   $options  The options (optional).
     * @return string            The options condition.
     */
    private function parseOptions ($options = null) {
        $sql = '';
        if (!$options) {
            return $sql;
        }
        if (isset($options['condition'])) {
            $sql.= ' WHERE '.$options['condition'];
        }
        if (isset($options['groupBy'])) {
            $sql.= ' GROUP BY '.$options['groupBy'];
        }
        if (isset($options['orderBy'])) {
            $sql.= ' ORDER BY '.$options['orderBy'];
        }
        if (isset($options['limit'])) {
            $limit = $options['limit'];
            $offset = isset($options['offset']) ? $options['offset'] : '0';
            $sql.= ' LIMIT '.$offset.','.$limit;
        }
        return $sql;
    }

    /**
     * Transform an associative array into a separated by comma key - value
     * pairs string. By default, the pair of values will be joined by comma.
     * Example:
     *      Source: array(key => value, key => value);
     *      Result: key='value',key='value'
     *
     * @param  object  $item  A item with public data.
     * @param  string  $join  The character in between pair of values (optional).
     * @return string         A separated by comma key - value pairs string.
     */
    private function toQuery ($item, $join = ',') {
        $query = '';
        foreach(get_object_vars($item) as $key => $value) {
            $query .= $key.'=\''.$value.'\''.$join;
        }
        return rtrim($query, $join);
    }
}