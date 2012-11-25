<?php namespace ParaTest\Logging;

abstract class MetaProvider
{
    protected static $totalMethod = '/^getTotal([\w]+)$/';
    protected static $messageMethod = '/^get((Failure|Error)s)$/';

    /**
     * Simplify aggregation of totals or messages
     */
    public function __call($method, $args)
    {
        if(preg_match(self::$totalMethod, $method, $matches) && $property = strtolower($matches[1]))
            return $this->getNumericValue($property);
        if(preg_match(self::$messageMethod, $method, $matches) && $type = strtolower($matches[1]))
            return $this->getMessages($type);
    }

    protected function getNumericValue($property)
    {
       return ($property === 'time') 
              ? floatval($this->suites[0]->$property)
              : intval($this->suites[0]->$property);
    }

    protected function getMessages($type)
    {
        $messages = array();
        $suites = $this->isSingle ? $this->suites : $this->suites[0]->suites;
        foreach($suites as $suite)
            $messages = array_merge($messages, array_reduce($suite->cases, function($result, $case) use($type) {
                return array_merge($result, array_reduce($case->$type, function($msgs, $msg) { 
                    $msgs[] = $msg['text'];
                    return $msgs;
                }, array()));
            }, array()));
        return $messages;
    }

}