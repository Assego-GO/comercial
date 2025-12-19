<?php
// atacadao/config.php
// Define credenciais do Atacadão a partir de variáveis de ambiente
// ou do arquivo .env da raiz do projeto.

declare(strict_types=1);

$envPath = dirname(__DIR__) . '/.env';

/** Obtém um valor do .env (sem alterar getenv). */
function atacadao_env_get(string $key, string $path): string {
	if (!is_file($path)) {
		return '';
	}
	$lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if ($lines === false) {
		return '';
	}
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '' || $line[0] === '#') {
			continue;
		}
		$parts = explode('=', $line, 2);
		if (count($parts) === 2) {
			$k = trim($parts[0]);
			if ($k !== $key) {
				continue;
			}
			$v = trim($parts[1]);
			$len = strlen($v);
			if ($len >= 2) {
				$first = $v[0];
				$last = $v[$len - 1];
				if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
					$v = substr($v, 1, -1);
				}
			}
			return $v;
		}
	}
	return '';
}

$token = getenv('ATACADAO_TOKEN');
if ($token === false || $token === '') {
	$token = atacadao_env_get('ATACADAO_TOKEN', $envPath);
}

$secret = getenv('ATACADAO_SECRET');
if ($secret === false || $secret === '') {
	$secret = atacadao_env_get('ATACADAO_SECRET', $envPath);
}

$endpoint = getenv('ATACADAO_ENDPOINT');
if ($endpoint === false || $endpoint === '') {
	$endpoint = atacadao_env_get('ATACADAO_ENDPOINT', $envPath);
}

if (!defined('ATACADAO_TOKEN') && $token !== '') {
	define('ATACADAO_TOKEN', (string)$token);
}
if (!defined('ATACADAO_SECRET') && $secret !== '') {
	define('ATACADAO_SECRET', (string)$secret);
}
if ($endpoint !== '' && !defined('ATACADAO_ENDPOINT')) {
	define('ATACADAO_ENDPOINT', (string)$endpoint);
}
