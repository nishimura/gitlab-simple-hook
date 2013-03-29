<?php

class Hook
{
    public function run($obj){
        $config = parse_ini_file('config.ini', true);
        $projectConfig = parse_ini_file('projects.ini', true);

        $projects = array();
        foreach ($projectConfig as $key => $section){
            if ($obj->repository->name !== $section['project'])
                continue;
            if (!preg_match('/'.$section['branch'].'$/', $obj->ref))
                continue;

            $projects[$key] = $section;
        }

        foreach ($projects as $project){
            if (isset($config['mail']) && isset($config['mail']['to'])
                && isset($project['mail']) && $project['mail'])
                $this->sendMail($obj, $config['mail']);
        }

        chdir('repositories');
        foreach ($projects as $name => $project)
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

    private function runProject($name, $project){
        if (!file_exists($name)){
            exec('git clone ' . $project['repository'] . " $name");
            chdir($name);
        }else{
            chdir($name);
            exec('git pull');
        }

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
