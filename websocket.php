<?php
require_once 'db.php';
use Swoole\WebSocket;

class WebsocketServer
{
    private $host = "0.0.0.0";

    private $port = "9500";
    /**
     * [private description]
     * @var Swoole\Table
     */
    private $table;

    /**
     * [private description]
     * @var Swoole\WebSocket\Server
     */
    private $server;

    private $config = [
        'task_worker_num' => 4,
        'task_ipc_mode'   => 3,
        'message_queue_key'=> ""
    ];
    /**
     * [protected description]
     * @var Swoole\Lock
     */
    protected $lock;

    function __construct()
    {
        $this->server = new WebSocket\Server($this->host, $this->port);
        $this->lock = new Swoole\Lock(SWOOLE_MUTEX);
        echo swoole_get_local_ip()['ens33'].":".$this->port."\n";

        $this->setConfig();
        $this->onInit();
        $this->tableInit();
    }

    public function open($server, $req)
    {
        $this->table->set($req->get['http_id'], ['fd' => $req->fd]);
    }
    public function message($server, $frame)
    {
        $db = new Db();
        // 1 解析数据
        /*
        [
           "http_id"
        ]
         */
        $data = json_decode($frame->data, true);
        // 2. 获取任务量
        $count = $db->query("select count(*) as count from mobile");
        // 3. 任务分块
        $avg = $count[0]['count'] / 4;
        // 4. 记录任务
        $this->table->set("task", ['fd' => $count[0]['count']]); // 总任务量
        $this->table->set("task_count", ['fd' => 0]);// 当前完成的量
        // 5. 分发任务
        for ($i=0; $i < $this->config['task_worker_num']; $i++) {
            $task_id = $server->task([
                "fid"  => ($this->table->get($data['http_id']))['fd'],
                "data" => ($avg * $i),
                "avg"  => $avg
            ], $i);
        }

        $server->push($frame->fd, json_encode(["msg" => "正在处理"]));
    }

    public function task($server, $task_id, $reactor_id, $data)
    {
        $db = new Db();
        // var_dump('select * from mobile limit '.$data['data'].','.$data['avg']);
        $mobile = $db->query('select count(*) from mobile limit '.$data['data'].','.$data['avg']);
        // 发送短信....跳过
        // sleep(3);
        // 记录完成的任务


        $this->lock->lock(); // 加锁避免多进程不安全问题
        $task_count = ($this->table->get("task_count"))['fd'];
        var_dump("当前的处理量".$task_count);
        $this->table->set("task_count", ['fd' => ($task_count + $data['avg'])]);
        $this->lock->unlock();


        // 通知
        $server->finish($data);
    }
    public function onfinish($server, $task_id, $data)
    {
        $task_count = ($this->table->get("task_count"))['fd'];
        $task = ($this->table->get("task"))['fd'];

        if ($task_count == $task && $task_count > 0) {
            $server->push($data['fid'], json_encode(["msg" => $task_count."个任务，处理完成"]));

            // 清空任务
            $this->table->del("task_count");
            $this->table->del("task");
        } else {
            var_dump("task 进程 --- 处理的任务量".$task_count);
        }

    }

    protected function tableInit()
    {
        $this->table = new Swoole\Table(1 * 1024 * 1024);
        $this->table->column('fd', Swoole\Table::TYPE_INT);
        $this->table->create();
    }

    protected function onInit()
    {
        // [$this, 'open'] 把对象方法转为闭包参数传递
        $this->server->on('open', [$this, 'open']);
        $this->server->on('message', [$this, 'message']);
        $this->server->on('close', [$this, 'close']);
        $this->server->on('task', [$this, 'task']);
        $this->server->on('finish', [$this, 'onfinish']);
    }

    protected function setConfig()
    {
        $msg_key = ftok(__DIR__,'u');
        $this->config['message_queue_key'] = $msg_key;
        $this->server->set($this->config);
    }

    public function close($server, $fd){}

    public function start()
    {
        $this->server->start();
    }

}

(new WebSocketServer)->start();


//
