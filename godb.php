<?php
/**
 * Библиотека для работы с базой данных MySQL
 * 
 * @package   goDB
 * @version   1.2.0 (14 сентября 2010)
 * @author    Григорьев Олег aka vasa_c
 * @copyright &copy; PyhaTeam, 2007-2010
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL
 * @uses      mysqli
 */

class goDB extends mysqli 
{

  /*** PUBLIC: ***/
    
    /**
     * Конструктор. От mysqli отличается тем, что генерирует исключение при ошибке подключения
     *
     * @exception goDBExceptionConnect
     *
     * @param string $host      [optional]
     * @param string $username  [optional]
     * @param string $passwd    [optional]
     * @param string $dbname    [optional]
     * @param int    $port      [optional]
     * @param string $socket    [optional]
     */
    public function __construct($host = null, $username = null, $passwd = null, $dbname = null, $port = null, $socket = null) {       
    	if (is_array($host)) {
    		$username = isset($host['username']) ? $host['username'] : null;
    		$passwd   = isset($host['passwd'])   ? $host['passwd']   : null;
    		$dbname   = isset($host['dbname'])   ? $host['dbname']   : null;
    		$port     = isset($host['port'])     ? $host['port']     : null;
    		$socket   = isset($host['socket'])   ? $host['socket']   : null;
            $charset  = isset($host['charset'])  ? $host['charset']  : null;
    		if (isSet($host['prefix'])) {
    			$this->setPrefix($host['prefix']);
    		}
    		if (isSet($host['debug'])) {
    			$this->setDebug($host['debug']);
    		}
    		$host = isSet($host['host']) ? $host['host'] : null;
    	}
    	if (!$port) {
        	$host = explode(':', $host, 2);
        	$port = empty($host[1]) ? null : $host[1];        
        	$host = $host[0];    	
    	}
        @parent::__construct($host, $username, $passwd, $dbname, $port, $socket);
        if (mysqli_connect_errno()) {
            throw new goDBExceptionConnect(mysqli_connect_error(), mysqli_connect_errno());
        }
        if (!empty($charset)) {
            $this->set_charset($charset);
        }
    }  
    
    /**
     * Выполнение запроса к базе
     *
     * @exception goDBExceptionQuery ошибка при запросе
     * 
     * @param  string $pattern sql-запрос или строка-шаблон с плейсхолдерами
     * @param  array  $data    [optional] массив входных данных
     * @param  string $fetch   [optional] формат результата
     * @param  string $prefix  [optional] префикс имен таблиц
     * @return mixed  результат запроса в заданном формате     
     */
    public function query($pattern, $data = null, $fetch = null, $prefix = null) {        
		self::$qQuery++;
    	$query = self::makeQuery($pattern, $data, $prefix);
        if ($this->queryWrapper) {
            $query = call_user_func_array($this->queryWrapper, array($query));
            if (!$query) {
                return false;
            }
        }
        if ($this->queryDebug) {
        	if ($this->queryDebug === true) {
            	print '<pre>'.htmlSpecialChars($query).'</pre>';
        	} else {
        		call_user_func($this->queryDebug, $query);
        	}
        }        
        $result = parent::query($query, MYSQLI_STORE_RESULT);
        if ($this->errno) {
            throw new goDBExceptionQuery($query, $this->errno, $this->error);
        }
        $return = $this->fetch($result, $fetch);
        if ((!is_object($return)) && (is_object($result))) {
            $result->free();
        }
        return $return;
    }    

    /**
     * Формирование запроса
     *
     * @param string $pattern строка-шаблон с плейсхолдерами
     * @param array  $data    массив входных данных
     * @param string $prefix  [optional] префикс таблиц
     */
    public function makeQuery($pattern, $data, $prefix = null) {
        $prefix = ($prefix === null) ? $this->tablePrefix : $prefix;    
		$query  = preg_replace('~{(.*?)}~', '`'.preg_quote($prefix).'\1`', $pattern);
		if (!$data) {
			return $query;
		}
        $this->_prefix  = $prefix;
        $this->_data    = $data;
        $this->_dataCnt = 0;
        $this->_phType  = null;
        $query = preg_replace_callback('~\?([a-z\?-]+)?(:([a-z0-9_-]*))?;?~i', Array($this, '_makeQuery'), $query);
        if (($this->_phType == 'r') && ($this->_dataCnt < count($data))) {
            throw new goDBExceptionDataMuch('It is too much data');
        }
        return $query;
    }    
    
