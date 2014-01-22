<?php
namespace FelixOnline\Exporter;

/**
 * Database exporter
 */
class MySQLExporter
{
    protected $config;
    protected $db;
    private $required = array(
        'db_name',
        'db_user',
        'db_pass',
    );

    protected $types = array(
        'int' => '/int/',
        'string' => '/varchar|timestamp|text/',
    );

    function __construct($config)
    {
        $this->checkRequired($config);
        $this->config = $config + array(
            'file' => $config['db_name'] . '.sql',
        );

        if (file_exists($this->config['file'])) {
            unlink($this->config['file']);
        }

        $this->db = new \mysqli(
            array_key_exists('db_host', $this->config) ? $this->config['db_host'] : "localhost",
            $this->config['db_user'],
            $this->config['db_pass'],
            $this->config['db_name']
        );

        if ($this->db->connect_errno) {
            throw new Exception("Failed to connect to MySQL: (" . $this->db->connect_errno . ") " . $this->db->connect_error);
        }
    }

    private function checkRequired($config)
    {
        foreach($this->required as $required) {
            if (!array_key_exists($required, $config)) {
                throw new Exception('Key "' . $required . '" is required');
            }
        }
    }

    /**
     * Get tables
     */
    protected function getTables()
    {
        if (!array_key_exists('tables', $this->config)) {
            $tables = array();
            $result = $this->db->query('SHOW TABLES');
            while($row = $result->fetch_row()) {
                $tables[] = $row[0];
            }
        } else {
            $tables = is_array($this->config['tables']) ? $this->config['tables'] : explode(',', $this->config['tables']);
        }
        return $tables;
    }

    /**
     * Run export
     */
    public function run()
    {
        $tables = $this->getTables();

        $return = "SET FOREIGN_KEY_CHECKS = 0;\n\n\n";
        $this->save($return);

        // cycle through
        foreach($tables as $table) {
            $return = "";

            $return .= 'DROP TABLE IF EXISTS '.$table.';';
            $row2 = $this->db->query('SHOW CREATE TABLE '.$table)->fetch_row();
            $return .= "\n\n".$row2[1].";\n\n";
            $this->save($return);

            // process table
            $check = $this->processTable($table);

            // skip table
            if ($check == false) {
                continue;
            }

            $columns = array();
            $res = $this->db->query('SHOW COLUMNS FROM ' . $table);
            while($row = $res->fetch_assoc()) {
                $columns[$row['Field']] = $row;
            }

            $num_fields = $this->db->query('SELECT COUNT(*) FROM ' . $table)->fetch_row()[0];

            $page_size = 500;
            $pages = ceil($num_fields / $page_size);

            for ($i = 0; $i < $pages; $i++) {
                $sql = "SELECT * FROM " . $table . " LIMIT " . $i * $page_size . ", " . $page_size;
                $result = $this->db->query($sql);
                $num_results = $result->num_rows;

                $return = 'INSERT INTO '.$table.' VALUES ';
                $inserts = [];
                while($row = $result->fetch_assoc()) {
                    $row = $this->processRow($row, $table);

                    // skip row
                    if ($row == false) {
                        continue;
                    }

                    $insert = '(';
                    $x = 0;
                    foreach($row as $key => $field) {
                        $field = addslashes($field);
                        $field = preg_replace("/\n/", "\\n", $field);

                        $insert .= $this->getInsert($key, $field, $columns);

                        if ($x < (count($row) - 1)) {
                            $insert .= ',';
                        }
                        $x++;
                    }

                    $insert .= ")";
                    $inserts[] = $insert;
                }

                if (!empty($inserts)) {
                    $return .= implode(",\n", $inserts) . ";\n";
                    $this->save($return);
                }
            }

            $return .= "\n\n\n";
            $this->save($return);
        }

        $return = "SET FOREIGN_KEY_CHECKS = 1;";
        $this->save($return);
    }

    /**
     * Get insert for field
     */
    private function getInsert($key, $field, $columns) {
        $column = $columns[$key];

        if (!$field && $column['Null'] == 'YES') {
            return 'NULL';
        }

        // find type
        $type = "string"; // default
        foreach($this->types as $t => $test) {
            if (preg_match($test, $column['Type']) == 1) {
                $type = $t;
                break;
            }
        }
        switch ($type) {
            case "int":
                return $field;
                break;
            case "string":
                return '"'.$field.'"';
                break;
        }
    }

    /**
     * Save data and empty it
     */
    public function save(&$content)
    {
        file_put_contents($this->config['file'], $content, FILE_APPEND);
        $content = "";
    }

    /**
     * Process table
     */
    protected function processTable($table)
    {
        return $table;
    }

    /**
     * Process row
     */
    protected function processRow($row, $table)
    {
        return $row;
    }
}
