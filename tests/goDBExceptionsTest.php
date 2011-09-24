<?php
/**
 * Юнит-тестирование goDB
 * Тестирование исключений библиотеки
 *
 * @package    goDB
 * @subpackage unittest
 * @version    1.2.2
 * @author     Григорьев Олег aka vasa_c
 * @link       http://pyha.ru/go/godb/unit/
 * @link       http://pyha.ru/go/godb/errors/
 * @uses       PHPUnit
 */

require_once(dirname(__FILE__).'/base/goDBTestBase.php');

class goDBExceptionTest extends goDBTestBase {

    /**
     * Ошибка подключения - пользователь не тот
     *
     * @expectedException     goDBExceptionConnect
     * @expectedExceptionCode 1045
     */
    public function testExceptionConnectUser() {
        $config = $this->config;
        $config['username'] = $this->usernameUnknown;
        $db = new goDB($config);
    }

    /**
     * Ошибка подключения - нет базы
     *
     * @expectedException     goDBExceptionConnect
     * @expectedExceptionCode 1044
     */
    public function testExceptionConnectDb() {
        $config = $this->config;
        $config['dbname'] = $this->dbUnknown;
        $db = new goDB($config);
    }

    /**
     * Ошибка при запросе - синтаксическая
     *
     * @expectedException     goDBExceptionQuery
     * @expectedExceptionCode 1064
     */
    public function testExceptionQuerySyntax() {
        try {
            $this->db()->query('SELECT * FORM `table`');
        } catch (goDBExceptionQuery $e) {
            $this->assertEquals('SELECT * FORM `table`', $e->query());
            $this->assertEquals(1064, $e->errno());
            throw $e;
        }
    }

    /**
     * Ошибка при запросе - нет таблицы
     *
     * @expectedException     goDBExceptionQuery
     * @expectedExceptionCode 1146
     */
    public function testExceptionQueryTable() {
        $this->db()->query('SELECT * FROM `table_unknown`');
    }

    /**
     * Не хватает данных для регулярных плейсхолдеров
     *
     * @expectedException goDBExceptionDataNotEnough
     */
    public function testExceptionDataNotEnoughRegular() {
        $pattern = 'INSERT INTO `godb` VALUES(?,?,?)';
        $data    = array(1, 2);
        $this->db()->query($pattern, $data);
    }

    /**
     * Нет данных для именованного плейсхолдера
     *
     * @expectedException goDBExceptionDataNotEnough
     */
    public function testExceptionDataNotEnoughNamed() {
        $pattern = 'INSERT INTO `godb` VALUES(?:a,?:b,?:c)';
        $data    = array('a' => 1, 'c' => 2, 'z' => 3);
        $this->db()->query($pattern, $data);
    }

    /**
     * Данных слишком много
     *
     * @expectedException goDBExceptionDataMuch
     */
    public function testExceptionDataMuch() {
        $pattern = 'INSERT INTO `godb` VALUES(?,?,?)';
        $data    = array(1, 2, 3, 4);
        $this->db()->query($pattern, $data);
    }

    /**
     * Смешанные плейсхолдеры
     *
     * @expectedException goDBExceptionDataMixed
     */
    public function testExceptionDataMixed() {
        $pattern = 'INSERT INTO `godb` VALUES(?,?:a,?)';
        $data    = array(1, 2, 3);
        $this->db()->query($pattern, $data);
    }

    /**
     * Неизвестный плейсхолдер
     *
     * @expectedException goDBExceptionDataPlaceholder
     */
    public function testExceptionDataPlaceholderUnknown() {
        $pattern = 'INSERT INTO `godb` VALUES(?,?unknown,?)';
        $data    = array(1, 2, 3);
        $this->db()->query($pattern, $data);
    }

    /**
     * Именованный плейсхолдер без имени
     *
     * @expectedException goDBExceptionDataPlaceholder
     */
    public function testExceptionDataPlaceholderNamed() {
        $pattern = 'INSERT INTO `godb` VALUES(?:a,?i:,?:c)';
        $data    = array('a' => 1, 'b' => 2, 'c' => 3);
        $this->db()->query($pattern, $data);
    }

    /**
     * Неизвестный формат представления
     *
     * @expectedException goDBExceptionFetchUnknown
     */
    public function testExceptionFetchUnknown() {
        $pattern = 'SHOW TABLES';
        $this->db()->query($pattern, null, 'unknown');
    }

    /**
     * Неожданный формат представления
     *
     * @expectedException goDBExceptionFetchUnexpected
     */
    public function testExceptionFetchUnexpected() {
        $db = $this->db(true);
        $pattern = 'UPDATE `godb` SET `number`=1 WHERE `id`>100';
        $db->query($pattern, null, 'assoc');
    }

    /**
     * Место в пространстве имён занято
     * 
     * @expectedException goDBExceptionDBAlready
     */
    public function testExceptionDBAlready() {
        $db   = $this->db();
        $name = 'name-name';
        goDB::setDB($db, $name);
        goDB::setDB($db, $name);
    }

    /**
     * Нету базы в пространстве имён
     *
     * @expectedException goDBExceptionDBNotFound
     */
    public function testExceptionDBNotFound() {
        goDB::getDB('wtf');
    }

}