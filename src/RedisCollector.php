<?php

namespace DebugBar\DataCollector\Redis;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use DebugBar\DataCollector\TimeDataCollector;

/**
 * Collects data about Redis actions executed
 */
class RedisCollector extends DataCollector implements Renderable, AssetProvider
{
    protected $connections = array();

    protected $timeCollector;

    /**
     * @param TraceableRedis $pdo
     * @param TimeDataCollector $timeCollector
     */
    public function __construct(TraceableRedis $redis = null, TimeDataCollector $timeCollector = null)
    {
        $this->timeCollector = $timeCollector;
        if ($redis !== null) {
            $this->addConnection($redis, 'default');
        }
    }

    /**
     * Adds a new Redis instance to be collector
     *
     * @param TraceableRedis $pdo
     * @param string $name Optional connection name
     */
    public function addConnection(TraceableRedis $redis, $name = null)
    {
        if ($name === null) {
            $name = spl_object_hash($redis);
        }
        $this->connections[$name] = $redis;
    }

    /**
     * Returns Redis instances to be collected
     *
     * @return array
     */
    public function getConnections()
    {
        return $this->connections;
    }

    public function collect()
    {

        $data = array(
            'nb_statements' => 0,
            'nb_failed_statements' => 0,
            'accumulated_duration' => 0,
            'memory_usage' => 0,
            'peak_memory_usage' => 0,
            'statements' => array()
        );

        foreach ($this->connections as $name => $redis) {
            $redisdata = $this->collectRedis($redis, $this->timeCollector);

            $data['nb_statements'] += $redisdata['nb_statements'];
            $data['nb_failed_statements'] += $redisdata['nb_failed_statements'];
            $data['accumulated_duration'] += $redisdata['accumulated_duration'];
            $data['memory_usage'] += $redisdata['memory_usage'];
            $data['peak_memory_usage'] = max($data['peak_memory_usage'], $redisdata['peak_memory_usage']);
            $data['statements'] = array_merge($data['statements'],
                array_map(function ($s) use ($name) { $s['connection'] = $name; return $s; }, $redisdata['statements']));
        }

        $data['accumulated_duration_str'] = $this->getDataFormatter()->formatDuration($data['accumulated_duration']);
        $data['memory_usage_str'] = $this->getDataFormatter()->formatBytes($data['memory_usage']);
        $data['peak_memory_usage_str'] = $this->getDataFormatter()->formatBytes($data['peak_memory_usage']);

        return $data;
    }

    /**
     * Collects data from a single TraceableRedis instance
     *
     * @param TraceableRedis $pdo
     * @param TimeDataCollector $timeCollector
     * @return array
     */
    protected function collectRedis(TraceableRedis $redis, TimeDataCollector $timeCollector = null)
    {
        $stmts = array();
        foreach ($redis->getExecutedStatements() as $stmt) {
            $stmts[] = array(
                'method' => $stmt->getMethod(),
                'params' => (object) $stmt->getParameters(),
                'duration' => $stmt->getDuration(),
                'duration_str' => $this->getDataFormatter()->formatDuration($stmt->getDuration()),
                'memory' => $stmt->getMemoryUsage(),
                'memory_str' => $this->getDataFormatter()->formatBytes($stmt->getMemoryUsage()),
                'end_memory' => $stmt->getEndMemory(),
                'end_memory_str' => $this->getDataFormatter()->formatBytes($stmt->getEndMemory()),
                'is_success' => $stmt->isSuccess(),
                'error_code' => $stmt->getErrorCode(),
                'error_message' => $stmt->getErrorMessage()
            );
            if ($timeCollector !== null) {
                $timeCollector->addMeasure($stmt->getMethod(), $stmt->getStartTime(), $stmt->getEndTime());
            }
        }

        return array(
            'nb_statements' => count($stmts),
            'nb_failed_statements' => count($redis->getFailedExecutedStatements()),
            'accumulated_duration' => $redis->getAccumulatedStatementsDuration(),
            'accumulated_duration_str' => $this->getDataFormatter()->formatDuration($redis->getAccumulatedStatementsDuration()),
            'memory_usage' => $redis->getMemoryUsage(),
            'memory_usage_str' => $this->getDataFormatter()->formatBytes($redis->getPeakMemoryUsage()),
            'peak_memory_usage' => $redis->getPeakMemoryUsage(),
            'peak_memory_usage_str' => $this->getDataFormatter()->formatBytes($redis->getPeakMemoryUsage()),
            'statements' => $stmts
        );
    }

    public function getName()
    {
        return 'redis';
    }

    public function getWidgets()
    {
        return array(
            "redis" => array(
                "icon" => "inbox",
                "widget" => "PhpDebugBar.Widgets.SQLQueriesWidget",
                "map" => "redis",
                "default" => "[]"
            ),
            "redis:badge" => array(
                "map" => "redis.nb_statements",
                "default" => 0
            )
        );
    }

    public function getAssets()
    {
        return array(
            'css' => 'widgets/sqlqueries/widget.css',
            'js' => 'widgets/sqlqueries/widget.js'
        );
    }
}
