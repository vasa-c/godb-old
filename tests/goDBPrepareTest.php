<?php
/**
 * Юнит-тестирование goDB
 * Тестирование подготовленных выражений
 *
 * @package    goDB
 * @subpackage unittest
 * @version    1.3.0
 * @author     Григорьев Олег aka vasa_c
 * @link       http://pyha.ru/go/godb/unit/
 * @link       http://pyha.ru/go/godb/prepare/
 * @uses       PHPUnit
 */

require_once(dirname(__FILE__).'/base/goDBTestBase.php');

class goDBPrepareTest extends goDBTestBase {

    /**
     * Проверка совместимости с mysqli::prepare
     *
     * @covers prepare
     */
    public function testPrepareMysqli() {

        $db = $this->db(true);

        $prepare = $db->prepare('SELECT * FROM `godb` WHERE `id`=?');
        $this->assertInstanceOf('mysqli_stmt', $prepare);
        $this->assertEquals(1, $prepare->param_count);
        $prepare->close();

        $prepare = $db->prepare('SELECT * FORM `godb` WHERE `id`=?'); // Error "FORM"
        $this->assertFalse($prepare);
        $this->assertEquals(1064, $db->errno);
    }

    /**
     * Простая проверка prepare
     *
     * @covers prepare
     */
    public function testPrepareSimple() {

        $db = $this->db(true);

        $prepare = $db->prepare('SELECT `number`,`caption` FROM `godb` WHERE `id`=?', true);
        $this->assertInstanceOf('goDBPrepare', $prepare);

        $r1 = $prepare->execute(array(1), 'el');
        $r3 = $prepare->execute(array(3), 'el');
        $this->assertEquals(1, $r1);
        $this->assertEquals(5, $r3);
        $prepare->close();
    }

    /**
     * Плейсхолдеры с типами
     *
     * @covers prepare
     * @covers goDBPrepare::getTypes
     */
    public function testTypes() {
        $db = $this->db(true);
        $pattern = 'INSERT INTO `godb` SET `id`=?i,`number`=?i;,`caption`=?';
        $query   = 'INSERT INTO `godb` SET `id`=?,`number`=?,`caption`=?';
        $types   = 'iis';
        $prepare = $db->prepare($pattern, true);
        $this->assertEquals('iis', $prepare->getTypes());
        $this->assertEquals($query, $prepare->getQuery());
        $prepare->close();
    }

    /**
     * @covers goDBPrepare::getSTMT
     */
    public function testGetSTMT() {
        $db = $this->db(true);
        $prepare = $db->prepare('INSERT INTO `godb` SET `id`=?i,`number`=?i;,`caption`=?', true);
        $stmt    = $prepare->getSTMT();
        $this->assertInstanceOf('mysqli_stmt', $stmt);
        $this->assertEquals(3, $stmt->param_count);
        $prepare->close();
    }

    /**
     * @covers goDBPrepare::close
     */
    public function testClose() {
        $db = $this->db(true);

        $prepare = $db->prepare('SELECT `caption` FROM `godb` WHERE `id`=?', true);
        $this->assertFalse($prepare->isClosed());
        $this->assertEquals('one', $prepare->execute(array(1), 'el'));

        $prepare->close();
        $this->assertTrue($prepare->isClosed());
        $this->assertFalse($prepare->getSTMT());
        $this->assertFalse($prepare->execute(array(1), 'el'));
    }

    /**
     * @covers goDBPrepare::execute
     */
    public function testEmptyParams() {
        $db = $this->db(true);

        $prepare = $db->prepare('SELECT COUNT(*) FROM `godb`', true);
        $this->assertTrue($prepare->execute());
        $this->assertEquals(7, $prepare->execute(null, 'el'));
    }

