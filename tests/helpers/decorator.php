<?php
/**
 * Хелпер-декоратор
 *
 * Заменяет все вхождения подстроки $from в запросе на $to,
 * а запросы с $deny вообще отменяет.
 */

class goDBTestHelperDecorator {

    public function __construct($from, $to, $deny) {
        $this->from = $from;
        $this->to   = $to;
        $this->deny = $deny;
    }

    public function wrapper($query) {
        if (strpos($query, $this->deny) !== false) {
            return false;
        }
        $query = str_replace($this->from, $this->to, $query);
        return $query;
    }

    public function getCallback() {
        return array($this, 'wrapper');
    }

    private $from, $to, $deny;
}