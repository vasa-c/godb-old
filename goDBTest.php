<?php
/**
 * Тестирование goDB (PHPUnit)
 *
 * @author  Григорьев Олег aka vasa_c (http://blgo.ru/blog/)
 * @version 1.2.1
 * @uses    goDB (http://pyha.ru/go/godb/)
 * @uses    PHPUnit (http://phpunit.de/)
 */

require(dirname(__FILE__).'/godb.php');

class goDBTest extends PHPUnit_Framework_TestCase {

    /**
     * Параметры тестовой базы
     *
     * @var array
     */
    private $config = array(
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
    private $usernameUnknown = 'user_unknown';

    /**
     * Несуществующее название базы
     * Для тестирования ошибки подключения
     *
     * @var string
     */
    private $dbUnknown = 'db_unknown';

    /**
     * Тестирование создания объекта и подключения к базе
     * Как с помощью порядковых аргументов конструктора, так и конфигурационного массива
     * Здесь же создаётся объект self::$db, используемый в остальных тестах
     * 
     * @group  create
     * @covers goDB::__construct
     */
    public function testCreate() {
        $c  = $this->configNormalize();
        $db = new goDB($c['host'], $c['username'], $c['passwd'], $c['dbname'], $c['port'], $c['socket']);
        $db->close();

        self::$db = new goDB($this->config);
    }

    /**
     * Тестирование параметра "charset", конфигурационного массива
     *
     * @depends testCreate
     * @group   create
     * @covers  goDB::__construct
     */
    public function testCreateCharset() {
        if (isset($this->config['charset'])) {
            $charset = $this->db()->get_charset();
            $this->assertEquals($this->config['charset'], $charset->charset);
        }
    }

    /*** Плейсхолдеры ***/

    /**
     * Строки: ?, ?string, ?n, ?null
     * @depends  testCreate
     * @group    placeholders
     * @dataProvider providerPlaceholderString
     * @covers   goDB::makeQuery
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
     * @depends  testCreate
     * @group    placeholders
     * @dataProvider providerPlaceholderInt
     * @covers   goDB::makeQuery
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
     * Список: ?l, ?list, ?li, ?list-int
     * @depends  testCreate
     * @group    placeholders
     * @dataProvider providerPlaceholderList
     * @covers   goDB::makeQuery
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
     * @depends  testCreate
     * @group    placeholders
     * @dataProvider providerPlaceholderSet
     * @covers   goDB::makeQuery
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
     * @depends  testCreate
     * @group    placeholders
     * @dataProvider providerPlaceholderValues
     * @covers   goDB::makeQuery
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
     * @depends testCreate
     * @group   placeholders
     * @dataProvider providerPlaceholderTable
     * @covers  goDB::makeQuery
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
     * @depends testCreate
     * @group   placeholders
     * @dataProvider providerPlaceholderCol
     * @covers  goDB::makeQuery
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
     * @depends testCreate
     * @group   placeholders
     * @dataProvider providerPlaceholderEscape
     * @covers  goDB::makeQuery
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
     * @depends testCreate
     * @group   placeholders
     * @dataProvider providerPlaceholderQuery
     * @covers  goDB::makeQuery
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
     * @depends testCreate
     * @group   placeholders
     * @dataProvider providerNamedPlaceholder
     * @covers  goDB::makeQuery
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

    /*** Разбор результата ***/

    /**
     * @depends testCreate
     * @group   fetch
     * @covers  goDB::fetch
     */
    public function testFetchNo() {
        $result = $this->db()->query('SELECT 1', null, 'no');
        $this->assertInstanceOf('mysqli_result', $result);
    }

    /**
     * @depends testCreate
     * @group   fetch
     * @covers  goDB::fetch
     */
    public function testFetchAssoc() {
        $db = $this->db(true);
        $pattern = 'SELECT * FROM `godb` ORDER BY `id` ASC LIMIT 3';
        $result  = $this->db()->query($pattern, null, 'assoc');
        $this->assertType('array', $result);
        $this->assertEquals(3, count($result));
        $this->assertArrayHasKey(1, $result);
        $row = $result[1];
        $this->assertArrayHasKey('caption', $row);
        $this->assertEquals('three', $row['caption']);
    }

    /**
     * @depends testCreate
     * @group   fetch
     * @covers  goDB::fetch
     */
    public function testFetchRow() {
        $db = $this->db(true);
        $pattern = 'SELECT `number`,`caption`,`id` FROM `godb` ORDER BY `id` ASC LIMIT 2';
        $result  = $this->db()->query($pattern, null, 'row');
        $this->assertType('array', $result);
        $this->assertEquals(2, count($result));
        $this->assertArrayHasKey(1, $result);
        $row = $result[1];
        $this->assertArrayHasKey(1, $row);
        $this->assertEquals('three', $row[1]);
    }

    /**
     * @depends testCreate
     * @group   fetch
     * @covers  goDB::fetch
     */
    public function testFetchObject() {
        $db = $this->db(true);
        $pattern = 'SELECT `number`,`caption`,`id` FROM `godb` ORDER BY `id` ASC LIMIT 4';
        $result  = $this->db()->query($pattern, null, 'object');
        $this->assertType('array', $result);
        $this->assertEquals(4, count($result));
        $this->assertArrayHasKey(2, $result);
        $row = $result[2];
        $this->assertType('object', $row);
        $this->assertAttributeEquals('five', 'caption', $row);
    }

    /**
     * @depends testCreate
     * @group   fetch
     * @covers  goDB::fetch
     */
    public function testFetchOCol() {
        $db = $this->db(true);
        $pattern  = 'SELECT `number` FROM `godb` ORDER BY `id` ASC LIMIT ?i,?i';
        $expected = array(5, 4);
        $result   = $this->db()->query($pattern, array(2,2), 'col');
        $this->assertEquals($expected, $result);
    }

    /**
     * @depends testCreate
     * @group   fetch
     * @covers  goDBResult
     */
    public function testFetchIterators() {
        $db = $this->db(true);
        $pattern  = 'SELECT `number`,`caption` FROM `godb` LIMIT 5';
        $result   = $db->query($pattern);
        $fassoc   = $db->fetch($result, 'assoc');
        $fiassoc  = $db->fetch($result, 'iassoc');
        $firow    = $db->fetch($result, 'irow');
        $ficol    = $db->fetch($result, 'icol');
        $fiobject = $db->fetch($result, 'iobject');

        $this->assertType('array', $fassoc);
        $this->assertInstanceOf('goDBResultAssoc', $fiassoc);
        $this->assertInstanceOf('goDBResultRow', $firow);
        $this->assertInstanceOf('goDBResultCol', $ficol);
        $this->assertInstanceOf('goDBResultObject', $fiobject);

        $this->assertEquals(5, count($fassoc));
        $this->assertEquals(5, count($fiassoc));
        $this->assertEquals(5, count($firow));
        $this->assertEquals(5, count($ficol));
        $this->assertEquals(5, count($fiobject));

        $r = 0;
        foreach ($fiassoc as $row) {$r += $row['number'];}
        $this->assertEquals(16, $r);
        $r = 0;
        foreach ($firow as $row) {$r += $row[0];}
        $this->assertEquals(16, $r);
        $r = 0;
        foreach ($ficol as $row) {$r += $row;}
        $this->assertEquals(16, $r);
        $r = 0;
        foreach ($fiobject as $row) {$r += $row->number;}
        $this->assertEquals(16, $r);

        $this->assertEquals($fassoc[2], $fiassoc[2]);
        $this->assertEmpty($fiassoc[20]);
        $this->assertTrue(isset($fiassoc[2]));
        $this->assertFalse(isset($fiassoc[20]));
    }

    /**
     * @depends testCreate
     * @group   fetch
     * @covers  goDB::fetch
     */
    public function testFetchVars() {
        $db = $this->db(true);
        $pattern = 'SELECT `caption`,`number` FROM `godb` WHERE `caption` IS NOT NULL';
        $result  = $db->query($pattern, null, 'vars');
        $this->assertType('array', $result);
        $this->assertArrayHasKey('one', $result);
        $this->assertArrayHasKey('three', $result);
        $this->assertArrayHasKey('double', $result);
        $this->assertEquals(1, $result['one']);
        $this->assertEquals(3, $result['three']);
        $this->assertEquals(3, $result['double']);
    }

    /**
     * @depends testCreate
     * @group   fetch
     * @covers  goDB::fetch
     */
    public function testFetchRowAssoc() {
        $db      = $this->db(true);
        $pattern = 'SELECT `number`,`caption` FROM `godb` WHERE `id`=?i';
        $result  = $db->query($pattern, array(5), 'rowassoc');
        $expected = array('number' => 3, 'caption' => 'double');
        $this->assertEquals($expected, $result);
        $result  = $db->query($pattern, array(55), 'rowassoc');
        $this->assertEmpty($result);
    }

    /**
     * @depends testCreate
     * @group   fetch
     * @covers  goDB::fetch
     */
    public function testFetchRowRow() {
        $db      = $this->db(true);
        $pattern = 'SELECT `number`,`caption` FROM `godb` WHERE `id`=?i';
        $result  = $db->query($pattern, array(5), 'rowrow');
        $expected = array(3, 'double');
        $this->assertEquals($expected, $result);
        $result  = $db->query($pattern, array(55), 'rowrow');
        $this->assertEmpty($result);
    }

    /**
     * @depends testCreate
     * @group   fetch
     * @covers  goDB::fetch
     */
    public function testFetchRowObject() {
        $db      = $this->db(true);
        $pattern = 'SELECT `number`,`caption` FROM `godb` WHERE `id`=?i';
        $result  = $db->query($pattern, array(5), 'rowobject');
        $this->assertEquals(3, $result->number);
        $result  = $db->query($pattern, array(55), 'rowobject');
        $this->assertEmpty($result);
    }

    /**
     * @depends testCreate
     * @group   fetch
     * @covers  goDB::fetch
     */
    public function testFetchEl() {
        $db      = $this->db(true);
        $pattern = 'SELECT `number` FROM `godb` WHERE `id`=?i';
        $result  = $db->query($pattern, array(5), 'el');
        $this->assertEquals(3, $result);
        $result  = $db->query($pattern, array(55), 'el');
        $this->assertEmpty($result);
    }

    /**
     * @depends testCreate
     * @group   fetch
     * @covers  goDB::fetch
     */
    public function testFetchNum() {
        $db      = $this->db(true);
        $pattern = 'SELECT `number` FROM `godb` LIMIT 2';
        $result  = $db->query($pattern, array(5), 'num');
        $this->assertEquals(2, $result);
    }

    /**
     * @depends testCreate
     * @group   fetch
     * @covers  goDB::fetch
     */
    public function testFetchId() {
        $db      = $this->db(true);
        $pattern = 'INSERT INTO `godb` SET `number`=?i,`caption`=?n';
        $result  = $db->query($pattern, array(5, null), 'id');
        $this->assertEquals(8, $result);
        $pattern = 'INSERT INTO `godb` SET `id`=?i,`number`=?i,`caption`=?n';
        $result  = $db->query($pattern, array(15, 6, 'q'), 'id');
        $this->assertEquals(15, $result);

        $pattern = 'SELECT `number` FROM `godb` WHERE `id`>=8 ORDER BY `id` ASC';
        $result  = $db->query($pattern, null, 'col');
        $this->assertEquals(array(5, 6), $result);

        self::$testTableUpdated = true;
    }

    /**
     * @depends testCreate
     * @group   fetch
     * @covers  goDB::fetch
     */
    public function testFetchAr() {
        $db      = $this->db(true);
        $pattern = 'UPDATE `godb` SET `number`=`number`+1 WHERE `caption` IS NULL';
        $result  = $db->query($pattern, null, 'ar');
        $this->assertEquals(2, $result);

        $pattern = 'DELETE FROM `godb` WHERE `caption` IS NULL';
        $result  = $db->query($pattern, null, 'ar');
        $this->assertEquals(2, $result);

        $pattern = 'UPDATE `godb` SET `number`=`number`+1 WHERE `caption` IS NULL';
        $result  = $db->query($pattern, null, 'ar');
        $this->assertEquals(0, $result);

        $this->assertEquals(5, $db->countRows('godb'));

        self::$testTableUpdated = true;
    }



    /*** Пространства имён ***/

    /**
     * @depends testCreate
     * @group   namespace
     * @covers  goDB::setDB
     * @covers  goDB::getDB
     */
    public function testDBSetGet() {
        $db   = $this->db();
        $name = 'setget-test';
        goDB::setDB($db, $name);
        $this->assertSame($db, goDB::getDB($name));
    }

    /**
     * @depends testCreate
     * @group   namespace
     * @covers  goDB::makeDB
     */
    public function testMakeDB() {
        $name   = 'make-test';
        $config = $this->config;
        $config['name'] = $name;
        $db = goDB::makeDB($config);
        $this->assertInstanceOf('goDB', $db);
        $this->assertSame($db, goDB::getDB($name));
        $this->assertNotSame($db, $this->db());
        $db->close();
    }

    /**
     * assocDB + "link"
     * @depends testMakeDB
     * @group   namespace
     * @covers  goDB::makeDB
     * @covers  goDB::assocDB
     */
    public function testAssocDB() {
        $name1  = 'make-test';
        $name2  = 'link-test';
        $name3  = 'assoc-test';
        $config = array(
            'name' => $name2,
            'link' => $name1,
        );
        goDB::makeDB($config);
        goDB::assocDB($name3, $name2);
        $db1 = goDB::getDB($name1);
        $db2 = goDB::getDB($name2);
        $db3 = goDB::getDB($name3);
        $this->assertInstanceOf('goDB', $db1);
        $this->assertSame($db1, $db2);
        $this->assertSame($db2, $db3);
        $this->assertNotSame($db1, $this->db());
    }

    /**
     * Отложенное подключение с ошибкой
     * @depends testMakeDB
     * @group   namespace
     * @covers  goDB::makeDB
     */
    public function testPostConnect() {
        $name   = 'post-connect';
        $config = $this->config;
        $config['username'] = $this->usernameUnknown;
        $config['name']     = $name;
        $config['postmake'] = true;
        goDB::makeDB($config);
        $this->setExpectedException('goDBExceptionConnect');
        $db = goDB::getDB($name);
    }

    /*** Дополнительные функции ***/

    /**
     * Префикс таблиц
     * @depends testCreate
     * @covers  goDB::setPrefix
     */
    public function testPrefix() {
        $db = $this->db();

        $pattern = 'SELECT * FROM `one` LEFT JOIN {two} ON `one`.`a`=?t.?c WHERE ?c=?i';
        $data    = array('two', 'a', array('two', 'b'), 10);

        $query   = 'SELECT * FROM `one` LEFT JOIN `two` ON `one`.`a`=`two`.`a` WHERE `two`.`b`=10';
        $result  = $db->makeQuery($pattern, $data);
        $this->assertEquals($query, $result);

        $db->setPrefix('t_');
        $query   = 'SELECT * FROM `one` LEFT JOIN `t_two` ON `one`.`a`=`t_two`.`a` WHERE `t_two`.`b`=10';
        $result  = $db->makeQuery($pattern, $data);
        $this->assertEquals($query, $result);

        $db->setPrefix(null);
    }

    /**
     * Подсчёт количества запросов
     * @depends testCreate
     * @covers  goDB::getQQuery
     */
    public function testQQuery() {
        $prev = goDB::getQQuery();
        $this->db()->query('SHOW TABLES');
        $current = goDB::getQQuery();
        $this->assertEquals($current, $prev + 1);
    }

    /**
     * Отладочная информация
     * @depends testCreate
     * @covers  goDB::setDebug
     */
    public function testDebug() {
        $db = $this->db();
        $this->debugQuery = null;
        $db->query('SHOW TABLES');
        $this->assertNull($this->debugQuery);
        $db->setDebug(array($this, 'callbackDebug'));
        $db->query('SHOW DATABASES');
        $db->setDebug(false);
        $this->assertEquals('SHOW DATABASES', $this->debugQuery);
        $this->debugQuery = null;
        $db->query('SHOW TABLES');
        $this->assertNull($this->debugQuery);
    }
    public function callbackDebug($query) {
        $this->debugQuery = $query;
    }
    private $debugQuery;

    /**
     * Тестирование декоратора
     * @depends testCreate
     * @covers  goDB::queryDecorated
     */
    public function testDecorator() {
        $db = $this->db(true);

        $r2 = $db->query('SELECT `id` FROM `godb` LIMIT 2', null, 'num');
        $r3 = $db->query('SELECT `id` FROM `godb` LIMIT 3', null, 'num');
        $r4 = $db->query('SELECT `id` FROM `godb` LIMIT 4', null, 'num');
        $this->assertEquals(2, $r2);
        $this->assertEquals(3, $r3);
        $this->assertEquals(4, $r4);

        $db->queryDecorated(array($this, 'wrapper'));
        $r2 = $db->query('SELECT `id` FROM `godb` LIMIT 2', null, 'num');
        $r3 = $db->query('SELECT `id` FROM `godb` LIMIT 3', null, 'num');
        $r4 = $db->query('SELECT `id` FROM `godb` LIMIT 4', null, 'num');
        $this->assertEquals(2, $r2);
        $this->assertEquals(5, $r3);
        $this->assertEmpty($r4);

        $db->queryDecorated(null);
        $r2 = $db->query('SELECT `id` FROM `godb` LIMIT 2', null, 'num');
        $r3 = $db->query('SELECT `id` FROM `godb` LIMIT 3', null, 'num');
        $r4 = $db->query('SELECT `id` FROM `godb` LIMIT 4', null, 'num');
        $this->assertEquals(2, $r2);
        $this->assertEquals(3, $r3);
        $this->assertEquals(4, $r4);
    }
    /**
     * Декоратор запроса
     * Если в запросе есть "LIMIT 3" - заменяет на "LIMIT 5"
     * Если в запросе есть "LIMIT 4" - отменяет запрос
     * @param string $query
     */
    public function wrapper($query) {
        if (strpos($query, 'LIMIT 4')) {
            return false;
        }
        return str_replace('LIMIT 3', 'LIMIT 5', $query);
    }

    /*** Исключения ***/

    /**
     * Ошибка подключения - пользователь не тот
     * @depends testCreate
     * @groups  exceptions
     * @expectedException goDBExceptionConnect
     * @expectedExceptionCode 1045
     */
    public function testExceptionConnectUser() {
        $config = $this->config;
        $config['username'] = $this->usernameUnknown;
        $db = new goDB($config);
    }

    /**
     * Ошибка подключения - нет базы
     * @depends testCreate
     * @groups  exceptions
     * @expectedException goDBExceptionConnect
     * @expectedExceptionCode 1044
     */
    public function testExceptionConnectDb() {
        $config = $this->config;
        $config['dbname'] = $this->dbUnknown;
        $db = new goDB($config);
    }

    /**
     * Ошибка при запросе - синтаксическая
     * @depends testCreate
     * @groups  exceptions
     * @expectedException goDBExceptionQuery
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
     * @depends testCreate
     * @groups  exceptions
     * @expectedException goDBExceptionQuery
     * @expectedExceptionCode 1146
     */
    public function testExceptionQueryTable() {
        $this->db()->query('SELECT * FROM `table_unknown`');
    }

    /**
     * Не хватает данных для регулярных плейсхолдеров
     * @depends testCreate
     * @groups  exceptions
     * @expectedException goDBExceptionDataNotEnough
     * @group exceptions
     */
    public function testExceptionDataNotEnoughRegular() {
        $pattern = 'INSERT INTO `godb` VALUES(?,?,?)';
        $data    = array(1, 2);
        $this->db()->query($pattern, $data);
    }

    /**
     * Нет данных для именованного плейсхолдера
     * @depends testCreate
     * @groups  exceptions
     * @expectedException goDBExceptionDataNotEnough
     * @group exceptions
     */
    public function testExceptionDataNotEnoughNamed() {
        $pattern = 'INSERT INTO `godb` VALUES(?:a,?:b,?:c)';
        $data    = array('a' => 1, 'c' => 2, 'z' => 3);
        $this->db()->query($pattern, $data);
    }

    /**
     * Данных слишком много
     * @depends testCreate
     * @groups  exceptions
     * @expectedException goDBExceptionDataMuch
     * @group exceptions
     */
    public function testExceptionDataMuch() {
        $pattern = 'INSERT INTO `godb` VALUES(?,?,?)';
        $data    = array(1, 2, 3, 4);
        $this->db()->query($pattern, $data);
    }

    /**
     * Смешанные плейсхолдеры
     * @depends testCreate
     * @groups  exceptions
     * @expectedException goDBExceptionDataMixed
     * @group exceptions
     */
    public function testExceptionDataMixed() {
        $pattern = 'INSERT INTO `godb` VALUES(?,?:a,?)';
        $data    = array(1, 2, 3);
        $this->db()->query($pattern, $data);
    }

    /**
     * Неизвестный плейсхолдер
     * @depends testCreate
     * @groups  exceptions
     * @expectedException goDBExceptionDataPlaceholder
     * @group exceptions
     */
    public function testExceptionDataPlaceholder() {
        $pattern = 'INSERT INTO `godb` VALUES(?,?unknown,?)';
        $data    = array(1, 2, 3);
        $this->db()->query($pattern, $data);
    }

    /**
     * Неизвестный формат разбора
     * @depends testCreate
     * @groups  exceptions
     * @expectedException goDBExceptionFetch
     * @group exceptions
     */
    public function testExceptionFetch() {
        $pattern = 'SHOW TABLES';
        $this->db()->query($pattern, null, 'unknown');
    }
    
    /**
     * Место в пространстве имён занято
     * @depends testCreate
     * @groups  exceptions
     * @expectedException goDBExceptionDBAlready
     * @group exceptions
     */
    public function testExceptionDBAlready() {
        $db   = $this->db();
        $name = 'name-name';
        goDB::setDB($db, $name);
        goDB::setDB($db, $name);
    }

    /**
     * Нету базы в пространстве имён
     * @depends testCreate
     * @groups  exceptions
     * @expectedException goDBExceptionDBNotFound
     * @group exceptions
     */
    public function testExceptionDBNotFound() {
        goDB::getDB('wtf');
    }

    /**
     * "Деструктор" - закрываем подключение
     */
    public static function tearDownAfterClass() {
        if (self::$db) {
            self::$db->query('DROP TABLE IF EXISTS `godb`');
            self::$db->close();
        }
    }

    /**
     * Получение тестового объекта goDB
     *
     * @param bool $fill [optional]
     *        заодно и заполнить
     * @return goDB
     */
    private function db($fill = false) {
        if ($fill) {
            $this->fillDB();
        }
        return self::$db;
    }

    /**
     * Заполнить базу тестовыми данными
     */
    private function fillDB() {
        if (!self::$testTableCreated) {
            self::$db->query('DROP TABLE IF EXISTS ?t', array($this->testTable));
            self::$db->query('CREATE TABLE ?t (?q) ENGINE=Memory', array($this->testTable, $this->testTableStruct));
            $insert = true;
            self::$testTableCreated = true;
        } elseif (self::$testTableUpdated) {
            self::$db->query('TRUNCATE TABLE `godb`');
            $insert = true;
            self::$testTableUpdated = false;
        } else {
            $insert = false;
        }
        if ($insert) {
            self::$db->query(
                'INSERT INTO ?t (`number`,`caption`) VALUES ?v',
                array($this->testTable, $this->testTableValues)
            );
        }
    }

    /**
     * Заполнение конфига отсутствующими полями
     *
     * @return array
     */
    private function configNormalize() {
        $config = $this->config;
        $result = array();
        $fields = array('host', 'username', 'passwd', 'dbname', 'port', 'socket');
        foreach ($fields as $field) {
            $result[$field] = isset($config[$field]) ? $config[$field] : null;
        }
        return $result;
    }

    /**
     * Название таблицы для тестов
     *
     * @var string
     */
    private $testTable = 'godb';

    /**
     * Структура столбцов таблицы для тестов
     *
     * @var string
     */
    private $testTableStruct = '
        `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `number`  TINYINT NOT NULL,
        `caption` VARCHAR(30) NULL DEFAULT NULL,
        PRIMARY KEY(`id`)
    ';

    /**
     * Начальные значения таблицы для тестов
     *
     * @var string
     */
    private $testTableValues = array(
        array(1, 'one'),
        array(3, 'three'),
        array(5, 'five'),
        array(4, null),
        array(3, 'double'),
        array(8, 'eight'),
        array(9, null),
    );

    /**
     * Была ли уже создана таблица для тестов
     *
     * @var bool
     */
    private static $testTableCreated = false;

    /**
     * Была ли тестовая таблица обновлена с последнего заполнения
     *
     * @var bool
     */
    private static $testTableUpdated = false;

    /**
     * Тестовый объект goDB
     * @var goDB
     */
    private static $db;
}