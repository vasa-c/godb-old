<?php
/**
 * Юнит-тестирование goDB
 * Абстрактный базовый класс для всех тестов
 *
 * От goDBTestBase наследуются конкретные тесты goDB
 * В $config следует указать параметры подключения к базе.
 *
 * Единственная используемая таблица в тестовой базе - `godb`, остальные не затрагиваются.
 *
 * Получение объекта базы:
 *     $db = $this->db();
 *
 * Получение объекта базы с заполнением тестовой таблицы тестовыми данными:
 *     $db = $this->db(true);
 *
 * @package    goDB
 * @subpackage unittest
 * @version    1.2.2
 * @author     Григорьев Олег aka vasa_c
 * @link       http://pyha.ru/go/godb/unit/
 * @uses       PHPUnit
 */

require(dirname(__FILE__).'/../../godb.php');

abstract class goDBTestBase extends PHPUnit_Framework_TestCase {

    /**
     * Параметры тестовой базы
     *
     * @var array
     */
    protected $config = array(
        'host'     => 'localhost',
        'username' => 'test',
        'passwd'   => 'test',
        'dbname'   => 'test',
        'charset'  => 'utf8',
    );

    /**
     * Несуществующий пользователь
     * Для тестирования ошибки подключения
     *
     * @var string
     */
    protected $usernameUnknown = 'user_unknown';

    /**
     * Несуществующее название базы
     * Для тестирования ошибки подключения
     *
     * @var string
     */
    protected $dbUnknown = 'db_unknown';

    /**
     * Инициализация перед каждым тестом
     * 
     * Лучше для каждого теста создавать новое подключение, хоть и немного накладно, но корректнее
     */
    public function setUp() {
        $this->makeDB();
    }

    /**
     * Уничтожение подключения после каждого теста
     */
    public function tearDown() {
        $this->closeDB();
    }

    /**
     * Создание нового подключения
     */
    protected function makeDB() {
        $this->db = new goDB($this->config);
    }

    /**
     * Уничтожение старого подключения
     */
    protected function closeDB() {
        if ($this->db) {
            $this->db->close();
            $this->db = null;
        }
    }


    /**
     * Получение тестового объекта goDB
     *
     * @param bool $fill [optional]
     *        заодно и заполнить
     * @return goDB
     */
    protected function db($fill = false) {
        if (!$this->db) {
            $this->makeDB();
        }
        if ($fill) {
            $this->fillDB();
        }
        return $this->db;
    }


    /**
     * Заполнить базу тестовыми данными
     */
    protected function fillDB() {

        $sql = '
            DROP TABLE IF EXISTS `godb`;
            CREATE TABLE `godb` (
                `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `number`  TINYINT NOT NULL,
                `caption` VARCHAR(30) NULL DEFAULT NULL,
                PRIMARY KEY(`id`)
            ) ENGINE='.$this->testTableEngine.';
            INSERT INTO `godb` (`number`,`caption`) VALUES
                (1, "one"),
                (3, "three"),
                (5, "five"),
                (4, NULL),
                (3, "double"),
                (8, "eight"),
                (9, NULL);
        ';

        if (!$this->db->multi_query($sql)) {
            throw new RuntimeException('fill db: '.$this->db->error);
        }
        while (true) {
            $result = $this->db->store_result();
            if ($result) {
                $result->free();
            }
            if (!$this->db->more_results()) {
                break;
            }
            $this->db->next_result();
        }
        
        return true;
    }

    /**
     * Заполнение конфига отсутствующими полями
     *
     * @return array
     */
    protected function configNormalize() {
        $config = $this->config;
        $result = array();
        $fields = array('host', 'username', 'passwd', 'dbname', 'port', 'socket');
        foreach ($fields as $field) {
            $result[$field] = isset($config[$field]) ? $config[$field] : null;
        }
        return $result;
    }

    /**
     * Движок тестовой таблицы
     *
     * @var string
     */
    protected $testTableEngine = 'MEMORY';

    /**
     * Тестовый объект goDB
     * @var goDB
     */
    protected $db;
}