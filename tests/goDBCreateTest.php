<?php
/**
 * Юнит-тестирование goDB
 * Тестирование создания объекта и подключения к базе данных
 *
 * Объект базы в этом тесте не создаётся в setUp(),
 * так как само создание объекта является объектом тестирования.
 * Создание и установка происходит в testCreate(),
 * а все остальные тесты должны зависить от этого.
 *
 *
 * @package    goDB
 * @subpackage unittest
 * @version    1.2.2
 * @author     Григорьев Олег aka vasa_c
 * @link       http://pyha.ru/go/godb/unit/
 * @link       http://pyha.ru/go/godb/connect/
 * @uses       PHPUnit
 */

require_once(dirname(__FILE__).'/base/goDBTestBase.php');

class goDBCreateTest extends goDBTestBase {

    public function setUp() {}
    public function tearDown() {}

    /**
     * Тестирование создания объекта
     *
     * @covers goDB::__construct
     */
    public function testCreate() {
        /* Простой конструктор */
        $c  = $this->configNormalize();
        $db = new goDB($c['host'], $c['username'], $c['passwd'], $c['dbname'], $c['port'], $c['socket']);
        $db->close();

        /* Конструктор с конфигурационным массивом */
        $db = new goDB($this->config);

        /* Установка тестового объекта для остальных тестов */
        $this->db = $db;
    }

    /**
     * Тестирование параметра "charset", конфигурационного массива.
     * Должен быть указан в $this->config
     *
     * @depends testCreate
     * @covers  goDB::__construct
     */
    public function testCharset() {
        if (isset($this->config['charset'])) {
            $charset = $this->db()->get_charset();
            $this->assertEquals($this->config['charset'], $charset->charset);
        }
    }

}