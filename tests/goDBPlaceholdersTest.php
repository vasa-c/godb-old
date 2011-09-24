<?php
/**
 * Юнит-тестирование goDB
 * Тестирование формирования запроса на основе шаблона с плейсхолдерами
 *
 * Тестирование ведётся сугубо через makeQuery(),
 * так что тестовые структуры в базе не создаются
 *
 * @package    goDB
 * @subpackage unittest
 * @version    1.2.2
 * @author     Григорьев Олег aka vasa_c
 * @link       http://pyha.ru/go/godb/unit/
 * @link       http://pyha.ru/go/godb/query/
 * @link       http://pyha.ru/go/godb/named/
 * @uses       PHPUnit
 */

require_once(dirname(__FILE__).'/base/goDBTestBase.php');

class goDBPlaceholdersTest extends goDBTestBase {

    /**
     * Строки: ?, ?string, ?n, ?null
     *
     * @covers       goDB::makeQuery
     * @dataProvider providerPlaceholderString
     */
    public function testPlaceholderString($pattern, $data, $query) {
        $result = $this->db()->makeQuery($pattern, $data);
        $this->assertEquals($query, $result);
    }
    public function providerPlaceholderString() {
        $data   = array('тест "кавычек"', null, 12);
        return array(
            array(
                'INSERT INTO `table` VALUES (?,?,?)',
                $data,
                'INSERT INTO `table` VALUES ("тест \"кавычек\"","","12")',
            ),
            array(
                'INSERT INTO `table` VALUES (?string,?,?string)',
                $data,
                'INSERT INTO `table` VALUES ("тест \"кавычек\"","","12")',
            ),
            array(
                'INSERT INTO `table` VALUES (?null,?n,?n)',
                $data,
                'INSERT INTO `table` VALUES ("тест \"кавычек\"",NULL,"12")',
            ),
        );
    }

    /**
     * Числа: ?i, ?int, ?in, ?int-null
     *
     * @covers       goDB::makeQuery
     * @dataProvider providerPlaceholderInt
     */
    public function testPlaceholderInt($pattern, $data, $query) {
        $result = $this->db()->makeQuery($pattern, $data);
        $this->assertEquals($query, $result);
    }
    public function providerPlaceholderInt() {
        $data = array(12, null, 'qwerty', '11qwe5');
        return array(
            array(
                'INSERT INTO `table` VALUES (?i,?i,?i,?i)',
                $data,
                'INSERT INTO `table` VALUES (12,0,0,11)',
            ),
            array(
                'INSERT INTO `table` VALUES (?int,?int,?int,?int)',
                $data,
                'INSERT INTO `table` VALUES (12,0,0,11)',
            ),
            array(
                'INSERT INTO `table` VALUES (?in,?int-null,?in,?in)',
                $data,
                'INSERT INTO `table` VALUES (12,NULL,0,11)',
            ),
        );
    }

    /**
     * BOOL: ?bool
     *
     * @covers       goDB::makeQuery
     * @dataProvider providerPlaceholderBool
     */
    public function testPlaceholderBool($pattern, $data, $query) {
        $result = $this->db()->makeQuery($pattern, $data);
        $this->assertEquals($query, $result);
    }
    public function providerPlaceholderBool() {
        return array(
            array(
                'INSERT INTO `table` VALUES (?,?i,?bool)',
                array('5', 5, 5),
                'INSERT INTO `table` VALUES ("5",5,"1")',
            ),
            array(
                'INSERT INTO `table` VALUES (?,?i,?bool)',
                array(false, false, false),
                'INSERT INTO `table` VALUES ("",0,"0")',
            ),
            array(
                'INSERT INTO `table` VALUES (?,?i,?bool)',
                array('qwe', 'qwe', 'qwe'),
                'INSERT INTO `table` VALUES ("qwe",0,"1")',
            ),
        );
    }

