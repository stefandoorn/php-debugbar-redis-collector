<?php

namespace RedisCollector;

use Redis;
use RedisException;

/**
 * A PDO proxy which traces statements
 */
class TraceableRedis
{

    private $redis;
    private $executedStatements = array();

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    public function __call($name, array $args)
    {
        return $this->profileCall($name, $args);
    }

    public function __get($name)
    {
        return $this->redis->$name;
    }

    public function __set($name, $value)
    {
        $this->redis->$name = $value;
    }

    /**
     * Profiles a call on a Redis method
     *
     * @param string $method
     * @param string $sql
     * @param array $args
     * @return mixed The result of the call
     */
    protected function profileCall($method, array $args)
    {
        $trace = new TracedStatement($method, $args);
        $trace->start();

        $ex = null;
        try {
            $result = call_user_func_array(array($this->redis, $method), $args);
        } catch (RedisException $e) {
            $ex = $e;
        }

        $trace->end($ex);
        $this->addExecutedStatement($trace);

        return $result;
    }

    /**
     * Adds an executed TracedStatement
     *
     * @param TracedStatement $stmt
     */
    public function addExecutedStatement(TracedStatement $stmt)
    {
        array_push($this->executedStatements, $stmt);
    }

    /**
     * Returns the list of executed statements as TracedStatement objects
     *
     * @return array
     */
    public function getExecutedStatements()
    {
        return $this->executedStatements;
    }

    /**
     * Returns the list of failed statements
     *
     * @return array
     */
    public function getFailedExecutedStatements()
    {
        return array_filter($this->executedStatements, function ($s) { return !$s->isSuccess(); });
    }

    /**
     * Returns the accumulated execution time of statements
     *
     * @return int
     */
    public function getAccumulatedStatementsDuration()
    {
        return array_reduce($this->executedStatements, function ($v, $s) { return $v + $s->getDuration(); });
    }

    /**
     * Returns the peak memory usage while performing statements
     *
     * @return int
     */
    public function getMemoryUsage()
    {
        return array_reduce($this->executedStatements, function ($v, $s) { return $v + $s->getMemoryUsage(); });
    }

    /**
     * Returns the peak memory usage while performing statements
     *
     * @return int
     */
    public function getPeakMemoryUsage()
    {
        return array_reduce($this->executedStatements, function ($v, $s) { $m = $s->getEndMemory(); return $m > $v ? $m : $v; });
    }

}
