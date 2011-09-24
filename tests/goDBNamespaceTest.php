<?php
/**
 * Юнит-тестирование goDB
 * Тестирование пространства имён внутри библиотеки
 *
 * @package    goDB
 * @subpackage unittest
 * @version    1.2.2
 * @author     Григорьев Олег aka vasa_c
 * @link       http://pyha.ru/go/godb/unit/
 * @link       http://pyha.ru/go/godb/namespace/
 * @uses       PHPUnit
 */

require_once(dirname(__FILE__).'/base/goDBTestBase.php');

class goDBNamespaceTest extends goDBTestBase {

    /**
     * @covers goDB::setDB
     * @covers goDB::getDB
     */
    public function testSetDB() {
        $db   = $this->db();
        $name = 'setget-test';
        goDB::setDB($db, $name);
        $this->assertSame($db, goDB::getDB($name));
    }

    /**
     * @covers goDB::makeDB
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
     *
     * @depends testMakeDB
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
     *
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

}