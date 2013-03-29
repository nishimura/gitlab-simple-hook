<?php

class Hook
{
    public function run($obj){
        $config = parse_ini_file('config.ini', true);
        $branch = $config['git']['branch'];
        if (!preg_match('/'.$branch.'$/', $obj->ref))
            return;

        if (isset($config['mail']) && isset($config['mail']['to']))
            $this->sendMail($obj, $config['mail']);
    }
    private function sendMail($obj, $config){
        if (!isset($obj->commits) || !is_array($obj->commits))
            return;

        $to = $config['to'];
        $from = $config['from'];
        $config['headers'][] = 'From: ' . $from;
        $headers = implode("\n", $config['headers']);

        $subject = sprintf($config['subject'], $obj->repository->name);
        $params = "-f$from";

        $body = '';
        if (isset($config['info']))
            $body .= $config['info'] . "\n\n";
        $body .= $obj->repository->homepage . "\n\n";

        foreach ($obj->commits as $commit){
            $body .= '* ' . $commit->message . "\n";
            $body .= '    '
                . date('Y-m-d H:i:s', strtotime($commit->timestamp))
                . ' ' . $commit->author->name . "\n\n";
        }

        $ret = mb_send_mail($to, $subject, $body, $headers, $params);
        // $ret: debug
    }
}

$hook = new Hook();
$data = file_get_contents('php://input');
$hook->run(json_decode($data));
