<?php

declare(strict_types=1);
require(__DIR__ . '/../../constants.php');
require(LIB_PATH . '/lib_rss.php');

header("Access-Control-Allow-Origin: *"); // 建议修改为自己的域
header("Content-Type:application/json; charset=utf-8");

function globalExceptionHandler(Throwable $e)
{
	if (http_response_code() === 200) http_response_code(500);

	die(json_encode([
		'msg' => 'Uncaught Exception: ' . $e->getMessage()
	]));
}

function getParams()
{
	Minz_Request::init();
	$user = ''; //指定用户
	$category = 'Friends'; //指定用户分类

	$items = Minz_Request::paramString('items');
	$offset = Minz_Request::paramString('offset');

	if (!ctype_digit($items) || !ctype_digit($offset)) {
		http_response_code(422);
		throw new Error('Invalid format `items` or `offset`!');
	}

	return [
		'user' => $user,
		'category' => $category,
		'items' => (int)$items,
		'offset' => (int)$offset
	];
}

function initSystem()
{
	FreshRSS_Context::initSystem();
	if (!FreshRSS_Context::hasSystemConf() || !FreshRSS_Context::systemConf()->api_enabled) {
		http_response_code(503);
		throw new Error('Service Unavailable!');
	}
}

function initUser($user)
{
	FreshRSS_Context::initUser($user);
	// usleep 应该是为了缓解计时攻击，但我不清楚在这里可不可以删
	if (!FreshRSS_Context::hasUserConf()) {
		usleep(rand(100, 10000));	//Primitive mitigation of scanning for users
		http_response_code(404);
		throw new Error('User not found!');
	} else {
		usleep(rand(20, 200));
	}
}

function getEntries($category, $items, $offset)
{
	$entryDAO = FreshRSS_Factory::createEntryDao();
	$sql = <<<SQL
SELECT nf.name AS feed_name, ne.title, ne.date, ne.link
FROM `_category` nc
JOIN `_feed` nf ON nc.id = nf.category
JOIN `_entry` ne ON nf.id = ne.id_feed
WHERE nc.name = :category
ORDER BY ne.date DESC
LIMIT :offset,:items
SQL;

	return $entryDAO->fetchAssoc($sql, [
		':category' => $category,
		':offset' => $offset,
		':items' => $items
	]);
}

function main()
{
	[
		'user' => $user,
		'category' => $category,
		'items' => $items,
		'offset' => $offset
	] = getParams();

	initSystem();
	initUser($user);

	$entries = getEntries($category, $items, $offset);

	foreach ($entries as $entry) {
		$data[] = [
			'url' => $entry['link'],
			'date' => $entry['date'],
			'title' => $entry['title'],
			'siteName' => $entry['feed_name']
		];
	}

	echo json_encode([
		'list' => $data,
		'items' => $items,
		'offset' => $offset
	]);
}

set_exception_handler('globalExceptionHandler');
main();
