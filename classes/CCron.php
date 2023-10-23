<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 * 
 * TODO:
 * - implement progress reporting
 * - some logic to detect when execatly the last worker of a task existed
 * - add level of abstraction: WP layer
 * - workload limiter: add sleep to poll
 * 
 * Reasons of failure:
 * - Time limit
 * - memory limit
 * - fatal error/exception
 * - Server reload
 * 
 * Webservers work good on parallel things
 * 
 * Concurrent/parllalized multi request task runnter
 * 
 * CMTR
 * CMRTR
 * PMRTR
 * PMTR
 * TODO tests:
 * - test proper chaining after a php time limit reach
 * - test proper chaining after an exception/fatal error
 */

class WPFB_CCronLock
{

    private $key;
    private $timeout;

    /**
     *
     * @param type $key
     * @param type $timeout
     */
    public function __construct($key, $timeout)
    {
        $this->key = $key;
        $this->timeout = $timeout;
    }

    /**
     *
     * @global wpdb $wpdb
     *
     * @return bool
     */
    function acquire()
    {
        global $wpdb;

        $now = microtime();
        $lock_until = $now + round($this->timeout * 60 * 1000);
        $option_name = "_ccron_lock_$this->key";

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $wpdb->options "
                . "WHERE option_name = %s", $option_name
            )
        );

        if ($row) {
            if ($row->option_value >= $now) {
                return false; /* lock is alive */
            }

            /* refresh lock, expecting it to be as we found it */

            return $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $wpdb->options "
                    . "SET option_value = %d "
                    . "WHERE option_id = %d AND option_name = %s AND option_value = %d",
                    $lock_until, $row->option_id, $row->option_name,
                    $row->option_value
                )
            );
        }

        return !!$wpdb->query(
            $wpdb->prepare(
                "INSERT INTO $wpdb->options (option_name, option_value, autoload)"
                . "VALUES (%s, %d, 'no')", $option_name, $lock_until
            )
        );
    }

    function acquireBlocking()
    {
        while (!$this->acquire()) {
            usleep(1000 * 100);
        }
    }

    function isLocked()
    {
        global $wpdb;

        $now = microtime();
        $option_name = "_ccron_lock_$this->key";

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $wpdb->options "
                . "WHERE option_name = %s AND option_value", $option_name, $now
            )
        );
    }

}

/**
 * Description of Cron
 *
 * @author flap
 */
class WPFB_CCronTask
{

    const MAX_POLL_INTERVAL = 60;

    /**
     *
     * @var string
     */
    var $hook;

    /**
     *
     * @var array
     */
    var $args;

    /**
     *
     * @var int
     */
    var $num_workers;

    /**
     *
     * @var int
     */
    var $start_time;

    /**
     * @var boolean
     */
    var $debug;

    /**
     *
     * @param string $hook
     * @param array $args
     * @param int $num_workers
     */
    public function __construct($hook, $args = array(), $num_workers = 1)
    {
        $this->hook = $hook;
        $this->args = $args;
        $this->num_workers = $num_workers;
        $this->start_time = microtime(true);
        $this->debug = (defined('WP_DEBUG') && WP_DEBUG);
    }

    /**
     *
     * @return string
     */
    function getKey()
    {
        return $this->hook . md5(serialize($this->args));
    }

    /**
     *
     * @return array
     */
    function getArgs()
    {
        return $this->args;
    }

    /**
     *
     * @return int
     */
    function getNumWorkers()
    {
        return $this->num_workers;
    }

    function poll()
    {
        WPFB_CCron::setTransient(
            "ccron_alive_{$this->getKey()}", microtime(true),
            self::MAX_POLL_INTERVAL * 2
        );
    }

    function __toString()
    {
        return "{$this->hook}(" . json_encode($this->args) . ")";
    }

}

class WPFB_CCronWorker
{

    /**
     *
     * @var WPFB_CCronTask
     */
    private $task;

    /**
     *
     * @var int
     */
    private $id;

    /**
     *
     * @var int
     */
    private $chain_index;

    /**
     *
     * @var int
     */
    private $exception_counter;

    /**
     *
     * @var int
     */
    private $chain_state;


    /**
     * @var array
     */
    var $debug_backtrace;

    /**
     *
     * @param WPFB_CCronTask $task
     * @param int $id
     */
    function __construct($task, $id)
    {
        $this->task = $task;
        $this->id = $id;
        $this->chain_index = 0;
        $this->exception_counter = 0;
        $this->chain_state = 0;
    }

    /**
     *
     * @return array
     */
    function serialize()
    {
        return array(
            'ccron_task' => $this->task->getKey(),
            'wrki' => $this->id,
            'chain' => $this->chain_index,
            'excc' => $this->exception_counter
        );
    }

