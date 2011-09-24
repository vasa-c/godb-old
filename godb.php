<?php
/**
 * Библиотека для работы с базой данных MySQL
 * 
 * @package   goDB
 * @version   1.2.2 (28 сентября 2010)
 * @link      http://pyha.ru/go/godb/
 * @author    Григорьев Олег aka vasa_c (http://blgo.ru/blog/)
 * @copyright &copy; Григорьев Олег & PyhaTeam, 2007-2010
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL
 * @uses      php_mysqli (http://php.net/mysqli)
 */

class goDB extends mysqli 
{

  /*** PUBLIC: ***/
    
    /**
     * Конструктор.
     *
     * Отличия от конструктора mysqli (http://php.net/manual/en/mysqli.connect.php):
     * 1. Исключение при ошибке подключения
     * 2. Дополнительный формат вызова: один аргумент - массив параметров
     * 3. Установка кодировки
     *
     * @link http://pyha.ru/go/godb/connect/
     *
     * @throws goDBExceptionConnect
     *         не подключиться или не выбрать базу
     *
     * @param mixed $host [optional]
     *        хост для подключения (возможен вариант "host:port")
     *        либо массив всех параметров
     * @param string $username [optional]
     *        имя пользователя mysql
     * @param string $passwd [optional]
     *        пароль пользователя mysql
     * @param string $dbname [optional]
     *        имя базы данных
     * @param int $port [optional]
     *        порт для подключения
     *        в случае указания аргументом и в строке $host используется аргумент
     * @param string $socket [optional]
     *        mysql-сокет для подключения
     */
    public function __construct($host = null, $username = null, $passwd = null, $dbname = null, $port = null, $socket = null) {       
    	if (is_array($host)) {
            $config = $host;
            $fields = array(
                'host', 'username', 'passwd', 'dbname', 'port', 
                'socket', 'charset', 'debug', 'prefix',
            );
            foreach ($fields as $field) {
                $$field = isset($config[$field]) ? $config[$field] : null;
            }
    		$this->setPrefix($prefix);
   			$this->setDebug($debug);
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
     * @link http://pyha.ru/go/godb/query/
     *
     * @throws goDBExceptionQuery
     *         ошибка при запросе
     * @throws goDBExceptionData
     *         ошибочный шаблон или входные данные
     * @throws goDBExceptionFetch
     *         неизвестный или неожиданный формат представления
     * 
     * @param string $pattern
     *        sql-запрос или строка-шаблон с плейсхолдерами
     * @param array $data [optional]
     *        массив входных данных
     * @param string $fetch [optional]
     *        требуемый формат представления результата
     * @param string $prefix [optional]
     *        префикс имен таблиц
     * @return mixed
     *         результат запроса в заданном формате
     */
    public function query($pattern, $data = null, $fetch = null, $prefix = null) {        
		self::$qQuery++;
    	$query = $this->makeQuery($pattern, $data, $prefix);
        if ($this->queryWrapper) {
            $query = call_user_func_array($this->queryWrapper, array($query));
            if (!$query) {
                return false;
            }
        }
        if ($this->queryDebug) {
        	if ($this->queryDebug === true) {
            	echo '<pre>'.htmlSpecialChars($query).'</pre>';
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
     * @link http://pyha.ru/go/godb/query/#ph-list
     *
     * @throws goDBExceptionData
     *         ошибочный шаблон или несответствие ему входных данных
     *
     * @param string $pattern
     *        строка-шаблон с плейсхолдерами
     * @param array $data
     *        массив входных данных
     * @param string $prefix [optional]
     *        префикс таблиц
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
     * @link http://pyha.ru/go/godb/fetch/
     *
     * @throws goDBExceptionFetch
     *         неизвестный или неожиданный формат представления
     *
     * @param mysqli_result $result
     *        результат запроса
     * @param string $fetch
     *        требуемый формат представления
     * @return mixed
     *         результат в требуемом формате
     */
    public function fetch($result, $fetch) {
        $fetch   = explode(':', $fetch, 2);
        $options = isset($fetch[1]) ? $fetch[1] : '';
        $fetch   = strtolower($fetch[0]);
        switch ($fetch) {
            case null:
            case 'no':
                return $result;
            case 'id':
                return $this->insert_id;
            case 'ar':
                return $this->affected_rows;
        }
        if (!is_object($result)) {
            $this->checkFetch($fetch);
            throw new goDBExceptionFetchUnexpected($fetch);
        }
        switch ($fetch) {
            case 'assoc':
                $return = array();
                while ($row = $result->fetch_assoc()) {
                    $return[] = $row;
                }
                return $return;
            case 'row':
                $return = array();
                while ($row = $result->fetch_row()) {
                    $return[] = $row;
                }
                return $return;
            case 'col':
                $return = array();
                while ($row = $result->fetch_row()) {
                    $return[] = $row[0];
                }
                return $return;
            case 'object':
                $return = array();
                while ($row = $result->fetch_object()) {
                    $return[] = $row;
                }
                return $return;
            case 'vars':
                $return = array();
                while ($row = $result->fetch_row()) {
                    $return[$row[0]] = isset($row[1]) ? $row[1] : $row[0];
                }
                return $return;
            case 'kassoc':
                $return = array();
                $key    = $options;
                while ($row = $result->fetch_assoc()) {
                    if (!$key) {
                        reset($row);
                        $key = key($row);
                    }
                    $return[$row[$key]] = $row;
                }
                return $return;
            case 'iassoc':
                return new goDBResultAssoc($result);
            case 'irow':
                return new goDBResultRow($result);
            case 'icol':
                return new goDBResultCol($result);
            case 'iobject':
                return new goDBResultObject($result);
        }

        $num = $result->num_rows;
        if ($fetch == 'num') {
            return $num;
        }
        if ($num == 0) {
            $this->checkFetch($fetch, 'one');
            return false;
        }

        switch ($fetch) {
            case 'rowassoc':
                return $result->fetch_assoc();
            case 'rowrow':
                return $result->fetch_row();
            case 'rowobject':
                return $result->fetch_object();
            case 'el':
                $r = $result->fetch_row();
                return $r[0];
        }

        throw new goDBExceptionFetchUnknown($fetch);
    }

    /**
     * Установка префикса таблиц
     *
     * @link http://pyha.ru/go/godb/etc/
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
     * @link http://pyha.ru/go/godb/etc/
     *
     * @param mixed $debug
     *        true:     вывод в поток
     *        false:    отключение отладки
     *        callback: вызов указанной функции
     */
    public function setDebug($debug = true) {
        $this->queryDebug = $debug;
        return true;
    }
    
    /**
     * Декорирование query()
     *
     * @link http://pyha.ru/go/godb/etc/
     *
     * @param callback $wrapper
     *        функция-декоратор
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

    /**
     * Имя по умолчанию в пространстве имён
     * @const string
     */
    const baseName = 'base';
      
    /**
     * Создание базы и сохранение в пространстве имен
     *
     * Возможно указание как всех аргументов метода,
     * так и указание единственного - конфигурационного массива
     *
     * @link http://pyha.ru/go/godb/namespace/
     *
     * @throws goDBExceptionDBAlready
     *         заданное имя уже существует
     * @throws goDBExceptionConnect
     *         ошибка подключения, если не используется отложенное подключение
     * 
     * @param mixed $host
     *        хост - возможно указание порта через ":"
     *        либо конфигурационный массив
     * @param string $username [optional]
     *        имя пользователя базы
     * @param string $passwd [optional]
     *        пароль пользователя базы
     * @param string $dbname [optional]
     *        имя базы данных
     * @param string $name [optional]
     *        наименование данной базы в пространстве имён
     * @param bool $post [optional]
     *        использовать ли отложенное подключение
     * @return mixed
     *         объект базы данные если не используется отложенное подключение
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
        if (isset(self::$dbList[$name])) {
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
     * @throws goDBExceptionAlready
     *         имя занято
     * 
     * @param goDB $db
     * @param string $name [optional]
     */
    public static function setDB(goDB $db, $name = self::baseName) {
        if (isset(self::$dbList[$name])) {
            throw new goDBExceptionDBAlready($name);
        }
        self::$dbList[$name] = $db;
        return true;
    }
    
    /**
     * Ассоциация с базой
     *
     * @throws goDBExceptionDBAlready
     *         имя новой занято
     * @throws goDBExceptionDBNotFound
     *         целевая база отсутствует
     * 
     * @param string $one
     *        новая база
     * @param string $two
     *        та, с которой ассоциируется
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
     * @throws goDBExceptionDBNotFound
     *         нет базы с таким именем
     * @throws goDBExceptionConnect
     *         может произойти ошибка при отложенном подключении
     * 
     * @param string $name
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
     * Делегирование запроса к нужному объекту БД из пространства имён
     *
     * @throws goDBException
     *         - нет такой базы
     *         - ошибка отложенного подключения
     *         - ошибки шаблона и данных
     *         - ошиочный формат разбора
     *         - ошибочный запрос
     *
     * @param string $pattern [optional]
     * @param array  $data [optional]
     * @param string $fetch [optional]
     * @param string $prefix [optional]
     * @param string $name [optional]
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
     * @param array $matches
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
                    if (empty($name)) {
                        throw new goDBExceptionDataPlaceholder($matches[0]);
                    }
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
            case 'bool':
                return $value ? '"1"' : '"0"';
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

    /**
     * Проверка формата разбора
     *
     * @throws goDBExceptionFetchUnknown
     *         неизвестный формат
     *
     * @param string $fetch
     *        формат разбора
     * @param string $groups [optional]
     *        в какой группе искать
     *        не указана - во всех
     */
    private function checkFetch($fetch, $group = null) {
        if ($group) {
            if (in_array($fetch, self::$listFetchs[$group])) {
                return true;
            }
        } else {
            foreach (self::$listFetchs as $fetchs) {
                if (in_array($fetch, $fetchs)) {
                    return true;
                }
            }
        }
        throw new goDBExceptionFetchUnknown($fetch);
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
     * Список всех форматов представления результата по группам
     * @var array
     */
    private static $listFetchs = array(
        /* Возвращающие множество записей */
        'many' => array(
            'assoc', 'row', 'col', 'object',
            'iassoc', 'irow', 'icol', 'iobject',
            'vars', 'num',
        ),
        /* Возвращаюшие одну запись */
        'one' => array(
            'rowassoc', 'rowrow', 'rowobject', 'el',
        ),
        /* Другие */
        'other' => array(
            'no', 'id', 'ar',
        ),
    );

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
class goDBExceptionDataPlaceholder extends goDBExceptionData {
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
abstract class goDBExceptionFetch extends goDBLogicException {}

/**
 * Неверный fetch - неизвестный
 */
class goDBExceptionFetchUnknown extends goDBExceptionFetch {
    public function __construct($fetch, $code = null) {
        $message = 'Unknown fetch "'.$fetch.'"';
        parent::__construct($message, $code);
    }
}

/**
 * Неверный fetch - неожиданный (assoc после INSERT, например)
 */
class goDBExceptionFetchUnexpected extends goDBExceptionFetch {
    public function __construct($fetch, $code = null) {
        $message = 'Unexpected fetch "'.$fetch.'" for this query';
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
            return null;
        }
        $this->result->data_seek($num);
        $r = $this->getEl();
        if ($index === false) {
            return $r;
        }
        if (!is_array($r)) {
            return null;
        }
        if (!isset($r[$index])) {
            return null;
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
        return (($offset >= 0) && ($offset < $this->numRows));
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
