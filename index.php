<?php
class QueryBuilder
{
    private $fields = [];
    private $conditions = [];
    private $order = [];
    private $from = [];
    private $innerJoin = [];
    private $leftJoin = [];
    private $limit;
    private $on = [];
    private $query;
    private $columns = [];
    private $values = [];
    protected $pdo;
    protected static $instance = null;

    public function __construct()
    {

    }

    public static function instance()
    {
        if (self::$instance === null)
        {
            $opt  = array(
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => FALSE,
            );
            $dsn = 'mysql:host='.'DB_HOST'.';dbname='.'DB_NAME'.';charset='.'DB_CHAR';
            self::$instance = new \PDO($dsn, 'DB_USER', 'DB_PASS', $opt);
        }
        return self::$instance;
    }

    public static function __callStatic($method, $args)
    {
        return call_user_func_array(array(self::instance(), $method), $args);
    }

    public function select(string ...$select) {
        foreach ($select as $arg) {
            $this->fields[] = $arg;
        }
        return $this;
    }

    public static function table(string $table, ?string $alias = null){
        $obj = new self;
        $obj->from[] = $alias === null ? $table : "$table $alias";
        return $obj;
    }

    public function where(string ...$where){
        foreach ($where as $arg) {
            $this->conditions[] = $arg;
        }
        return $this;
    }

    public function limit(int $limit){
        $this->limit = $limit;
        return $this;
    }

    public function orderBy(string ...$order){
        foreach ($order as $arg) {
            $this->order[] = $arg;
        }
        return $this;
    }

    public function innerJoin(string ...$join){
        $this->innerJoin = [];
        foreach ($join as $arg) {
            $this->innerJoin[] = $arg;
        }
        return $this;
    }

    public function leftJoin(string ...$join){
        $this->leftJoin = [];
        foreach ($join as $arg) {
            $this->leftJoin[] = $arg;
        }
        return $this;
    }

    public function on(string ...$col){
        $this->on = [];
        foreach ($col as $arg) {
            $this->on[] = $arg;
        }
        return $this;
    }

    public function fetch() {
        $this->query .= 'SELECT ';
        $this->query .= (count($this->fields)>=1 && !empty($this->fields[0])) ? implode(', ', $this->fields) : "*";
        $this->query .= ' FROM ' . implode(', ', $this->from);
        $this->query .= ($this->leftJoin === [] ? '' : ' LEFT JOIN '. implode(' ON ', $this->leftJoin));
        $this->query .= ($this->innerJoin === [] ? '' : ' INNER JOIN '. implode(' ON ', $this->innerJoin));
        $this->query .= ($this->conditions === [] ? '' : ' WHERE ' . implode(' ', $this->conditions));
        $this->query .= ($this->order === [] ? '' : ' ORDER BY ' . implode(', ', $this->order));
        $this->query .= ($this->limit === null ? '' : ' LIMIT ' . $this->limit);

        // $pdoStatement = self::instance()->prepare($this->query);
        // $pdoStatement->execute();
        // $data = $pdoStatement->fetch(\PDO::FETCH_ASSOC);
        // return $data;
        return $this->query;
    }

    public function fetchAll() {
        $this->query .= 'SELECT ';
        $this->query .= (count($this->fields)>=1 && !empty($this->fields[0])) ? implode(', ', $this->fields) : "*";
        $this->query .= ' FROM ' . implode(', ', $this->from);
        $this->query .= ($this->leftJoin === [] ? '' : ' LEFT JOIN '. implode(' ON ', $this->leftJoin));
        $this->query .= ($this->innerJoin === [] ? '' : ' INNER JOIN '. implode(' ON ', $this->innerJoin));
        $this->query .= ($this->conditions === [] ? '' : ' WHERE ' . implode(' ', $this->conditions));
        $this->query .= ($this->order === [] ? '' : ' ORDER BY ' . implode(', ', $this->order));
        $this->query .= ($this->limit === null ? '' : ' LIMIT ' . $this->limit);
        // $pdoStatement = self::instance()->prepare($this->query);
        // $pdoStatement->execute();
        // $data = $pdoStatement->fetchAll(\PDO::FETCH_ASSOC);
        // return $data;
        return $this->query;
    }

    public static function query($query){
        // return self::instance()->query($query);
        return ($query);
    }

    public function insert($data,$batch=null): string
    {
        $this->query .= 'INSERT INTO ' . implode(', ', $this->from);
        if($batch==='batch'){
            if (count($data) == count($data, COUNT_RECURSIVE)){
                $this->query = 'Error: One dimention Array! Insert without batch.';
            }else{
                $this->columns = implode(', ',array_keys($data[0]));
                $this->query .= ' ('.$this->columns.') VALUES';
                foreach($data as $d){
                    $this->values = implode("', '",array_values($d));
                    $this->query .= " ('".$this->values."'), ";
                }
                $this->query = rtrim($this->query,', ');
            }
        }else{
            if (count($data) == count($data, COUNT_RECURSIVE)){
                $this->columns = implode(', ',array_keys($data));
                $this->values = implode("', '",array_values($data));
                $this->query .= " (".$this->columns.") VALUES ('".$this->values."')";
            }else{
                $this->query = 'Error: Multidimention Array! Insert as batch.';
            }
        }
        return $this->queryExecute($this->query);
    }

    public function delete()
    {
        $this->query = 'DELETE FROM ' . implode(', ', $this->from) . ($this->conditions === [] ? '' : ' WHERE ' . implode(' AND ', $this->conditions));
        return $this->queryExecute($this->query);
    }

    public function update($data)
    {
        if (count($data) == count($data, COUNT_RECURSIVE)){
            $this->columns = implode(', ', array_map(fn($k, $v) => ("$k = ".(is_numeric($v) ? "$v" : "'$v'")), array_keys($data), $data));
            $this->query = 'UPDATE ' . implode(', ', $this->from)
            . ' SET ' . $this->columns . ($this->conditions === [] ? '' : ' WHERE ' . implode(' = ', $this->conditions));
        }else{
            foreach($data as $key=>$d){
                $this->columns = implode(', ', array_map(fn($k, $v) => ("$k = ".(is_numeric($v) ? "$v" : "'$v'")), array_keys($d), $d));
                $this->query .= 'UPDATE ' . implode(', ', $this->from) . ' SET ' . $this->columns . ($this->conditions === [] ? '' : ' WHERE ' . implode(' = ', $this->conditions)). '; ';
            }
        }
        return $this->queryExecute($this->query);
    }

    public function queryExecute($query){
        return self::instance()->prepare($query)->execute();
    }
}

$objQB = new QueryBuilder();
echo $objQB->select('col1','col2')->table('users')->where('id','=',1,'AND','name','=','rehan')->leftJoin('posts','users.id')->on('users.id','=','posts.id')->fetch();
