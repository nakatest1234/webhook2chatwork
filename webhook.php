<?php

ini_set('display_errors', true);
ini_set('error_reporting', E_ALL );

$config = array(
	'rid'   => '',
	'token' => '',
	// github author email => chatwork account id
	'users' => array(
		'author@test.com' => '0',
	),
	'ts'          => 4,
	'commits_num' => 10,
);

return main($config);

function main($config=array())
{
	$errmsg = '';

	try
	{
		isset($_GET['rid'])         and $config['rid'] = $_GET['rid'];
		isset($_GET['ts'])          and $config['ts'] = $_GET['ts'];
		isset($_GET['commits_num']) and $config['commits_num'] = $_GET['commits_num'];

		if ( ! is_numeric($config['commits_num']) || $config['commits_num']<1)
		{
			$config['commits_num'] = 1;
		}

		if ( ! isset($config['token'])) throw new \Exception('no token');
		if ( ! isset($config['rid']) || empty($config['rid'])) throw new \Exception('no rid');

		$json = isset($_POST['payload']) ? $_POST['payload'] : array();

		if ( ! empty($json))
		{
			$errmsg = post_chatwork($config, json_decode($json, true));
		}
		else
		{
			$errmsg = 'INPUT ERROR';
		}
	}
	catch (\Exception $e)
	{
		$errmsg = $e->getMessage();
	}

	$l = new Log();

	$l->write($errmsg===''?'OK':$errmsg);
	return $errmsg==='' ? '' : $errmsg;
}

function post_chatwork($config, $json, $api='https://api.chatwork.com/v1/rooms/%s/messages')
{
	$errmsg = '';

	if (isset($json['commits']))
	{
		$ref             = isset($json['ref']) ? $json['ref'] : 'no ref';
		$compare         = isset($json['compare']) ? $json['compare'] : 'no compare';
		$repository_name = isset($json['repository']['name']) ? $json['repository']['name'] : 'no name';

		$commits = array();

		$commits_num = $config['commits_num'];
		$over_flg    = count($json['commits'])>$commits_num ? true : false;

		foreach (array_splice($json['commits'], count($json['commits'])-$commits_num, $commits_num) as $v)
		{
			$author_email = isset($v['author']['email']) ? $v['author']['email'] : 'no email';
			$message      = isset($v['message'])         ? $v['message']     : '';
			$url          = isset($v['url'])             ? $v['url']."?ts={$config['ts']}" : '';
			$time         = isset($v['timestamp'])       ? strtotime($v['timestamp']) : 0;
			$chatwork_account_id = isset($config['users'][$author_email]) ? $config['users'][$author_email] : 0;

			$commits[] = sprintf("[qt][qtmeta aid=%s time=%s]%s\n%s[/qt]", $chatwork_account_id, $time, $url, $message);
		}

		if ( ! empty($commits))
		{
			$message = sprintf("[info][title]%s: %s[/title]%s\n%s%s[/info]", $repository_name, $ref, $compare, $over_flg ? "over {$commits_num} commits\n" : '', implode("\n", $commits));

			$url = sprintf($api, $config['rid']);

			$curl = curl_init();

			if ($curl)
			{
				$header = array(
					sprintf('X-ChatWorkToken: %s', $config['token']),
				);

				$post_data = array(
					'body' => $message,
				);

				// curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
				// curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, true);

				curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
				curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_URL, $url);
				curl_setopt($curl, CURLOPT_HEADER, true);
				curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
				curl_setopt($curl, CURLOPT_POST, true);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);

				$output= curl_exec($curl);

				curl_close($curl);
			}
			else
			{
				$errmsg = 'CURL ERROR';
			}
		}
		else
		{
			$errmsg = 'HEADER ERROR';
		}
	}
	else
	{
		$errmsg = 'no commits';
	}

	return $errmsg;
}

class Log
{
	private $filename = '';
	private $is_log   = true;

	public function __construct($log_file='', $is_log=true)
	{
		if ($log_file=='')
		{
			$tmp = debug_backtrace();

			if (count($tmp)>0)
			{
				$file     = $tmp[0]['file'];
				$log_file = sprintf('%s%s%s.log', dirname($file), DIRECTORY_SEPARATOR, basename($file, '.php'));
			}
		}

		$this->filename = $log_file;
		$this->is_log   = $is_log;
	}

	public function write($msg='', $sp="\t")
	{
		is_object($msg) && $msg = 'OBJECT';
		is_bool($msg)   && $msg = $msg ? 'TRUE' : 'FALSE';

		if (is_array($msg))
		{
			ob_start();
			var_dump($msg);
			$msg = ob_get_clean();
		}

		$msg = sprintf("%s%s%s\n", date('Y-m-d H:i:s'), $sp, $msg);

		$old = @umask(0111);

		file_put_contents($this->filename, $msg, FILE_APPEND|LOCK_EX);

		@umask($old);
	}
}