    /**
     *
     * @param WPFB_CCronTask $task
     * @param array $args
     *
     * @return WPFB_CCronWorker|null
     */
    static function unserialize($task, &$args)
    {
        $worker_id = isset($args['wrki']) ? (0 + $args['wrki']) : -1;
        if ($worker_id < 0 || $worker_id >= $task->getNumWorkers()) {
            return null;
        }
        $wrk = new WPFB_CCronWorker($task, $worker_id);
        $wrk->chain_index = empty($args['chain']) ? 0
            : (0 + $args['chain']);
        $wrk->exception_counter = empty($args['excc']) ? 0
            : (0 + $args['excc']);

        return $wrk;
    }

    /**
     *
     * @return string
     */
    function getTaskKey()
    {
        return $this->task->getKey();
    }

    function getActionHook()
    {
        return $this->task->hook;
    }

    function getActionArgs()
    {
        return array_merge(array($this), $this->task->getArgs());
    }

    /**
     *
     * @return int
     */
    function getId()
    {
        return $this->id;
    }

    /**
     *
     * @return bool
     */
    function getChain()
    {
        return 1 === $this->chain_state;
    }

    /**
     *
     * @return bool
     */
    function getIgnoreExit()
    {
        return 2 === $this->chain_state;
    }

    /**
     *
     * @return int
     */
    function getChainIndex()
    {
        return $this->chain_index;
    }

    /**
     *
     * @return int
     */
    function getExceptionCounter()
    {
        return $this->exception_counter;
    }

    function poll()
    {
        if ($this->task->debug)
            $this->debug_backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3);

        $this->task->poll();
    }

    function maybeBackOff()
    {
        $this->poll();
        $delay = 0;
        if ($this->exception_counter > 0) {
            /* exponential backoff with cap */
            $delay = min(
                pow(2, $this->exception_counter / 2),
                WPFB_CCronTask::MAX_POLL_INTERVAL - 1
            );
        } elseif ($this->chain_index > 0) {
            $delay = 1;
        }

        if ($delay) {
            /* add jitter [75%-125%] */
            $delay *= (0.75 + mt_rand() / mt_getrandmax() / 2);
            usleep($delay * 1000000);
            $this->poll();
        }
    }

    function ignoreExit()
    {
        $this->chain_state = 2;
    }

    function chain()
    {
        $this->chain_state = 1;
    }

    /**
     *
     * @return WPFB_CCronWorker
     */
    function _createNext($recover_from_exception = false)
    {
        $next = clone $this;
        $recover_from_exception ? ($next->exception_counter++)
            : ($next->chain_index++ && $next->exception_counter = 0);

        return $next;
    }

    function __toString()
    {
        return "{$this->task}:$this->id@$this->chain_index"
        . ($this->exception_counter ? "E$this->exception_counter" : "");
    }

}

class WPFB_CCron
{

    const TASK_TRANSIENT_EXPIRATION = 28800; // 8h
    const WORKER_REQUEST_TIME_LIMIT = 1800; // 30min

    static function TaskStart($hook, $num_workers = 4, $args = array())
    {
        $tasks = self::getTransient('wpfb_ccron_tasks');
        if (empty($tasks)) {
            $tasks = array();
        }

        $new_task = new WPFB_CCronTask(
            $hook, $args, $num_workers
        );
        $tasks[$new_task->getKey()] = $new_task;
        WPFB_CCron::setTransient(
            'wpfb_ccron_tasks', $tasks, self::TASK_TRANSIENT_EXPIRATION
        );

        $new_task->poll();

        self::logMsg("NewTask", $new_task, "x$num_workers");
        for ($i = 0; $i < $num_workers; $i++) {
            self::spawnWorker(new WPFB_CCronWorker($new_task, $i));
        }
    }

    public static function getTransient($key)
    {
        global $_wp_using_ext_object_cache;
        global $wp_object_cache;
        $_wp_using_ext_object_cache = false;
        if($wp_object_cache) $wp_object_cache->flush();
        return get_transient($key);
    }

    public static function setTransient($transient, $value, $expiration = 0)
    {
        global $_wp_using_ext_object_cache;
        global $wp_object_cache;
        $_wp_using_ext_object_cache = false;
        if($wp_object_cache) $wp_object_cache->flush();
        return set_transient($transient, $value, $expiration);
    }

    static function TaskIsRunning($hook, $flush_cache = false, $args = array())
    {
        if ($flush_cache) {
            wp_cache_flush();
        }

        $task = new WPFB_CCronTask($hook, $args);
        $key = $task->getKey();
        $tasks = self::getTransient('wpfb_ccron_tasks');
        $alive = self::getTransient('ccron_alive_' . $key);
        $exists = isset($tasks[$key]);

        if ($exists && !$alive) {
            //unset($tasks[$task_key]); dont remove!
            WPFB_CCron::setTransient(
                'wpfb_ccron_tasks', $tasks, self::TASK_TRANSIENT_EXPIRATION
            );

            return false;
        }

        return $alive;
    }