    /**
     * Список: ?l, ?list, ?li, ?list-int
     *
     * @covers       goDB::makeQuery
     * @dataProvider providerPlaceholderList
     */
    public function testPlaceholderList($pattern, $data, $query) {
        $result = $this->db()->makeQuery($pattern, $data);
        $this->assertEquals($query, $result);
    }
    public function providerPlaceholderList() {
        $list = array('стр"ока', 12, null, '11qwe5');
        $data = array($list);
        return array(
            array(
                'INSERT INTO `table` VALUES (?l)',
                $data,
                'INSERT INTO `table` VALUES ("стр\"ока","12",NULL,"11qwe5")',
            ),
            array(
                'INSERT INTO `table` VALUES (?list)',
                $data,
                'INSERT INTO `table` VALUES ("стр\"ока","12",NULL,"11qwe5")',
            ),
            array(
                'INSERT INTO `table` VALUES (?li)',
                $data,
                'INSERT INTO `table` VALUES (0,12,NULL,11)',
            ),
            array(
                'INSERT INTO `table` VALUES (?list-int)',
                $data,
                'INSERT INTO `table` VALUES (0,12,NULL,11)',
            ),
        );
    }

    /**
     * Раздел SET: ?s, ?set
     *
     * @covers       goDB::makeQuery
     * @dataProvider providerPlaceholderSet
     */
    public function testPlaceholderSet($pattern, $data, $query) {
        $result = $this->db()->makeQuery($pattern, $data);
        $this->assertEquals($query, $result);
    }
    public function providerPlaceholderSet() {
        $set = array(
            'name' => 'Вася',
            'quot' => '"',
            'ava'  => null,
        );
        $data = array($set, 10);
        return array(
            array(
                'UPDATE `users` SET ?s WHERE `id`=?i',
                $data,
                'UPDATE `users` SET `name`="Вася",`quot`="\"",`ava`=NULL WHERE `id`=10',
            ),
            array(
                'UPDATE `users` SET ?set WHERE `id`=?',
                $data,
                'UPDATE `users` SET `name`="Вася",`quot`="\"",`ava`=NULL WHERE `id`="10"',
            ),
        );
    }

    /**
     * Множественная вставка: ?values
     *
     * @covers   goDB::makeQuery
     * @dataProvider providerPlaceholderValues
     */
    public function testPlaceholderValues($pattern, $data, $query) {
        $result = $this->db()->makeQuery($pattern, $data);
        $this->assertEquals($query, $result);
    }
    public function providerPlaceholderValues() {
        $values = array(
            array(1, '"', 3),
            array(4, null, 5),
            array(7, 9, null),
        );
        $result = '("1","\"","3"),("4",NULL,"5"),("7","9",NULL)';
        $data   = array($values);
        return array(
            array(
                'INSERT INTO `table` VALUES ?v',
                $data,
                'INSERT INTO `table` VALUES '.$result,
            ),
            array(
                'INSERT INTO `table` VALUES ?values',
                $data,
                'INSERT INTO `table` VALUES '.$result,
            ),
        );
    }

    /**
     * Таблица: ?t, ?table + {}-форма (префикс здесь не тестируется)
     *
     * @covers       goDB::makeQuery
     * @dataProvider providerPlaceholderTable
     */
    public function testPlaceholderTable($pattern, $data, $query) {
        $result = $this->db()->makeQuery($pattern, $data);
        $this->assertEquals($query, $result);
    }
    public function providerPlaceholderTable() {
        return array(
            array(
                'SELECT * FROM ?t AS `table`',
                array('test'),
                'SELECT * FROM `test` AS `table`',
            ),
            array(
                'SELECT * FROM {test} AS `table`',
                null,
                'SELECT * FROM `test` AS `table`',
            ),
        );
    }

