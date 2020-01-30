<?php
//ini_set('display_errors', 1);
include 'config.php';

$api = 'https://api.telegram.org/bot'.$tg_bot_token;

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
$user_id = $output['message']['from']['id'];
$message_id = $output['message']['message_id'];
$new_user = $output['message']['new_chat_members'];

echo "Init successful.\n";

//----------------------------------------------------------------------------------------------------------------------------------//

if ($message == '/start') {
	sendMessage($chat_id, "__йоу__");
}

if ($message == '/cid') {
	sendMessage($chat_id, "id этого чата: `".$chat_id."`");
}

if ($message == '/setup') {
	//deleteMessage
	sendMessage($chat_id, "твой user_id: `".$user_id."`");
	sendMessage($user_id, "вызван из `".$chat_id."`");
}

if ($new_user) {
	sendWelcomeMessage($chat_id, "ебать здарова нахуй", $message_id);
}

//----------------------------------------------------------------------------------------------------------------------------------//

//отправка форматированного сообщения
function sendMessage($chat_id, $message) {
	file_get_contents($GLOBALS['api'].'/sendMessage?chat_id='.$chat_id.'&text='.urlencode($message).'&parse_mode=MarkdownV2');
}

function sendWelcomeMessage($chat_id, $message, $new_member_message_id) {
	file_get_contents($GLOBALS['api'].'/sendMessage?chat_id='.$chat_id.'&text='.urlencode($message).'&parse_mode=MarkdownV2'.'&reply_to_message_id='.$new_member_message_id);
}

mysqli_close($db);
echo "End script."
?>