    /**
     * Разбор результата в нужном формате
     *
     * @param  mysqli_result $result результат
     * @param  string        $fetch  формат
     * @return mixed
     */
    public function fetch($result, $fetch) {
        $fetch = strToLower($fetch);
        if ((!$fetch) || ($fetch == 'no')) {
            return $result;
        }
        if ($fetch == 'id') {
            return $this->insert_id;
        }
        if ($fetch == 'ar') {
            return $this->affected_rows;
        }
        $numRows = $result->num_rows;
        if ($fetch == 'num') {
            return $numRows;
        }
        if ($fetch == 'row') {
            $A = Array();
            for ($i = 0; $i < $numRows; $i++) {
                $A[] = $result->fetch_row();
            }
            return $A;
        }
        if ($fetch == 'assoc') {
            $A = Array();
            for ($i = 0; $i < $numRows; $i++) {
                $A[] = $result->fetch_assoc();
            }
            return $A;
        }
        if ($fetch == 'col') {
            $A = Array();
            for ($i = 0; $i < $numRows; $i++) {
                $r = $result->fetch_row();
                $A[] = $r[0];
            }
            return $A;
        }
        if ($fetch == 'object') {
        	$A = Array();
        	for ($i = 0; $i < $numRows; $i++) {
        		$A[] = $result->fetch_object();        		
        	}
        	return $A;
        }
        if ($fetch == 'vars') {
        	$A = Array();
        	for ($i = 0; $i < $numRows; $i++) {
        		$r = $result->fetch_row();
        		$A[$r[0]] = $r[1];
        	}
        	return $A;
        }
        if ($fetch == 'irow') {
            return new goDBResultRow($result);
        }
        if ($fetch == 'iassoc') {
            return new goDBResultAssoc($result);
        }
        if ($fetch == 'icol') {
            return new goDBResultCol($result);
        }
        if ($fetch == 'iobject') {
            return new goDBResultObject($result);
        }        
        if ($numRows == 0) {
            if (!in_array($fetch, array('rowrow', 'rowassoc', 'rowobject', 'el'))) {
                throw new goDBExceptionFetch($fetch);
            }
            return false;
        }
        if ($fetch == 'rowrow') {
            return $result->fetch_row();
        }
        if ($fetch == 'rowassoc') {
            return $result->fetch_assoc();
        }
        if ($fetch == 'rowobject') {
            return $result->fetch_object();
        }        
        if ($fetch == 'el') {
            $r = $result->fetch_row();
            return $r[0];
        }
        throw new goDBExceptionFetch($fetch);
    }

    /**
     * Установка префикса таблиц
     *
     * @param string $prefix
     */
    public function setPrefix($prefix) {
        $this->tablePrefix = $prefix;
        return true;
    }

    /**
     * Установить значение отладки
     *
     * @param bool $debug
     */
    public function setDebug($debug = true) {
        $this->queryDebug = $debug;
        return true;
    }
    
    /**
     * Декорирование query()
     *
     * @param callback $wrapper функция-декоратор
     */
    public function queryDecorated($wrapper) {
    	$this->queryWrapper = $wrapper;
    	return true;
    }    
    
    /**
     * Количество записей в таблице, удволетворяющих условию
     * 
     * @param string $table
     *        имя таблицы (используется префикс)
     * @param string $where [optional]
     *        условие WHERE (с плейсхолдерами). Нет - вся таблица.
     * @param array $data [optional]
     *        данные для WHERE
     * @return int
     *         количество записей удовлетворяющих условию
     */
    public function countRows($table, $where = null, $data = null) {
    	$where   = $where ? ('WHERE '.$where) : '';
    	$pattern = 'SELECT COUNT(*) FROM {'.$table.'} '.$where;
    	return $this->query($pattern, $data, 'el');
    }    
 
