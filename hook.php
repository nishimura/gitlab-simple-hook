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
            if (!preg_match('|^refs/heads/'.$section['branch'].'$|', $obj->ref))
                continue;

            $projects[$key] = $section;
        }

        chdir('repositories');
        $ret = '';
        foreach ($projects as $name => $project)
            $ret .= $this->runProject($name, $project) . "\n\n";

        foreach ($projects as $project){
            if (isset($config['mail']) && isset($config['mail']['to'])
                && isset($project['mail']) && $project['mail'])
                $this->sendMail($obj, $config['mail'], $project, $ret);
        }
    }
    private function sendMail($obj, $config, $project, $ret){
        if (!isset($obj->commits) || !is_array($obj->commits))
            return;

        if (isset($config['lang']))
            mb_language($config['lang']);
        if (isset($config['internalEncoding']))
            mb_internal_encoding($config['internalEncoding']);

        $to = $config['to'];
        $from = $config['from'];
        $config['headers'][] = 'From: ' . $from;
        $headers = implode("\n", $config['headers']);

        $subject = sprintf($config['subject'], $obj->repository->name);
        $params = "-f$from";

        $body = '';
        if (isset($config['info'])){
            if (is_array($config['info']))
                $body .= implode("\n", $config['info']) . "\n\n";
            else
                $body .= $config['info'] . "\n\n";
        }
        if (isset($project['mail.info'])){
            if (is_array($project['mail.info']))
                $body .= implode("\n", $project['mail.info']) . "\n\n";
            else
                $body .= $project['mail.info'] . "\n\n";
        }

        $issues = array();
        foreach ($obj->commits as $commit){
            $issue = $this->parseIssue($project, $commit->message);
            if ($issue)
                $issues[] = $issue;
            $body .= '* ' . $commit->message . "\n";
            $body .= '    '
                . date('Y-m-d H:i:s', strtotime($commit->timestamp))
                . ' ' . $commit->author->name . "\n\n";
        }

        if ($issues)
            $body .= "----\nIssues:\n" . implode("\n", $issues) . "\n\n";
        $body .= "----\n";
        $body .= "Result:\n$ret";
        $body .= "----\n";
        $body .= 'GitLab: ' . $obj->repository->homepage . "\n\n";
        $ret = mb_send_mail($to, $subject, $body, $headers, $params);
        // $ret: debug
    }
    private function parseIssue($project, $message){
        if (isset($project['issues.url'])
            && preg_match('/#[0-9]+/', $message))
            return preg_replace('/#([0-9]+)/',
                                $project['issues.url'] . '/$1', $message);
        return false;
    }

    private function runProject($name, $project){
        $out = array();
        $ret = 0;
        if (!file_exists($name)){
            exec('git clone ' . $project['repository'] . " $name", $out);
            chdir($name);
            exec('git checkout ' . $project['branch'], $out);
        }else{
            chdir($name);
            exec('git pull', $out);
        }

        if (!isset($project['commands']) || !is_array($project['commands']))
            return '';

        foreach ($project['commands'] as $command){
            exec($command, $out, $ret);
            if ($ret){
                $out[] = "Error: ";
                $out[] = "'$command' return code $ret";
                break;
            }
        }

        chdir('..');
        return implode("\n", $out);
    }
}

$hook = new Hook();
$data = file_get_contents('php://input');
$hook->run(json_decode($data));