    /**
     * Столбцы: ?c, ?col (префикс здесь не тестируется)
     *
     * @covers       goDB::makeQuery
     * @dataProvider providerPlaceholderCol
     */
    public function testPlaceholderCol($pattern, $data, $query) {
        $result = $this->db()->makeQuery($pattern, $data);
        $this->assertEquals($query, $result);
    }
    public function providerPlaceholderCol() {
        return array(
            array(
                'SELECT ?c,?col;,?c FROM ?t LEFT JOIN ?t ON ?c=?c',
                array('x', 'y', 'z', 'one', 'two', array('two', 'a'), array('one', 'b')),
                'SELECT `x`,`y`,`z` FROM `one` LEFT JOIN `two` ON `two`.`a`=`one`.`b`',
            ),
        );
    }

    /**
     * Часть строки: ?e, ?escape
     *
     * @covers       goDB::makeQuery
     * @dataProvider providerPlaceholderEscape
     */
    public function testPlaceholderEscape($pattern, $data, $query) {
        $result = $this->db()->makeQuery($pattern, $data);
        $this->assertEquals($query, $result);
    }
    public function providerPlaceholderEscape() {
        return array(
            array(
                'SELECT * FROM `table` WHERE `str` LIKE "%a?e"',
                array('qwe'),
                'SELECT * FROM `table` WHERE `str` LIKE "%aqwe"',
            ),
            array(
                'SELECT * FROM `table` WHERE `str` LIKE "%a?e"',
                array('qw"e'),
                'SELECT * FROM `table` WHERE `str` LIKE "%aqw\"e"',
            ),
            array(
                'SELECT * FROM `table` WHERE `str` LIKE "%a?e"',
                array('qw"e%'),
                'SELECT * FROM `table` WHERE `str` LIKE "%aqw\"e%"',
            ),
        );
    }

    /**
     * Часть запроса: ?q, ?query
     *
     * @covers       goDB::makeQuery
     * @dataProvider providerPlaceholderQuery
     */
    public function testPlaceholderQuery($pattern, $data, $query) {
        $result = $this->db()->makeQuery($pattern, $data);
        $this->assertEquals($query, $result);
    }
    public function providerPlaceholderQuery() {
        return array(
            array(
                'SELECT * FROM `table` WHERE ?q AND `z`="??"',
                array('`one`=1 AND `two`="?"'),
                'SELECT * FROM `table` WHERE `one`=1 AND `two`="?" AND `z`="?"',
            ),
        );
    }

    /**
     * Именованные плейсхолдеры
     *
     * @covers       goDB::makeQuery
     * @dataProvider providerNamedPlaceholder
     */
    public function testNamedPlaceholder($pattern, $data, $query) {
        $result = $this->db()->makeQuery($pattern, $data);
        $this->assertEquals($query, $result);
    }
    public function providerNamedPlaceholder() {
        $user = array(
            'userId'   => 11,
            'name'     => 'Вася',
            'surname'  => 'Пупкин',
            'nickname' => '"vasa"',
            'ava'      => null,
            'profile' => array(
                'sex'    => 'male',
                'born'   => '1980-11-11',
                'status' => 'looser',
            ),
        );
        return array(
            array(
                'INSERT INTO `users` SET `name`=?:name,`surname`=?:surname,`nickname`=?:nickname',
                $user,
                'INSERT INTO `users` SET `name`="Вася",`surname`="Пупкин",`nickname`="\"vasa\""',
            ),
            array(
                'UPDATE `users` SET ?set:profile; WHERE `user_id`=?i:userId',
                $user,
                'UPDATE `users` SET `sex`="male",`born`="1980-11-11",`status`="looser" WHERE `user_id`=11',
            ),
            array(
                'SELECT * FROM `users` WHERE '.
                    '(`name`=?:name AND `surname`=?:surname) OR (`name`=?:name AND `nickname`=?:nickname)',
                $user,
                'SELECT * FROM `users` WHERE '.
                    '(`name`="Вася" AND `surname`="Пупкин") OR (`name`="Вася" AND `nickname`="\"vasa\"")',
            ),
        );
    }

}