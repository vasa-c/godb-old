<?php
/**
 * Юнит-тестирование goDB
 * Тестирование разбора результата в соответствии с указанным форматом
 *
 * @package    goDB
 * @subpackage unittest
 * @version    1.2.2
 * @author     Григорьев Олег aka vasa_c
 * @link       http://pyha.ru/go/godb/fetch/
 * @link       http://pyha.ru/go/godb/unit/
 * @uses       PHPUnit
 */

require_once(dirname(__FILE__).'/base/goDBTestBase.php');

class goDBFetchTest extends goDBTestBase {

    /**
     * Возвращение mysqli_result без разбора
     *
     * @group   fetch
     * @covers  goDB::fetch
     */
    public function testNo() {
        $result = $this->db()->query('SELECT 1', null, 'no');
        $this->assertInstanceOf('mysqli_result', $result);
    }

    /**
     * assoc: порядковый массив ассоциативных массивов
     *
     * @covers goDB::fetch
     */
    public function testAssoc() {
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
     * row: порядковый массив порядковых массивов
     *
     * @covers goDB::fetch
     */
    public function testRow() {
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
     * object: порядкового массив объектов
     *
     * @group fetch
     */
    public function testObject() {
        $db = $this->db(true);
        $pattern = 'SELECT `number`,`caption`,`id` FROM `godb` ORDER BY `id` ASC LIMIT 4';
        $result  = $db->query($pattern, null, 'object');
        $this->assertType('array', $result);
        $this->assertEquals(4, count($result));
        $this->assertArrayHasKey(2, $result);
        $row = $result[2];
        $this->assertType('object', $row);
        $this->assertAttributeEquals('five', 'caption', $row);
    }

    /**
     * col: порядковый массив заначений столбца
     *
     * @group fetch
     */
    public function testCol() {
        $db = $this->db(true);
        $pattern  = 'SELECT `number` FROM `godb` ORDER BY `id` ASC LIMIT ?i,?i';
        $expected = array(5, 4);
        $result   = $this->db()->query($pattern, array(2,2), 'col');
        $this->assertEquals($expected, $result);
    }

    /**
     * kassoc: ассоциативный массив "столбец" => array(строка)
     *
     * @group fetch
     */
    public function testKAssoc() {
        $db = $this->db(true);

        $pattern  = 'SELECT `id`,`number`,`caption` FROM `godb` ORDER BY `id` ASC LIMIT 2,3';
        $result   = $db->query($pattern, null, 'kassoc');
        $expected = array(
            '3' => array('id' => '3', 'number' => '5', 'caption' => 'five'),
            '4' => array('id' => '4', 'number' => '4', 'caption' => null),
            '5' => array('id' => '5', 'number' => '3', 'caption' => 'double'),
        );
        $this->assertEquals($expected, $result);

        $pattern  = 'SELECT `id`,`number`,`caption` FROM `godb` ORDER BY `id` ASC LIMIT 2,3';
        $result   = $db->query($pattern, null, 'kassoc:number');
        $expected = array(
            '5' => array('id' => '3', 'number' => '5', 'caption' => 'five'),
            '4' => array('id' => '4', 'number' => '4', 'caption' => null),
            '3' => array('id' => '5', 'number' => '3', 'caption' => 'double'),
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Тестирование итераторов в качестве возвращаемого значения
     *
     * @covers goDBResult
     */
    public function testIterators() {
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
     * Очищать результат, используемый в итераторе нельзя
     *
     * @covers query
     */
    public function testIteratorFree() {
        $db     = $this->db(true);
        $summ   = 0;
        $result = $db->query('SELECT `number` FROM `godb` LIMIT 5', null, 'icol');        
        foreach ($result as $number) {
            $summ += $number;
        }
        $this->assertEquals(16, $summ);
    }

    /**
     * vars: "переменная" => "значение"
     *
     * @covers goDB::fetch
     */
    public function testVars() {
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

        $pattern = 'SELECT `caption` FROM `godb` WHERE `caption` IS NOT NULL';
        $result  = $db->query($pattern, null, 'vars');
        $this->assertType('array', $result);
        $this->assertArrayHasKey('one', $result);
        $this->assertArrayHasKey('three', $result);
        $this->assertArrayHasKey('double', $result);
        $this->assertEquals('one', $result['one']);
        $this->assertEquals('three', $result['three']);
        $this->assertEquals('double', $result['double']);

    }

    /**
     * rowassoc: одна запись в виде ассоциативного массива
     *
     * @covers goDB::fetch
     */
    public function testRowAssoc() {
        $db      = $this->db(true);
        $pattern = 'SELECT `number`,`caption` FROM `godb` WHERE `id`=?i';
        $result  = $db->query($pattern, array(5), 'rowassoc');
        $expected = array('number' => 3, 'caption' => 'double');
        $this->assertEquals($expected, $result);
        $result  = $db->query($pattern, array(55), 'rowassoc');
        $this->assertEmpty($result);
    }

    /**
     * rowrow: одна запись в виде порядкового массива
     * 
     * @covers goDB::fetch
     */
    public function testRowRow() {
        $db      = $this->db(true);
        $pattern = 'SELECT `number`,`caption` FROM `godb` WHERE `id`=?i';
        $result  = $db->query($pattern, array(5), 'rowrow');
        $expected = array(3, 'double');
        $this->assertEquals($expected, $result);
        $result  = $db->query($pattern, array(55), 'rowrow');
        $this->assertEmpty($result);
    }


    /**
     * rowobject: одна запись в виде объекта
     *
     * @covers goDB::fetch
     */
    public function testRowObject() {
        $db      = $this->db(true);
        $pattern = 'SELECT `number`,`caption` FROM `godb` WHERE `id`=?i';
        $result  = $db->query($pattern, array(5), 'rowobject');
        $this->assertEquals(3, $result->number);
        $result  = $db->query($pattern, array(55), 'rowobject');
        $this->assertEmpty($result);
    }

    /**
     * el: одно значение
     *
     * @covers goDB::fetch
     */
    public function testEl() {
        $db      = $this->db(true);
        $pattern = 'SELECT `number` FROM `godb` WHERE `id`=?i';
        $result  = $db->query($pattern, array(5), 'el');
        $this->assertEquals(3, $result);
        $result  = $db->query($pattern, array(55), 'el');
        $this->assertEmpty($result);
    }

    /**
     * bool: одно значение (BOOL)
     *
     * @covers goDB::fetch
     */
    public function testBool() {
        $db      = $this->db(true);
        $pattern = 'SELECT `number` FROM `godb` WHERE `id`=?i';
        $result  = $db->query($pattern, array(5), 'bool');
        $this->assertTrue($result);
        $result  = $db->query($pattern, array(55), 'bool');
        $this->assertEmpty($result);
    }

    /**
     * num: количество полученных записей
     *
     * @covers goDB::fetch
     */
    public function testNum() {
        $db      = $this->db(true);
        $pattern = 'SELECT `number` FROM `godb` LIMIT 2';
        $result  = $db->query($pattern, array(5), 'num');
        $this->assertEquals(2, $result);
    }

    /**
     * id: последний авто-инкремент
     *
     * @covers goDB::fetch
     */
    public function testId() {
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
    }

    /**
     * ar: количество затронутых запросом записей
     *
     * @covers goDB::fetch
     */
    public function testAr() {
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
    }

}
