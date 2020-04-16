<?php
/*
* select application numbers from patent_cn_patentindex, and then insert them into patent_cn_patent
* use this plug by php commond-line
*/
class application_number{


    private $model                  = 'start';
    public  $previous_patent_order  = null;
    private $memory_overflow_file   = 'memory_overflow.log';
    private $mysqli                 = NULL;


    public function __construct($model='start'){
        if($model === 'daemon') $this->model = 'daemon';
        $memory_overflow_file = $this->memory_overflow_file;
        if(file_exists($memory_overflow_file)) unlink($memory_overflow_file);
    }
    
    
    public function init(){
        if(file_exists('./previous_patent_number_year.log'))
            die("{\"status\":\"error\", \"message\":\"previous_patent_number_year.log, this file already exists, this file #20\"}\r\n");
        
        file_put_contents('./previous_patent_number_year.log', '0');
        die("{\"status\":\"success\", \"message\":\"init successfully, previous_patent_number_year.log is generated, this file #23\"}\r\n");
    }
    
        
    private function previous_patent_order(){
        $previous   = $this->previous_patent_order;
        if(!is_null($previous)){
            if(!is_numeric($previous))
                die("{\"status\":\"error\", \"message\":\"patent number is not number in previous_patent_number_year.log, this file #31\"}\r\n");
            return $previous;
        }
        
        if(!file_exists('./previous_patent_number_year.log'))
            die("{\"status\":\"error\", \"message\":\"previous_patent_number_year.log, this file does not exists, this file #29\"}\r\n");
        
        $previous   = file_get_contents('./previous_patent_number_year.log');
        $length     = strlen($previous);
        if($length === 0)
            die("{\"status\":\"error\", \"message\":\"patent number is empty in previous_patent_number_year.log, this file #45\"}\r\n");
        if($length!==1 && $length!==10 && $length!==12)
            die("{\"status\":\"error\", \"message\":\"patent number length is incorrect in previous_patent_number_year.log, this file #47\"}\r\n");
        if(!is_numeric($previous))
            die("{\"status\":\"error\", \"message\":\"patent number is not number in previous_patent_number_year.log, this file #49\"}\r\n");
        
        return $previous;
    }
    
    
    private function arrayToInsertSql($array, $table){
        if(!is_array($array))       die('error, first parameter in arrayToInsertSql() is not array');
        if(strlen($table) === 0)    die('error, second parameter in arrayToInsertSql() is not exist');
        
        $column_array   = array();
        $value_array    = array();
        foreach($array as $key=>$value){
            $column_array[] = "`{$key}`";
            if(is_null($value)){
                $value_array[] = 'NULL';
            }else{
                $value_array[] = "'".str_replace("'", "''", $value)."'";
            }
        }
        $columns    = implode(',', $column_array);
        $values     = implode(',', $value_array);
        
        if(strlen(@$array['title']) > 0){
            $sql        = "INSERT IGNORE INTO {$table} ({$columns}) VALUES({$values}) ON DUPLICATE KEY UPDATE title='{$array['title']}'";
        }else{
            $sql        = "INSERT IGNORE INTO {$table} ({$columns}) VALUES({$values})";
        }
        
        unset($array);
        unset($table);
        unset($column_array);
        unset($value_array);
        unset($columns);
        unset($values);
        
        return $sql;
    }

    
    private function insert($mysqli, $data){
        $model = $this->model;
        
        $index['application_number']      	= $data['application_number'];
        $index['patent_number']    			= substr($data['application_number'], 0, strlen($data['application_number'])-1);
		$index['patent_number']    			.= '.';
		$index['patent_number']    			.= substr($data['application_number'], strlen($data['application_number'])-1);
        if(strlen($data['title']) > 0)      $index['title'] = $data['title'];
        $index['typeid'] 					= $data['typeid'];
        if(strlen($data['application_number']) === 9){
            if(substr($data['application_number'], 0, 1)!=='0'){
                $index['year']  = '19'.substr($data['application_number'], 0, 2);
            }else{
                $index['year']  = '20'.substr($data['application_number'], 0, 2);
            }
            $index['number']    = (int)substr($data['application_number'], 3, 5);
        }elseif(strlen($data['application_number']) === 13){
            $index['year']      = substr($data['application_number'], 0, 4);
            $index['number']    = (int)substr($data['application_number'], 5, 7);
        }else{
            if($model === 'start'){
                die("error, application number length is incrrect, {$data['application_number']}");
            }elseif($model === 'daemon'){
                $error_file = 'error_' . date('Y-m-d') . '.log';
                file_put_contents($error_file, "error, application number length is incrrect, {$data['application_number']}\r\n\r\n", FILE_APPEND);
            }
            return;
        }
        
        if($model === 'start')  echo "application_number: {$index['application_number']}\r\n";
        if($model === 'daemon') file_put_contents('daemon.log', "{$index['application_number']}\r\n", FILE_APPEND);

        $sql 	= $this->arrayToInsertSql($index, 'patent_cn_patentindex');

        $mysqli->query("SET NAMES 'utf8'");
        $mysqli->query($sql);
        $error  = $mysqli->error;
        if($error)  die("error, {$error}\r\n");
        
        $result = $mysqli->affected_rows;
        if($model === 'start')  echo "operate result:     {$result}\r\n";
        if($model === 'daemon') file_put_contents('daemon.log', "result: {$result}\r\n", FILE_APPEND);

        unset($data);
        unset($index);
        unset($sql);
        unset($mysqli);
        unset($error);
        unset($result);
    }
    
    
    public function main(){
        $model      = $this->model;
        if($model === 'start') echo('memory:             ' . memory_get_usage() . "\r\n");

        if(memory_get_usage() > 16777216){
            $memory_overflow_file = $this->memory_overflow_file;
            if(!file_exists($memory_overflow_file)) file_put_contents($memory_overflow_file, memory_get_usage());
        }
        
        $previous   = $this->previous_patent_order();

        $mysqli = $this->mysqli;
        if(is_null($mysqli)){
            $mysqli     = new \mysqli('172.31.169.89', 'patent_master', '9/@a$>+F', 'patent', 3306);
            $this->mysqli   = $mysqli;
        }
        if(($mysqli->connect_error)){
            die($mysqli->connect_error);
        }
        $sql            = "SELECT * FROM patent_cn_patent WHERE patent_order>{$previous} ORDER BY patent_order ASC LIMIT 1";
        $mysqli->query("SET NAMES 'utf8'");
        $result         = $mysqli->query($sql);
        
        if($result->num_rows === 0){
            if($model === 'start') die("{\"status\":\"error\", \"message\":\"data empty, this file #166\"}\r\n");
            if($model === 'daemon'){
                $error_file = 'error_' . date('Y-m-d') . '.log';
                file_put_contents($error_file, "error, data empty\r\n\r\n", FILE_APPEND);
                die;
            }
        }
        
        $data           = mysqli_fetch_assoc($result);
        $this->insert($mysqli, $data);
        
        $this->previous_patent_order = $data['patent_order'];
        if(!file_put_contents('./previous_patent_number_year.log', $data['patent_order'], LOCK_EX)){
            $fileopen = fopen('previous_patent_number_year.log', 'w');
            flock($fileopen,  LOCK_EX);
            fwrite($fileopen, $data['patent_order']);
            flock($fileopen,  LOCK_UN);
            fclose($fileopen);
        }

        unset($previous);
        unset($sql);
        unset($result);
        unset($data);
        
        if($model === 'start')  echo "\r\n";
        if($model === 'daemon'){
            file_put_contents('daemon.log', "\r\n", FILE_APPEND);
            clearstatcache();
            if(filesize('daemon.log') > 1024) unlink('daemon.log');
        }

        usleep(10);
        $this->main();
        
        $mysqli->close();
        $this->mysqli = NULL;
    }


}


