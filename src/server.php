<?php
header("Content-Type: text/html;charset=utf-8");
ini_set('memory_limit', '500M');
date_default_timezone_set("PRC");
set_time_limit(0);
error_reporting(0);

require_once(__DIR__ . '/server.php');
require_once(__DIR__ . '/user.php');

$server = new Server('0.0.0.0', '8888');
$server->run();

class Server
{
    protected $host;
    protected $port;
    protected $maxBufferSize;
    protected $master;
    protected $sockets = [];
    protected $users = [];
    protected $socketUsers = [];

    public function __construct($host, $port, $bufferLength = 2048)
    {
        $this->host = $host;
        $this->port = $port;
        $this->maxBufferSize = $bufferLength;
        $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("Failed: socket_create()");
        socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");
        socket_bind($this->master, $this->host, $this->port) or die("Failed: socket_bind()");
        socket_listen($this->master, 20) or die("Failed: socket_listen()");
        $this->sockets[] = $this->master;
    }

    /**
     * 服务启动
     */
    public function run()
    {
        $this->stdout('socket listen ' . $this->host . ':' . $this->port);
        exec('open ../login.html');
        while (true) {
            $read = $this->sockets;
            $write = $except = null;
            socket_select($read, $write, $except, null);

            foreach ($read as $socket) {
                if ($socket == $this->master) {
                    $client = socket_accept($socket);
                    if ($client < 0) {
                        $this->stderr("Failed: socket_accept()");
                        continue;
                    } else {
                        $this->connect($client);
                        $this->stdout("Client connected. " . $client);
                    }
                } else {
                    $numBytes = socket_recv($socket, $buffer, $this->maxBufferSize, 0);
                    if ($numBytes === false) {
                        $this->stderr('Socket error: ' . socket_strerror(socket_last_error($socket)));
                    } elseif ($numBytes == 0) {
                        $this->disconnect($socket);
                        $this->stdout("Client disconnected. TCP connection lost: " . $socket);
                    } else {
                        $user = $this->getUserBySocket($socket);
                        $message = $this->unmask($buffer);
                        $this->handleMessage($user, $message);
                    }
                }
            }
        }
    }

    /**
     * 建立连接
     *
     * @param $client
     */
    protected function connect($client)
    {
        $user = new User(uniqid('u'), $client);
        $this->users[$user->id] = $user;
        $this->sockets[$user->id] = $client;
        $this->socketUsers[$client] = $user->id;

        //通过socket获取数据执行handshake
        $header = socket_read($client, 1024);
        $this->performHandshaking($header, $client, $this->host, $this->port);

        $res = [
            'command' => 'connect',
            'body'    => [
                'user_id' => $user->id
            ]
        ];
        $this->send($user, $res);
    }

    /**
     * 删除连接
     *
     * @param $client
     */
    protected function disconnect($client)
    {
        $index = array_search($client, $this->sockets);
        unset($this->sockets[$index], $this->users[$index], $this->socketUsers[$client]);
    }

    /**
     * 获取用户信息
     *
     * @param $socket
     *
     * @return mixed|null
     */
    protected function getUserBySocket($socket)
    {
        return $this->users[$this->socketUsers[$socket] ?? 0] ?? null;
    }

    /**
     * 处理信息
     *
     * @param $user
     * @param $message
     */
    protected function handleMessage($user, $message)
    {
        $data = json_decode(trim($message), true);
        if (empty($data)) {
            return;
        }
        switch ($data['command']) {
            case 'join':
                $user->nickname = $data['body']['nickname'];
                $user->portrait = $data['body']['portrait'];
                $res = [
                    'command' => 'join',
                    'body'    => [
                        'nickname' => $user->nickname,
                        'portrait' => $user->portrait,
                        'user_id'  => $user->id,
                    ],
                ];
                $this->broadcast($res);
                break;
            case 'online_users':
                $res = [
                    'command' => 'online_users',
                    'body'    => [
                        'data' => $this->getOnlineUsers(),
                    ],
                ];
                $this->send($user, $res);
                break;
            case 'message':
                $res = [
                    'command' => 'message',
                    'body'    => $data['body'] + [
                            'user_id'  => $user->id,
                            'nickname' => $user->nickname,
                            'portrait' => $user->portrait,
                            'time'     => date('H:', time())
                        ]
                ];
                $toUserID = $data['body']['to_user'] ?? '';
                if (!empty($toUserID)) {
                    $toUser = $this->users[$toUserID];
                    if ($toUserID != $user->id) {
                        $this->send($user, $res);
                        $this->send($toUser, $res);
                    }
                    return;
                }
                $this->broadcast($res);
                break;
            default:
                break;
        }
    }

    /**
     * 握手的逻辑
     *
     * @param $header
     * @param $client
     * @param $host
     * @param $port
     */
    protected function performHandshaking($header, $client, $host, $port)
    {
        $headers = array();
        $lines = preg_split("/\r\n/", $header);
        foreach ($lines as $line) {
            $line = chop($line);
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }

        $secKey = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "WebSocket-Origin: $host\r\n" .
            "WebSocket-Location: ws://$host:$port/demo/shout.php\r\n" .
            "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
        socket_write($client, $upgrade, strlen($upgrade));
    }

    /**
     * 广播
     *
     * @param $msg
     *
     * @return bool
     */
    protected function broadcast($msg)
    {
        foreach ($this->users as $user) {
            $this->send($user, $msg);
        }
        return true;
    }

    protected function getOnlineUsers()
    {
        $users = [];
        foreach ($this->users as $user) {
            $tmp = ['user_id' => $user->id, 'portrait' => $user->portrait, 'nickname' => $user->nickname];
            $users[] = $tmp;
        }
        return $users;
    }

    /**
     * 单发
     *
     * @param       $user
     * @param array $msg
     *
     * @return bool
     */
    protected function send($user, array $msg)
    {
        $msg = $this->mask(json_encode($msg));
        @socket_write($user->socket, $msg, strlen($msg));
        return true;
    }

    /**
     * 解码数据
     *
     * @param $text
     *
     * @return string
     */
    protected function unmask($text)
    {
        $length = ord($text[1]) & 127;
        if ($length == 126) {
            $masks = substr($text, 4, 4);
            $data = substr($text, 8);
        } elseif ($length == 127) {
            $masks = substr($text, 10, 4);
            $data = substr($text, 14);
        } else {
            $masks = substr($text, 2, 4);
            $data = substr($text, 6);
        }
        $text = "";
        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i % 4];
        }
        return $text;
    }

    /**
     * 编码数据
     *
     * @param $text
     *
     * @return string
     */
    protected function mask($text)
    {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($text);
        $header = '';

        if ($length <= 125) {
            $header = pack('CC', $b1, $length);
        } elseif ($length > 125 && $length < 65536) {
            $header = pack('CCn', $b1, 126, $length);
        } elseif ($length >= 65536) {
            $header = pack('CCNN', $b1, 127, $length);
        }
        return $header . $text;
    }


    /**
     * 输出信息
     *
     * @param $message
     */
    protected function stdout($message)
    {
        echo "$message\n";
    }

    /**
     * 输出错误信息
     *
     * @param $message
     */
    protected function stderr($message)
    {
        echo "$message\n";
    }
}