  /*** STATIC: ***/
  
      const baseName = 'base';
      
    /**
     * Создание базы и сохранение в пространстве имен
     *
     * @exception goDBExceptionDBAlready заданное имя уже существует
     * 
     * @param  string $host     хост - возможно указание порта через ":"
     * @param  string $username
     * @param  string $passwd
     * @param  string $dbname
     * @param  string $name     [optional] наименование
     * @param  bool   $post     [optional] отложенное подключение
     * @return mixed
     */
    public static function makeDB($host, $username = null, $passwd = null, $dbname = null, $name = null, $post = false) {
    	if (is_array($host)) {
    		$name = isset($host['name']) ? $host['name'] : self::baseName;
            if (isset($host['link'])) {
                self::assocDB($name, $host['link']);
                return true;
            }
    		$post = isset($host['postmake']) ? $host['postmake'] : false;
    	} elseif (!$name) {
            $name = self::baseName;
        }
        if (isSet(self::$dbList[$name])) {
            throw new goDBExceptionDBAlready($name);
        }
        if (!$post) {
            self::$dbList[$name] = new self($host, $username, $passwd, $dbname);        
        } else {
            self::$dbList[$name] = Array($host, $username, $passwd, $dbname);
        }
        return self::$dbList[$name];
    }

    /**
     * Сохранить базу в пространстве имен
     *
     * @exception goDBExceptionAlready имя занято
     * 
     * @param goDB   $db
     * @param string $name [optional]
     */
    public static function setDB(goDB $db, $name = self::baseName) {
        if (isSet(self::$dbList[$name])) {
            throw new goDBExceptionDBAlready($name);
        }
        self::$dbList[$name] = $db;
        return true;
    }
    
    /**
     * Ассоциация с базой
     *
     * @exception goDBExceptionDBAlready  имя новой занято
     * @exception goDBExceptionDBNotFound старой базы нет
     * 
     * @param string $one новая база
     * @param string $two та, с которой ассоциируется
     */
    public static function assocDB($one, $two) {
    	if (isset(self::$dbList[$one])) {
    		throw new goDBExceptionDBAlready($one);
    	}
    	if (!isset(self::$dbList[$two])) {
    		throw new goDBExceptionDBNotFound($two);
    	}
    	self::$dbList[$one] = $two;
    	return true;
    }

    /**
     * Получить базу из пространства имен
     *
     * @exception goDBExceptionDBNotFound нет базы с таким именем
     * @exception goDBExceptionConnect    может произойти ошибка при отложенном подключении
     * 
     * @param  string $name
     * @return goDB
     */
    public static function getDB($name = self::baseName) {
        if (!isset(self::$dbList[$name])) {
            throw new goDBExceptionDBNotFound($name);
        }
        if (is_array(self::$dbList[$name])) {
            $prm = self::$dbList[$name];        
            self::$dbList[$name] = new self($prm[0], $prm[1], $prm[2], $prm[3]);
        } elseif (!is_object(self::$dbList[$name])) {
        	self::$dbList[$name] = self::getDB(self::$dbList[$name]);
        }
        return self::$dbList[$name];
    }

    /**
     * Делегирование запроса к нужному объекту БД
     *
     * @param  string $pattern
     * @param  array  $data
     * @param  string $fetch
     * @param  string $prefix
     * @param  string $name
     * @return mixed
     */
    public static function queryDB($pattern, $data = null, $fetch = null, $prefix = null, $name = self::baseName) {
        return self::getDB($name)->query($pattern, $data, $fetch, $prefix);
    }      
    
	/**
	 * Получить количество запросов через данный класс
	 *
	 * @return int
	 */
    public static function getQQuery() {
    	return self::$qQuery;
    }
    
  /*** PRIVATE: ***/