class process{
    

    private $start_master_pid_file  = 'start_master_pid.log';
    private $start_worker_pid_file  = 'start_worker_pid.log';
    private $daemon_master_pid_file = 'daemon_master_pid.log';
    private $daemon_worker_pid_file = 'daemon_worker_pid.log';
    private $memory_overflow_file   = 'memory_overflow.log';
    private $previous_patent_order  = null;
    
    
    public function __construct(){
        global $argv;
        if(count($argv) !== 2)
            die("{\"status\":\"error\", \"message\":\"php command line, parameter number is incorrect\"}\r\n");
        $command = $argv[1];
        if(!in_array($command, array('init', 'start', 'daemon', 'status', 'stop')))
            die("{\"status\":\"error\", \"message\":\"php command line, parameter must be init, start, daemon, status or stop!\"}\r\n");
    }
    
    
    public function main($class, $function){
        global $argv;
        $command = $argv[1];
        
        if($command === 'init'){
            $this->init($class, $function);
            return;
        }
        
        if($command === 'start'){
            $this->start_daemon_check();
            $this->start($class, $function);
            return;
        }
        
        if($command === 'daemon'){
            $this->start_daemon_check();
            $this->daemon($class, $function);
            return;
        }
        
        if($command === 'stop'){
            $this->stop();
            return;
        }
        
        if($command === 'status'){
            $this->status();
            return;
        }
    }
    
    
    private function start_daemon_check(){
        $start_master_pid_file  = $this->start_master_pid_file;
        $start_worker_pid_file  = $this->start_worker_pid_file;
        if(file_exists($start_master_pid_file)){
            $start_master_pid   = file_get_contents($start_master_pid_file);
            if(posix_kill($start_master_pid, 0)){
                die("{\"status\":\"error\", \"message\":\"start model is already running, check by '# ps -ef | grep php', this file #268\"}\r\n");
            }else{
                @unlink($start_master_pid_file);
                @unlink($start_worker_pid_file);
            }
        }
        
        $daemon_master_pid_file = $this->daemon_master_pid_file;
        $daemon_worker_pid_file = $this->daemon_worker_pid_file;
        if(file_exists($daemon_master_pid_file) && file_exists($daemon_worker_pid_file)){
            $daemon_master_pid  = file_get_contents($daemon_master_pid_file);
            $daemon_worker_pid  = file_get_contents($daemon_worker_pid_file);
            if(posix_kill($daemon_master_pid, 0) && posix_kill($daemon_worker_pid, 0)){
                die("{\"status\":\"error\", \"message\":\"daemon is already running, this file #280\"}\r\n");
            }
            if(posix_kill($daemon_master_pid, 0)) posix_kill($daemon_master_pid, SIGINT);
            if(posix_kill($daemon_worker_pid, 0)) posix_kill($daemon_worker_pid, SIGINT);
            @unlink($daemon_master_pid_file);
            @unlink($daemon_worker_pid_file);
        }elseif(file_exists($daemon_master_pid_file)){
            $daemon_master_pid  = file_get_contents($daemon_master_pid_file);
            if(posix_kill($daemon_master_pid, 0)) posix_kill($daemon_master_pid, SIGINT);
            @unlink($daemon_master_pid_file);
        }elseif(file_exists($daemon_worker_pid_file)){
            $daemon_worker_pid  = file_get_contents($daemon_worker_pid_file);
            if(posix_kill($daemon_worker_pid, 0)) posix_kill($daemon_worker_pid, SIGINT);
            @unlink($daemon_worker_pid_file);
        }
    }
    
    
    private function init($class, $function){
        $object = new $class();
        $object->init();
    }
    
    
    private function start($class, $function){
        $start_master_pid_file  = $this->start_master_pid_file;
        $start_master_pid       = getmypid();
        file_put_contents($start_master_pid_file, $start_master_pid);
        
        $this->start_fork($class, $function);
    }
    
    
    private function start_fork($class, $function){
        $memory_overflow_file = $this->memory_overflow_file;
        if(file_exists($memory_overflow_file)) unlink($memory_overflow_file);
        
        $fork = pcntl_fork();
        if($fork === -1){
            die("{\"status\":\"error\", \"message\":\"enable start failed, it is about fork child progess, this file #254\"}\r\n");
        }elseif($fork){
            pcntl_signal(SIGCLD, SIG_IGN);
            pcntl_signal(SIGCHLD,SIG_IGN);
            $start_master_pid_file  = $this->start_master_pid_file;
            $start_worker_pid_file  = $this->start_worker_pid_file;
            file_put_contents($start_worker_pid_file, $fork);
            while(1){
                if(!posix_kill($fork, 0)){
                    @unlink($start_master_pid_file);
                    @unlink($start_worker_pid_file);
                    die("{\"status\":\"error\", \"message\":\"start model's child progress stop, this file #326\"}\r\n");
                }

                if(file_exists($memory_overflow_file)){
                    $previous_patent_order = file_get_contents('previous_patent_number_year.log');
                    if(strlen($previous_patent_order) > 0) $this->previous_patent_order = $previous_patent_order;
                    $memory = file_get_contents($memory_overflow_file);
                    if($memory > 16777216){
                        posix_kill($fork, SIGINT);
                        unlink($memory_overflow_file);
                        sleep(1);
                        $this->start_fork($class, $function);
                    }
                }
            
                sleep(5);
            }
            
        }else{
            $object                 = new $class();
            $previous_patent_order  = $this->previous_patent_order;
            if(!is_null($previous_patent_order)) $object->previous_patent_order = $previous_patent_order;
            $object->$function();
        }
    }
    
    
    private function daemon($class, $function){
        $daemon_master_pid_file = $this->daemon_master_pid_file;
        $daemon_worker_pid_file = $this->daemon_worker_pid_file;
        $fork   = pcntl_fork();
        if($fork === -1){
            die("{\"status\":\"error\", \"message\":\"enable daemon failed, it is about fork child progess, this file 376\"}\r\n");
        }elseif($fork){
            file_put_contents($daemon_master_pid_file, $fork);
            echo("{\"status\":\"success\", \"message\":\"enable daemon successfully!\"}\r\n");
            exit(0);
        }else{
            $this->daemon_fork($class, $function);
        }
    }
    
    
    private function daemon_fork($class, $function){
        $memory_overflow_file = $this->memory_overflow_file;
        if(file_exists($memory_overflow_file)) unlink($memory_overflow_file);
        
        $fork   = pcntl_fork();
        if($fork === -1){
            $error_file = 'error_' . date('Y-m-d') . '.log';
            file_put_contents($error_file, "fork progress failed from master to worker, this file #382\r\n");
            exit;
        }elseif($fork){
            pcntl_signal(SIGCLD, SIG_IGN);
            pcntl_signal(SIGCHLD,SIG_IGN);
            $daemon_master_pid_file = $this->daemon_master_pid_file;
            $daemon_worker_pid_file = $this->daemon_worker_pid_file;
            file_put_contents($daemon_worker_pid_file, $fork);
            while(1){
                if(!posix_kill($fork, 0)){
                    $error_file = 'error_' . date('Y-m-d') . '.log';
                    file_put_contents($error_file, "worker progress stop here, " . date('Y-m-d h:i:s') . ", this file #391\r\n", FILE_APPEND);
                    @unlink($daemon_master_pid_file);
                    @unlink($daemon_worker_pid_file);
                    exit;
                }
                
                if(file_exists($memory_overflow_file)){
                    $previous_patent_order = file_get_contents('previous_patent_number_year.log');
                    if(strlen($previous_patent_order) > 0) $this->previous_patent_order = $previous_patent_order;
                    $memory = file_get_contents($memory_overflow_file);
                    if($memory > 16777216){
                        posix_kill($fork, SIGINT);
                        unlink($memory_overflow_file);
                        sleep(1);
                        $this->daemon_fork($class, $function);
                    }
                }
                sleep(5);
            }
            $error_file = 'error_' . date('Y-m-d') . '.log';
            $message    = date('Y-m-d H:i:s') . ", daemon stop here, this file #411\r\n\r\n";
            file_put_contents($error_file, $message, FILE_APPEND);
        }else{
            $object = new $class('daemon');
            $object->$function();
            exit;
        }
        
    }
    
    
    private function stop(){
        $start_worker_pid_file = $this->start_worker_pid_file;
        $start_master_pid_file = $this->start_master_pid_file;
        if(file_exists($start_worker_pid_file) || file_exists($start_master_pid_file)){
            if(file_exists($start_worker_pid_file)){
                $start_worker_pid   = file_get_contents($start_worker_pid_file);
                if(posix_kill($start_worker_pid, 0)){
                    posix_kill($start_worker_pid, SIGINT);
                    unlink($start_worker_pid_file);
                }
            }
            if(file_exists($start_master_pid_file)){
                $start_master_pid   = file_get_contents($start_master_pid_file);
                if(posix_kill($start_master_pid, 0)){
                    posix_kill($start_master_pid, SIGINT);
                    unlink($start_master_pid_file);
                }
            }
            die("{\"status\":\"sueccss\", \"message\":\"start model is stopped, check by '# ps -ef | grep php', this file 440'\"}\r\n\r\n");
        }
        
        $daemon_worker_pid_file = $this->daemon_worker_pid_file;
        $daemon_master_pid_file = $this->daemon_master_pid_file;
        if(file_exists($daemon_worker_pid_file) || file_exists($daemon_master_pid_file)){
            if(file_exists($daemon_worker_pid_file)){
                $daemon_worker_pid  = file_get_contents($daemon_worker_pid_file);
                if(posix_kill($daemon_worker_pid, 0)) posix_kill($daemon_worker_pid, SIGINT);
                @unlink($daemon_worker_pid_file);
            }
            if(file_exists($daemon_master_pid_file)){
                $daemon_master_pid  = file_get_contents($daemon_master_pid_file);
                if(posix_kill($daemon_master_pid, 0)) posix_kill($daemon_master_pid, SIGINT);
                @unlink($daemon_master_pid_file);
            }
            die("{\"status\":\"sueccss\", \"message\":\"daemon model is stopped, check by '# ps -ef | grep php', this file 440'\"}\r\n\r\n");
        }
    }
    
    
    private function status(){
        $start_master_pid_file  = $this->start_master_pid_file;
        $start_worker_pid_file  = $this->start_worker_pid_file;
        if(file_exists($start_master_pid_file)){
            $start_master_pid   = file_get_contents($start_master_pid_file);
            if(posix_kill($start_master_pid, 0)){
                die("{\"status\":\"success\", \"message\":\"start model is running, this file 453\"}\r\n");
            }else{
                @unlink($start_master_pid_file);
                @unlink($start_worker_pid_file);
            }
        }
        
        $daemon_master_pid_file = $this->daemon_master_pid_file;
        $daemon_worker_pid_file = $this->daemon_worker_pid_file;
        if(file_exists($daemon_master_pid_file) && file_exists($daemon_worker_pid_file)){
            $daemon_master_pid  = file_get_contents($daemon_master_pid_file);
            $daemon_worker_pid  = file_get_contents($daemon_worker_pid_file);
            if(posix_kill($daemon_master_pid, 0) && posix_kill($daemon_worker_pid, 0)){
                die("{\"status\":\"success\", \"message\":\"daemon is running, this file #459\"}\r\n");
            }
            if(posix_kill($daemon_master_pid, 0)) posix_kill($daemon_master_pid, SIGINT);
            if(posix_kill($daemon_worker_pid, 0)) posix_kill($daemon_worker_pid, SIGINT);
            @unlink($daemon_master_pid_file);
            @unlink($daemon_worker_pid_file);
        }elseif(file_exists($daemon_master_pid_file)){
            $daemon_master_pid  = file_get_contents($daemon_master_pid_file);
            if(posix_kill($daemon_master_pid, 0)) posix_kill($daemon_master_pid, SIGINT);
            @unlink($daemon_master_pid_file);
        }elseif(file_exists($daemon_worker_pid_file)){
            $daemon_worker_pid  = file_get_contents($daemon_worker_pid_file);
            if(posix_kill($daemon_worker_pid, 0)) posix_kill($daemon_worker_pid, SIGINT);
            @unlink($daemon_worker_pid_file);
        }
        
        die("{\"status\":\"success\", \"message\":\"program is not running, this file 475\"}\r\n");
    }
    
}


ini_set('memory_limit','512M');
date_default_timezone_set('Asia/Shanghai');

$object = new process;
$object->main('application_number', 'main');

