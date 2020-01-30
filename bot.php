<?php
//ini_set('display_errors', 1);
include 'config.php';
require_once 'vendor/autoload.php';

$token = $tg_bot_token;
$api = 'https://api.telegram.org/bot'.$token;

$input = file_get_contents('php://input');
$output = json_decode($input, TRUE); //сюда приходят все запросы по вебхукам

//соединение с БД
$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
if (mysqli_connect_errno()) echo "Failed to connect to MySQL: " . mysqli_connect_error();
	else echo "MySQL connect successful.\n";

if ($check = mysqli_query($db, 'select * from main')) {
	$count = mysqli_num_rows($check);
	echo "There is $count records in DB.\n\n";
	mysqli_free_result($check);
}

//телеграмные события
$chat_id = $output['message']['chat']['id']; //отделяем id чата, откуда идет обращение к боту
$message = $output['message']['text']; //сам текст сообщения
$user = $output['message']['from']['username'];

echo "Init successful.\n";

//регистрация+генерация secret для ACR
if ($message == '/start') {
	sendMessage($chat_id, "йоу");
}

if ($message == '📳 Тихий режим') {
	$query = mysqli_query($db, 'select silent from users where chat_id='.$chat_id);
	while ($sql = mysqli_fetch_object($query)) {
		$silent = $sql->silent;
	}
	if ($silent == 0) {
		mysqli_query($db, 'update users set silent=1 where chat_id='.$chat_id);
		sendMessage($chat_id, "Тихий режим *включен*");
	} else {
		mysqli_query($db, 'update users set silent=0 where chat_id='.$chat_id);
		sendMessage($chat_id, "Тихий режим *отключен*");
	}
	mysqli_free_result($sql);
}
//----------------------------------------------------------------------------------------------------------------------------------//

//отправка форматированного сообщения
function sendMessage($chat_id, $message, $keyboard) {
	if ($keyboard === NULL) {
		file_get_contents($GLOBALS['api'].'/sendMessage?chat_id='.$chat_id.'&text='.urlencode($message).'&parse_mode=MarkupV2');
	} else {
		file_get_contents($GLOBALS['api'].'/sendMessage?chat_id='.$chat_id.'&text='.urlencode($message).'&parse_mode=MarkupV2'.'&reply_markup='.json_encode($keyboard));
	}
}

mysqli_close($db);
echo "End script."
?>