    /**
     * Вспомагательная функция для формирования запроса
     *
     * @param  array  $matches
     * @return string
     */
    private function _makeQuery($matches) {
        if (!isset($matches[1])) {
            /* Простой регулярный (не именованный) плейсхолдер "?" */
            $placeholder = '';
            $type        = 'r';
        } else {
            /* Плейсхолдер с указанием типа и (или) именованный */
            $placeholder = strtolower($matches[1]);
            if ($placeholder == '?') {
                return '?';
            }
            if (isset($matches[2])) {
                /* Именованный плейсхолдер */
                if (isset($matches[3])) {
                    $type = 'n';
                    $name = $matches[3];
                } else {
                    /* ":" поставлена, а имя не указано */
                    throw new goDBExceptionDataPlaceholder($matches[0]);
                }
            } else {
                /* Регулярный плейсхолдер */
                $type = 'r';
            }
        }
        if ($type == 'r') {
            /* Для регулярного плейсхолдера индекс - очередной номер */
            $name = $this->_dataCnt;
            $this->_dataCnt++;
        }
        if (!$this->_phType) {
            /* Тип плейсхолдеров ещё не определён */
            $this->_phType = $type;
        } elseif ($this->_phType != $type) {
            /* Неоднородные плейсхолдеры в одном запроса */
            throw new goDBExceptionDataMixed('regularly and named placeholder in a query');
        }
        if (!array_key_exists($name, $this->_data)) {
            /* Нет такого индекса */
            throw new goDBExceptionDataNotEnough('It is not enough data');
        }
        $value = $this->_data[$name];

        switch ($placeholder) {
            case '':
            case 'string':
                return '"'.$this->real_escape_string($value).'"';
            case 'n':
            case 'null':
                return is_null($value) ? 'NULL' : '"'.$this->real_escape_string($value).'"';
            case 'i':
            case 'int':
                return (0 + $value);
            case 'in':
            case 'ni':
            case 'int-null':
                return is_null($value) ? 'NULL' : (0 + $value);
            case 'l':
            case 'list':
            case 'a':
                foreach ($value as &$e) {
                    $e = is_null($e) ? 'NULL' : '"'.$this->real_escape_string($e).'"';
                }
                return implode(',', $value);
            case 'li':
            case 'list-int':
            case 'ai':
            case 'ia':
                foreach ($value as &$e) {
                    $e = is_null($e) ? 'NULL' : (0 + $e);
                }
                return implode(',', $value);
            case 's':
            case 'set':
                $set = array();
                foreach ($value as $col => $val) {
                    $val   = is_null($val) ? 'NULL' : '"'.$this->real_escape_string($val).'"';
                    $set[] = '`'.$col.'`='.$val;
                }
                return implode(',', $set);
            case 'v':
            case 'values':
                $valueses = array();
                foreach ($value as $vs) {
                    $values = array();
                    foreach ($vs as $v) {
                        $values[] = is_null($v) ? 'NULL' : '"'.$this->real_escape_string($v).'"';
                    }
                    $valueses[] = '('.implode(',', $values).')';
                }
                return implode(',', $valueses);
            case 'e':
            case 'escape':
                return $this->real_escape_string($value);
            case 'q':
            case 'query':
                return $value;
            case 't':
            case 'table':
                return '`'.$this->_prefix.$value.'`';
            case 'c':
            case 'col':
                if (is_array($value)) {
                    return '`'.$this->_prefix.$value[0].'`.`'.$value[1].'`';
                }
                return '`'.$value.'`';
            default:
                throw new goDBExceptionDataPlaceholder($matches[0]);
        }
    }
  
  
  /*** VARS: ***/
    
   /**
     * Префикс таблиц по умолчанию
     *
     * @var string
     */
    protected $tablePrefix = '';

    /**
     * Разрешение отладки
     *
     * @var bool
     */
    protected $queryDebug = false;

    /**
     * Список баз данных
     *
     * @var array
     */
    protected static $dbList = Array();
    
    /**
     * Количество запросов через класс
     *
     * @var int
     */
    protected static $qQuery = 0;
    
    /**
     * Декоратор query()
     *
     * @var callback
     */
    protected $queryWrapper;

    /* Вспомагательная фигня */
    private $_data;
    private $_dataCnt;
    private $_phType;
    private $_prefix;
}


/****************************************
 * 
 * Иерархия исключений при работе с библиотекой
 *
 ****************************************/

interface goDBException {}

abstract class goDBRuntimeException extends RuntimeException implements goDBException {}
abstract class goDBLogicException extends LogicException implements goDBException {}


