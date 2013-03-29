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

        if (isset($config['hook']))
            $this->runHook($config['hook']);

        $projects = parse_ini_file('projects.ini', true);
        chdir('repositories');
        foreach ($projects as $name => $project)
            if ($obj->repository->name === $name)
                $this->runProject($name, $project);
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

    private function runHook($config){
        if (!isset($config['commands']) || !is_array($config['commands']))
            return;

        foreach ($config['commands'] as $command){
            exec($command);
        }
    }

    private function runProject($name, $project){
        if (!file_exists($name)){
            exec('git clone ' . $project['repository'] . " $name");
            chdir($name);
        }else{
            chdir($name);
            exec('git pull');
        }
        $this->initPull();

        if (!isset($project['commands']) || !is_array($project['commands']))
            return;

        foreach ($project['commands'] as $command)
            exec($command);

        chdir('..');
    }
}

$hook = new Hook();
$data = file_get_contents('php://input');
$hook->run(json_decode($data));