    /**
     * @covers goDB::prepareExecute
     * @covers goDB::getPrepare
     */
    public function testPrepareExecute() {
        $db = $this->db(true);

        $count1 = $db->query('SELECT COUNT(*) FROM `godb`');
        $query  = 'INSERT INTO `godb` SET `number`=?i';

        $this->assertFalse($db->getPrepare($query));
        $id1 = $db->prepareExecute($query, array(1), 'id');
        $this->assertEquals(8, $id1);
        $prepare = $db->getPrepare($query);
        $id2 = $db->prepareExecute($query, array(1), 'id');
        $this->assertEquals(9, $id2);
        $this->assertSame($prepare, $db->getPrepare($query));
    }

    /**
     * @covers goDB::prepareExecute
     */
    public function testPrepareExecuteNamed() {

        $db = $this->db(true);

        $count1 = $db->query('SELECT COUNT(*) FROM `godb`');
        $query  = 'INSERT INTO `godb` SET `number`=?i';

        $this->assertFalse($db->getPrepare($query));
        $id1 = $db->prepareExecute($query, array(1), 'id', '#insert');
        $this->assertEquals(8, $id1);
        $prepare = $db->getPrepare('#insert');
        $id2 = $db->prepareExecute('insert', array(1), 'id');
        $this->assertEquals(9, $id2);
        $this->assertSame($prepare, $db->getPrepare($query));
    }

    /**
     * @covers goDB::prepareNamed
     */
    public function testPrepareNamed() {
        $db = $this->db(true);

        $name    = 'insert';
        $query   = 'INSERT INTO `godb` SET `number`=?i';
        $prepare = $db->prepareNamed($name, $query);

        $this->assertSame($prepare, $db->getPrepare($name));
        $this->assertSame($prepare, $db->getPrepare($query));
    }

    /**
     * Ошибка при создании выражения
     *
     * @covers prepare
     * @expectedException goDBExceptionPrepareCreate
     */
    public function testErrorPrepare() {
        $db = $this->db();
        $prepare = $db->prepare('SELECT FORM `unknown`', true);
    }

    /**
     * Ошибка: неверное количество данных
     */
    public function testErrorData() {

        $db = $this->db();

        $prepare = $db->prepare('SELECT 1', true);

        $this->assertEquals(1, $prepare->execute(array(), 'el'));
        
        $this->setExpectedException('goDBExceptionDataMuch');
        $prepare->execute(array(1, 2, 3), 'el');
    }

    /**
     * Ошибка: формат разбора ни к месту
     *
     * @expectedException goDBExceptionFetchUnexpected
     */
    public function testErrorFetch() {
        $db = $this->db();

        $prepare = $db->prepare('INSERT INTO `godb` SET `number`=?i', true);

        $prepare->execute(array(2), 'el');
    }

    /**
     * @expectedException goDBExceptionPrepareNamed
     */
    public function testErrorNamed() {
        $db = $this->db();

        $this->assertFalse($db->getPrepare('#unknown', false));
        $db->getPrepare('#unknown', true);
    }

    /**
     * @expectedException goDBExceptionPrepareCreate
     */
    public function testErrorNamed2() {
        $db = $this->db();

        $this->assertFalse($db->getPrepare('unknown', false));
        $db->getPrepare('unknown', true);
    }

    /**
     * Исключения и отложенное создание
     */
    public function testErrorLazy() {
        $db = $this->db();

        $prepare = $db->prepareNamed('err', 'errrror');

        $this->setExpectedException('goDBExceptionPrepareCreate');
        $stmt = $prepare->getSTMT();
    }

