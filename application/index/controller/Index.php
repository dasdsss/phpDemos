<?php
//namespace app\index\controller;

class Index
{
    protected $master = null;
    protected $port     = 8080;
    protected $ip  =  '0.0.0.0';
    protected $connectPool = [];
    protected $handPoos = [];
    public function __construct()
    {
        $this->startServer();
    }


    public function startServer(){
       $this->connectPool[] = $this->master = socket_create(AF_INET,SOCK_STREAM,SOL_TCP );

       socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1);
       socket_bind( $this->master, $this->ip, $this->port);
       socket_listen($this->master,1000);
       while (true){
           $sockets = $this->connectPool;
           $write = $except = null;
           socket_select($sockets,$write,$except,60);
           foreach ($sockets as $socket){
               if($socket == $this->master){
                   $this->connectPool[] = $client = socket_accept($this->master);
                   $keyArr = array_keys($this->connectPool,$client);
                   $key = end($keyArr);
                   $this->handPoos[$key] = false;
               }else{
                   $length = socket_recv($socket,$buffer,20480,0);
                   if($length<7){
                      $this->close($socket);
                   }else{
                       $key = array_search($socket,$this->connectPool);
                       if($this->handPoos[$key] == false){
                           $this->handShake($socket,$buffer,$key);
                       }else{
                            $message = $this->deFrame($buffer);
                            $message = $this->enFrame($message);
                            $this->send($message);
                       }
                   }
               }
           }
       }
    }

    public function close($socket){
       $key = array_search($socket,$this->connectPool);
       unset($this->connectPool[$key]);
       unset($this->handPoos[$key]);
       socket_close($socket);
    }

    public function handShake($socket,$buffer,$keys){
//        if(preg_match("/Sec-WebSocket-Key:(.*)\r\n/",$buffer,$mathc)){
//            $responseKey = base64_encode(sha1($mathc[1].'258EAFA5-E914-47DA-95CA-C5AB0DC85B11',true));
//            $upgrade = "HTTP/1.1 101 Switching Protocol\r\n" .
//                        "Upgrade : websocket\r\n" .
//                        "Connection : Upgrade\r\n" .
//                        "Sec-WebSocket-Accept:" . $responseKey . "\r\n\r\n";
//            socket_write($socket,$upgrade.strlen($upgrade));
//            $this->handPoos[$key] = true;
//        }
        //提取websocket传的key并进行加密 （这是固定的握手机制获取Sec-WebSocket-Key:里面的key）
        $buf = substr($buffer,strpos($buffer,'Sec-WebSocket-Key:')+18);
        //去除换行空格字符
        $key = trim(substr($buf,0,strpos($buf,"\r\n")));
        //固定的加密算法
        $new_key = base64_encode(sha1($key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",true));
        $new_message = "HTTP/1.1 101 Switching Protocols\r\n";
        $new_message .= "Upgrade: websocket\r\n";
        $new_message .= "Sec-WebSocket-Version: 13\r\n";
        $new_message .= "Connection: Upgrade\r\n";
        $new_message .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
        //将套接字写入缓冲区
        socket_write($socket,$new_message,strlen($new_message));
        // socket_write(socket,$upgrade.chr(0), strlen($upgrade.chr(0)));
        //标记此套接字握手成功
        $this->handPoos[$keys]=true;


    }



    public function deFrame($buffer){
        $len = $masks = $data = $decoded = null;
        $len = ord($buffer[1]) & 127;
        if($len === 126){
            $masks = substr($buffer,4,4);
            $data = substr($buffer,8);
        }elseif($len === 127){
            $masks = substr($buffer,10,4);
            $data = substr($buffer,14);
        }else{
            $masks = substr($buffer,2,4);
            $data = substr($buffer,6);
        }
        for ($index = 0;$index<strlen($data);$index++){
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }
        return $decoded;
    }

    public function enFrame($message){
       $len = strlen($message);
       if($len <= 125){
           return "\x81" . chr($len)  . $message;
       }elseif ($len <= 65535){
           return "\x81" . chr($len) . pack("n",$len)  . $message;
       }else{
           return "\x81" . chr($len) . pack("xxxxN",$len)  . $message;
       }
    }

    public function send($message){
//        var_dump($this->connectPool);
        foreach ($this->connectPool as $socket){
            if($socket != $this->master){
                socket_write($socket,$message,strlen($message));
            }
        }
    }
}

new Index();
