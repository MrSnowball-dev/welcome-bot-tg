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
$chat = $output['message']['chat']['title'];
$message = $output['message']['text']; //сам текст сообщения
$user = $output['message']['from']['username'];
$user_id = $output['message']['from']['id'];
$message_id = $output['message']['message_id'];
$new_user = $output['message']['new_chat_members'];

echo "Init successful.\n";

//----------------------------------------------------------------------------------------------------------------------------------//

if ($message == '/start') {
	sendMessage($chat_id, "Для настройки приветственных сообщений - добавьте меня в чат и наберите там /setup");
}

if ($message == '/setup') {
	deleteMessage($chat_id, $message_id);
	$query = mysqli_query($db, 'select chat_id from main where chat_id='.$chat_id);
	while ($sql = mysqli_fetch_object($query)) {
		$sql_chat_id = $sql->chat_id;
	}
	sendMessage($user_id, "1 ".$chat_id);
	if ($sql_chat_id == $chat_id) {
		sendMessage($user_id, "2");
		sendMessage($user_id, "Чат ".$chat." уже настроен. Для изменения приветственного сообщения напишите мне\n\n`/set ".$chat_id." <ваше сообщение>`  <-- строку можно скопировать");
	} else {
		sendMessage($user_id, "3 ".$chat_id);
		mysqli_query($db, "insert into main (chat_id, chat_owner_user_id) values (".$chat_id.", ".$user_id.")");
		sendMessage($user_id, "Вы включили приветственные сообщения для ".$chat."!\nЧтобы задать своё приветствие, напишите мне\n\n`/set ".$chat_id." <ваше сообщение>`  <-- строку можно скопировать\n\nВ дальнейшем, изменить приветствие для чата сможете только вы.");
	}
}

if (is_int(stripos($message, '/set '))) {
	$setup_array = explode(" ", substr($message, 5), 2);
	$chat_to_setup = $setup_array[0];
	$message_to_setup = $setup_array[1];

	mysqli_query($db, "update main set welcome_message_text='".$message_to_setup."' where chat_id=".$chat_to_setup);
	sendMessage($chat_id, "Сообщение \n\n".$message_to_setup."\n\n для `".$chat_to_setup."` установлено.");
}

if ($new_user) {
	$query = mysqli_query($db, 'select chat_id from main where chat_id='.$chat_id);
	while ($sql = mysqli_fetch_object($query)) {
		$sql_chat_id = $sql->chat_id;
	}
	
	if ($sql_chat_id == $chat_id) {
		$query = mysqli_query($db, 'select welcome_message_text from main where chat_id='.$chat_id);
		while ($sql = mysqli_fetch_object($query)) {
			$welcome_message = $sql->welcome_message_text;
		}
		sendWelcomeMessage($chat_id, $welcome_message, $message_id);
	} else {
		sendWelcomeMessage($chat_id, "Привет!", $message_id);
	}

	mysqli_free_result($sql);
}

if (is_int(stripos($message, '/mysql'))) {
	$query = substr($message, 7);
	mysqli_query($db, $query);
	sendMessage($chat_id, 'Доне, '.$query);
}

//----------------------------------------------------------------------------------------------------------------------------------//

//отправка форматированного сообщения
function sendMessage($chat_id, $message) {
	file_get_contents($GLOBALS['api'].'/sendMessage?chat_id='.$chat_id.'&text='.urlencode($message).'&parse_mode=Markdown');
}

function sendWelcomeMessage($chat_id, $message, $new_member_message_id) {
	file_get_contents($GLOBALS['api'].'/sendMessage?chat_id='.$chat_id.'&text='.urlencode($message).'&parse_mode=Markdown'.'&reply_to_message_id='.$new_member_message_id);
}

function deleteMessage($chat_id, $message_id) {
	file_get_contents($GLOBALS['api'].'/deleteMessage?chat_id='.$chat_id.'&message_id='.$message_id);
}

mysqli_close($db);
echo "End script."
?>