/**
 * Ошибка подключения
 */
class goDBExceptionConnect extends goDBRuntimeException {}

/**
 * Ошибка в запросе
 */
class goDBExceptionQuery extends goDBLogicException
{
    public function __construct($query, $errno, $error) {
		$msg = 'DB Error. Query="'.$query.'" error = '.$errno.' "'.$error.'"';
		parent::__construct($msg, $errno);
        $this->query = $query;
        $this->errno = $errno;
        $this->error = $error;
    }

    public function query() {return $this->query;}
    public function errno() {return $this->errno;}
    public function error() {return $this->error;}

    public function __toString() {
        return htmlspecialchars($this->getMessage());
    }

    private $query, $errno, $error;
}

/**
 * Несоответствие количество плейсхолдеров и входных данных
 */
abstract class goDBExceptionData extends goDBLogicException {}

/**
 * Данных больше плейсхолдеров
 */
class goDBExceptionDataMuch extends goDBExceptionData {}

/**
 * Данных меньше плейсхолдеров
 */
class goDBExceptionDataNotEnough extends goDBExceptionData {}

/**
 * Неизвестный плейсхолдер
 */
class goDBExceptionDataPlaceholder extends goDBExceptionData
{
    public function __construct($placeholder, $code = null) {
        $message = 'Unknown placeholder "'.$placeholder.'"';
        parent::__construct($message, $code);
    }
}

/**
 * В запросе используются именованные плейсхолдеры наравне с регулярными
 */
class goDBExceptionDataMixed extends goDBExceptionData {}

/**
 * Неверный fetch
 */
class goDBExceptionFetch extends goDBLogicException
{
    public function __construct($fetch, $code = null) {
        $message = 'Unknown fetch "'.$fetch.'"';
        parent::__construct($message, $code);
    }
}

/**
 * Проблемы со списком баз данных в пространстве имен DB
 */
abstract class goDBExceptionDB extends goDBLogicException {}
class goDBExceptionDBAlready  extends goDBExceptionDB {}
class goDBExceptionDBNotFound extends goDBExceptionDB {}

/**
 * Результат выполнения запроса (итератор)
 *
 */
abstract class goDBResult implements Iterator, ArrayAccess, Countable {

    public function __construct($result) {
        $this->result  = $result;
        $this->numRows = $result->num_rows;
    }

    public function __destruct() {
        if (is_resource($this->result)) {
            $this->result->free();
        }
    }

    public function rewind() {
        $this->count = 0;
        return true;
    }

    public function current() {
        $this->result->data_seek($this->count);
        return $this->getEl();
    }

    public function key() {
        return $this->count;
    }

    public function next() {
        $this->count++;
        return $this->current();
    }

    public function valid() {
        if ($this->count >= $this->numRows) {
            return false;
        }
        return true;
    }

    public function count() {
        return $this->numRows;
    }

    public function get($num, $index = false)  {
        if ($num >= $this->numRows) {
            return false;
        }
        $this->result->data_seek($num);
        $r = $this->getEl();
        if ($index === false) {
            return $r;
        }
        if (!is_array($r)) {
            return false;
        }
        if (!isSet($r[$index])) {
            return false;
        }
        return $r[$index];
    }

    public function offsetGet($offset) {
        return $this->get($offset);
    }
    public function offsetSet($offset, $value) {
        return false;
    }
    public function offsetExists($offset) {
        return (($offset >= 0) && ($offset < $numRows));
    }
    public function offsetUnset($offset) {
        return false;
    }


    abstract protected function getEl();

    protected $result, $numRows, $count = 0;
}

class goDBResultRow extends goDBResult
{
    protected function getEl() {
        return $this->result->fetch_row();
    }
}

class goDBResultAssoc extends goDBResult
{
    protected function getEl()  {
        return $this->result->fetch_assoc();
    }
}

class goDBResultCol extends goDBResult
{
    protected function getEl() {
        $r = $this->result->fetch_row();
        return $r[0];
    }
}

class goDBResultObject extends goDBResult
{
    protected function getEl() {
        return $this->result->fetch_object();        
    }
}
