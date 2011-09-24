<?php
/**
 * Библиотека для работы с базой данных MySQL
 * 
 * @package   goDB
 * @version   1.1.3 (1 июля 2010)
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
    public function __construct($host = null, $username = null, $passwd = null, $dbname = null, $port = null, $socket = null)
    {       
    	if (is_array($host)) {
    		$username = isSet($host['username']) ? $host['username'] : null;
    		$passwd   = isSet($host['passwd']) ? $host['passwd'] : null;
    		$dbname   = isSet($host['dbname']) ? $host['dbname'] : null;
    		$port     = isSet($host['port']) ? $host['port'] : null;
    		$socket   = isSet($host['socket']) ? $host['socket'] : null;
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
    public function query($pattern, $data = null, $fetch = null, $prefix = null)
    {        
		self::$qQuery++;
    	$query = self::makeQuery($pattern, $data, $prefix);
        if ($this->queryWrapper) {
        	if (!is_array($this->queryWrapper)) {
        		$nf = $this->queryWrapper;
        		if (!$nf($query, $fetch)) {
    	    		return false;
	        	}        		
        	} elseif (!call_user_func_array($this->queryWrapper, Array($query, $fetch))) {
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
    public function makeQuery($pattern, $data, $prefix = '')
    {	
        $prefix = ($prefix === null) ? $this->tablePrefix : $prefix;    
		$q = preg_replace('~{(.*?)}~', '`'.preg_quote($prefix).'\1`', $pattern);
		if (!$data) {
			return $q;
		}
        $this->_mqPH = $data;
        $this->_mqPrefix = $prefix;
        $q = @preg_replace_callback('/\?([int?casveq]?[ina]?);?/', Array($this, '_makeQuery'), $q);
        if (sizeOf($this->_mqPH) > 0) {
            throw new goDBExceptionDataMuch();
        }
        return $q;
    }    
    
    /**
     * Разбор результата в нужном формате
     *
     * @param  mysqli_result $result результат
     * @param  string        $fetch  формат
     * @return mixed
     */
    public function fetch($result, $fetch)
    {
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
        return true;
    }

    /**
     * Установка префикса таблиц
     *
     * @param string $prefix
     */
    public function setPrefix($prefix)
    {
        $this->tablePrefix = $prefix;
        return true;
    }

    /**
     * Установить значение отладки
     *
     * @param bool $debug
     */
    public function setDebug($debug = true)
    {
        $this->queryDebug = $debug;
        return true;
    }
    
    /**
     * Декорирование query()
     *
     * @param callback $wrapper функция-декоратор
     */
    public function queryDecorated($wrapper)
    {
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
    public function countRows($table, $where = null, $data = null)
    {
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
    public static function makeDB($host, $username = null, $passwd = null, $dbname = null, $name = null, $post = false)
    {
    	if (is_array($host)) {
    		$name = isSet($host['name']) ? $host['name'] : self::baseName;
    		$post = isSet($host['postmake']) ? $host['postmake'] : false;
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
    public static function setDB(goDB $db, $name = self::baseName)
    {
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
    public static function assocDB($one, $two)
    {
    	if (isSet(self::$dbList[$one])) {
    		throw new goDBExceptionDBAlready($one);
    	}
    	if (!isSet(self::$dbList[$two])) {
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
    public static function getDB($name = self::baseName)
    {
        if (!isSet(self::$dbList[$name])) {
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
    public static function queryDB($pattern, $data = null, $fetch = null, $prefix = null, $name = self::baseName)
    {
        return self::getDB($name)->query($pattern, $data, $fetch, $prefix);
    }      
    
	/**
	 * Получить количество запросов через данный класс
	 *
	 * @return int
	 */
    public static function getQQuery()
    {
    	return self::$qQuery;
    }
    
  /*** PRIVATE: ***/

    /**
     * Вспомагательная функция для формирования запроса
     *
     * @param  array  $ph
     * @return string
     */
    private function _makeQuery($ph)
    {
        if ($ph[1] == '?') {
            return '?';
        }
        if (sizeOf($this->_mqPH) == 0) {
            throw new goDBExceptionDataNotEnough();
        }
        $el = array_shift($this->_mqPH);
        switch ($ph[1]) {
            case ('i'): return (0 + $el);
            case ('t'): return '`'.$this->_mqPrefix.$el.'`';
            case ('c'):
                if (is_array($el)) {
                    return '`'.$this->_mqPrefix.$el[0].'`.`'.$el[1].'`';
                }
                return '`'.$el.'`';
            case ('n'): return is_null($el) ? 'NULL' : ('"'.$this->real_escape_string($el).'"');
            case ('ni'):
            case ('in'): return is_null($el) ? 'NULL' : (0 + $el);
            case ('a'):
                foreach ($el as &$e) {
                    $e = '"'.$this->real_escape_string($e).'"';
                }
                return implode(',', $el);
            case ('ai'):
            case ('ia'):
                foreach ($el as &$e) {
                    $e = (0 + $e);
                }
                return implode(',', $el);
            case ('s'):
            	$set = Array();
            	foreach ($el as $k => $v) {
            		if ($v !== null) {
            			$set[] = '`'.$k.'`="'.$this->real_escape_string($v).'"';
            		} else {
            			$set[] = '`'.$k.'`=NULL';
            		}
            	}
            	return implode(',', $set);
            case ('v'):
            	$valueses = Array();
            	foreach ($el as $v) {
            		$values = Array();
            		foreach ($v as $d) {
            			if ($d !== null) {
            				$values[] = '"'.$this->real_escape_string($d).'"';
            			} else {
            				$values[] = 'NULL';
            			}
            		}
            		$valueses[] = '('.implode(',', $values).')';
            	}
            	return implode(',', $valueses);
            case ('e'):
            	return $this->real_escape_string($el);
            case ('q'):
            	return $el;
        }
        return '"'.$this->real_escape_string($el).'"';
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
    private $_mqPH;
    private $_mqPrefix;      
}


/****************************************
 * 
 * Классы исключений при работе с базой  
 *
 ****************************************/

/**
 *  Базовый
 */
class goDBException extends RuntimeException {}

/**
 * Ошибка подключения
 */
class goDBExceptionConnect extends goDBException {}

/**
 * Ошибка в запросе
 */
class goDBExceptionQuery extends goDBException
{
    public function __construct($query, $errno, $error)
    {
		$msg = 'DB Error. Query="'.$query.'" error = '.$errno.' "'.$error.'"';
		parent::__construct($msg, $errno);
        $this->query = $query;
        $this->errno = $errno;
        $this->error = $error;
    }

    public function query() {return $this->query;}
    public function errno() {return $this->errno;}
    public function error() {return $this->error;}

    public function __toString()
    {
        return htmlspecialchars($this->getMessage());
    }

    private $query, $errno, $error;
}

/**
 * Несоответствие количество плейсхолдеров и входных данных
 */
class goDBExceptionData extends goDBException
{
    protected $message = 'DB Data Exception';
}

/**
 * Данных больше плейсхолдеров
 */
class goDBExceptionDataMuch extends goDBExceptionData
{
    protected $message = 'It is too much data';
}

/**
 * Данных меньше плейсхолдеров
 */
class goDBExceptionDataNotEnough extends goDBExceptionData
{
    protected $message = 'It is not enough data';
}

/**
 * Проблемы со списком баз данных в пространстве имен DB
 */
class goDBExceptionDB extends goDBException {}
class goDBExceptionDBAlready  extends goDBExceptionDB {}
class goDBExceptionDBNotFound extends goDBExceptionDB {}

/**
 * Результат выполнения запроса (итератор)
 *
 */
abstract class goDBResult implements Iterator, ArrayAccess, Countable
{

    public function __construct($result)
    {
        $this->result  = $result;
        $this->numRows = $result->num_rows;
    }

    public function __destruct()
    {
        if (is_resource($this->result)) {
            $this->result->free();
        }
    }

    public function rewind()
    {
        $this->count = 0;
        return true;
    }

    public function current()
    {
        $this->result->data_seek($this->count);
        return $this->getEl();
    }

    public function key()
    {
        return $this->count;
    }

    public function next()
    {
        $this->count++;
        return $this->current();
    }

    public function valid()
    {
        if ($this->count >= $this->numRows) {
            return false;
        }
        return true;
    }

    public function count()
    {
        return $this->numRows;
    }

    public function get($num, $index = false)
    {
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

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }
    public function offsetSet($offset, $value)
    {
        return false;
    }
    public function offsetExists($offset)
    {
        return (($offset >= 0) && ($offset < $numRows));
    }
    public function offsetUnset($offset)
    {
        return false;
    }


    abstract protected function getEl();

    protected $result, $numRows, $count = 0;
}

class goDBResultRow extends goDBResult
{
    protected function getEl()
    {
        return $this->result->fetch_row();
    }
}

class goDBResultAssoc extends goDBResult
{
    protected function getEl()
    {
        return $this->result->fetch_assoc();
    }
}

class goDBResultCol extends goDBResult
{
    protected function getEl()
    {
        $r = $this->result->fetch_row();
        return $r[0];
    }
}

class goDBResultObject extends goDBResult
{
    protected function getEl()
    {
        return $this->result->fetch_object();        
    }
}

?>