    /**
     *
     * @return \WPFB_CCronWorker
     */
    private static function getWorker(&$args)
    {
        $task_key = $args['ccron_task'];

        /* @var $tasks WPFB_CCronTask[] */
        $tasks = self::getTransient('wpfb_ccron_tasks');

        if (empty($tasks) || !isset($tasks[$task_key])) {
            return null;
        }

        /* @var $task WPFB_CCronTask */
        $task = $tasks[$task_key];

        return WPFB_CCronWorker::unserialize($task, $args);
    }

    static function doCron(&$args)
    {
        if (!defined('DOING_CRON') || empty($args['ccron_task'])) {
            return;
        }

        if (!($worker = self::getWorker($args))) {
            return;
        }


        /* always backOff on chained requests, to get things unlocked from previous request */
        $worker->maybeBackOff();

        self::logMsg(
            ($worker->getChainIndex() == 0) ? "Started taskworker"
                : "Continued taskworker", $worker
        );

        register_shutdown_function(array(__CLASS__, 'onShutdownPHP'), $worker);
        add_filter('wp_die_handler', function() {
            return array(__CLASS__, 'onWPDie');
        });

        if (!ini_get('safe_mode')) {
            set_time_limit(self::WORKER_REQUEST_TIME_LIMIT);
        }

        do_action_ref_array($worker->getActionHook(), $worker->getActionArgs());

        if ($worker->getChain()) {
            self::logMsg("Chaining", $worker);
            $worker->poll();
            self::spawnWorker($worker->_createNext());
        } else {
            self::logMsg("Completed", $worker);
        }

        $worker->ignoreExit();

        exit;
    }

    static function onWPDie($message, $title, $args)
    {
        self::logMsg("ERROR: wp_die: $message, $title", json_encode($args));
        exit;
    }

    /**
     *
     * @param WPFB_CCronWorker $worker
     */
    static function onShutdownPHP($worker)
    {
        //new WPFB_CCronLock($worker->getTaskKey(), 10)->acquireBlocking();
        //$worker->get_transient('ccron_alive_' . $key);

        if (!$worker->getIgnoreExit()) {
            global $php_errormsg;
            $err = error_get_last();
            self::logMsg(
                "Unexpected shutdown ", $worker, " last error: '$php_errormsg' ", "out:", ob_get_clean(), 'err:',
                json_encode($err) //, 'lastpoll:' , str_replace("\n",'<br>',print_r($worker->debug_backtrace, true))
            );
            if ($worker->getExceptionCounter() >= 10) {
                self::logMsg("Too many exceptions", $worker, "QUIT");
            } else {
                self::logMsg("Re-spawning", $worker);
                self::spawnWorker($worker->_createNext(true));
            }
        }
    }

    /**
     *
     * @param WPFB_CCronWorker $worker
     */
    private static function spawnWorker($worker)
    {
        $get_args = $worker->serialize();

        if (isset($_REQUEST['XDEBUG_PROFILE'])) {
            $get_args['XDEBUG_PROFILE'] = '';
        }

        $cron_request = apply_filters(
            'cron_request', array(
                'url' => add_query_arg($get_args, site_url('wp-cron.php')),
                'key' => $worker->getTaskKey(),
                'args' => array(
                    'timeout' => 0.01,
                    'blocking' => false,
                    /** This filter is documented in wp-includes/class-wp-http-streams.php */
                    'sslverify' => apply_filters(
                        'https_local_ssl_verify', false
                    )
                )
            )
        );
        wp_remote_post($cron_request['url'], $cron_request['args']);
    }

    private static $logfile = false;

    private static function logMsg()
    {
        if (!self::$logfile) {
            return;
        }
        $t = current_time('mysql');
        $args = func_get_args();
        // json_encode((array)$worker, true)
        $msg = implode(' ', array_map('strval', $args));

        return error_log("[$t] $msg\n", 3, self::$logfile);
    }

    public static function SetLogFile($file)
    {
        self::$logfile = $file;
    }

}

/*
 * 		/* we need an atomic operation that
		 * - checks if lock key exists, if not create one
		 * - check if found lock is alive, if not update it
		 * here it is:
		 * NOTE: actually its not required to be atomic, since the option_name is unique and INSERT will fail
		 *
 * 		return $wpdb->query( $wpdb->prepare(""
				  . "INSERT INTO $wpdb->options (option_name, option_value, autoload) "
				  . "SELECT * FROM (SELECT %s, %d, 'no') AS tmp"
				  . "WHERE NOT EXISTS ("
				  . "	SELECT GET_LOCK( option_name FROM $wpdb->options WHERE option_name = %s AND option_value > %d"
				  . ") LIMIT 1", $lock_key, $lock_until, $lock_key, $now));
 */