    /**
     * Разбор результата
     */
    public function testFetch() {
        $db = $this->db(true);

        /* SELECT 2 ROWS */
        $pattern = 'SELECT `id`,`number` FROM `godb` WHERE `id` IN (1,3) ORDER BY `id` DESC';
        $prepare = $db->prepare($pattern, true);

        $expectedAssoc = array(
            array('id' => 3, 'number' => 5),
            array('id' => 1, 'number' => 1),
        );
        $this->assertEquals($expectedAssoc, $prepare->execute(null, 'assoc'));
        $this->assertEquals($expectedAssoc, $prepare->execute(null, 'iassoc'));

        $expectedRow = array(
            array(3, 5),
            array(1, 1),
        );
        $this->assertEquals($expectedRow, $prepare->execute(null, 'row'));
        $this->assertEquals($expectedRow, $prepare->execute(null, 'irow'));

        $expectedCol = array(3, 1);
        $this->assertEquals($expectedCol, $prepare->execute(null, 'col'));
        $this->assertEquals($expectedCol, $prepare->execute(null, 'icol'));

        $expectedKassoc = array(
            3 => array('id' => 3, 'number' => 5),
            1 => array('id' => 1, 'number' => 1),
        );
        $this->assertEquals($expectedKassoc, $prepare->execute(null, 'kassoc'));

        $expectedKassocN = array(
            5 => array('id' => 3, 'number' => 5),
            1 => array('id' => 1, 'number' => 1),
        );
        $this->assertEquals($expectedKassocN, $prepare->execute(null, 'kassoc:number'));

        $expectedObject = array(
            (object)array('id' => 3, 'number' => 5),
            (object)array('id' => 1, 'number' => 1),
        );
        $this->assertEquals($expectedObject, $prepare->execute(null, 'object'));

        $expectedVars = array(
            3 => 5,
            1 => 1,
        );
        $this->assertEquals($expectedVars, $prepare->execute(null, 'vars'));

        try {
            $prepare->execute(null, 'no');
            $this->fail('expected goDBExceptionFetchUnexpected');
        } catch (goDBExceptionFetchUnexpected $e) {}

        $expectedNum = 2;
        $this->assertEquals($expectedNum, $prepare->execute(null, 'num'));
        
        $expectedRowassoc = array('id' => 3, 'number' => 5);
        $this->assertEquals($expectedRowassoc, $prepare->execute(null, 'rowassoc'));

        $expectedRowrow = array(3, 5);
        $this->assertEquals($expectedRowrow, $prepare->execute(null, 'rowrow'));

        $expectedRowobject = (object)array('id' => 3, 'number' => 5);
        $this->assertEquals($expectedRowobject, $prepare->execute(null, 'rowobject'));

        $expectedEl = 3;
        $this->assertEquals($expectedEl, $prepare->execute(null, 'el'));

        $expectedBool = true;
        $this->assertEquals($expectedBool, $prepare->execute(null, 'bool'));

        $prepare->close();

        /* SELECT EMPTY */
        $pattern = 'SELECT `id`,`number` FROM `godb` WHERE `id`>100';
        $prepare = $db->prepare($pattern, true);

        $this->assertFalse($prepare->execute(null, 'rowassoc'));
        $this->assertFalse($prepare->execute(null, 'rowobject'));
        $this->assertFalse($prepare->execute(null, 'rowrow'));
        $this->assertFalse($prepare->execute(null, 'el'));
        $this->assertFalse($prepare->execute(null, 'bool'));

        $prepare->close();

        /* LAST INSERT ID */
        $pattern = 'INSERT INTO `godb` SET `number`=?i';
        $prepare = $db->prepare($pattern, true);

        $this->assertEquals(8, $prepare->execute(array(1), 'id'), null);
        $this->assertEquals(9, $prepare->execute(array(2), 'id'), null);
        
        $prepare->close();

        /* AFFECTED ROWS */
        $pattern = 'DELETE FROM `godb` WHERE `number`=?i';
        $prepare = $db->prepare($pattern, true);

        $this->assertEquals(2, $prepare->execute(array(1), 'ar'));
        $this->assertEquals(0, $prepare->execute(array(1), 'ar'));

        $prepare->close();
    }

    public function testMany() {
        $db = $this->db(true);

        $db->query('TRUNCATE `godb`');

        $prepare = $db->prepare('INSERT INTO `godb` VALUES (NULL, ?, ?)', true);

        $prepare->execute(array(2, 'two'));
        $prepare->execute(array(4, 'four'));
        $prepare->execute(array(6, 'six'));

        $expected = array(
            array(1, 2, 'two'),
            array(2, 4, 'four'),
            array(3, 6, 'six'),
        );
        $result = $db->query('SELECT * FROM `godb` ORDER BY `id` ASC', null, 'row');
        $this->assertEquals($expected, $result);
    }

}