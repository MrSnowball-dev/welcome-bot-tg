<?php
//ini_set('display_errors', 1);
include 'config.php';
header('Content-Type: text/html; charset=utf-8');

$api = 'https://api.telegram.org/bot'.$tg_bot_token;

$input = file_get_contents('php://input');
$output = json_decode($input, TRUE); //сюда приходят все запросы по вебхука

//телеграмные события
$chat_id = isset($output['message']['chat']['id']) ? $output['message']['chat']['id'] : 'chat_id_empty'; //отделяем id чата, откуда идет обращение к боту
$chat = isset($output['message']['chat']['title']) ? $output['message']['chat']['title'] : 'chat_title_empty';
$message = isset($output['message']['text']) ? $output['message']['text'] : 'message_text_empty'; //сам текст сообщения
$user = isset($output['message']['from']['username']) ? $output['message']['from']['username'] : 'origin_user_empty';
$user_id = isset($output['message']['from']['id']) ? $output['message']['from']['id'] : 'origin_user_id_empty';
$message_id = isset($output['message']['message_id']) ? $output['message']['message_id'] : 'message_id_empty';
$new_user = isset($output['message']['new_chat_members']) ? $output['message']['new_chat_members'] : 'new_user_empty';

echo "Init successful.\n";

//----------------------------------------------------------------------------------------------------------------------------------//

if ($message == '/start') {
	sendMessage($chat_id, "Для настройки приветственных сообщений - добавьте меня в чат и наберите там /init");
}

if ($message == '/init') {
	$db = pg_connect("host=".$db_host." user=".$db_username." password=".$db_pass." dbname=".$db_schema);
	
	$stat = pg_connection_status($db);
	if ($stat === PGSQL_CONNECTION_OK) echo "PGSQL connection is ok";
		else echo "PGSQL failed to connect.\n".$stat;

	if ($check = pg_query($db, 'select * from main')) {
		$count = pg_num_rows($check);
		echo "There is $count records in DB.\n\n";
		pg_free_result($check);
	}
	
	deleteMessage($chat_id, $message_id);
	if ($chat_id > 0) {
		sendMessage($chat_id, "Нельзя настроить приветственное сообщение в личном чате :)\nДобавь меня в группу и набери там /init для настройки!");
	} else {
		$query = pg_query($db, 'select chat_owner_user_id from main where chat_id='.$chat_id);
		while ($sql = pg_fetch_object($query)) {
			$owner_id = $sql->chat_owner_user_id;
		}
		if (($owner_id == $user_id) || ($owner_id === NULL)) {
			$query = pg_query($db, 'select chat_id from main where chat_id='.$chat_id);
			while ($sql = pg_fetch_object($query)) {
				$sql_chat_id = $sql->chat_id;
			}
			if ($sql_chat_id == $chat_id) {
				$query = pg_query($db, 'select welcome_message_text from main where chat_id='.$chat_id);
				while ($sql1 = pg_fetch_object($query)) {
					$welcome_message = $sql1->welcome_message_text;
				}
				sendMessage($user_id, "Сообщение для чата _".$chat."_ уже настроено.\nТекущее сообщение:\n\n".$welcome_message."\n\nДля изменения приветственного сообщения напишите мне\n\n`/set ".$chat_id." <ваше сообщение>`\n^строку можно скопировать");
			} else {
				pg_query($db, "insert into main (chat_id, chat_owner_user_id) values (".$chat_id.", ".$user_id.")");
				sendMessage($user_id, "Вы включили приветственные сообщения для _".$chat."_!\nЧтобы задать своё приветствие, напишите мне\n\n`/set ".$chat_id." <ваше сообщение>`\n^строку можно скопировать\n\nВ дальнейшем, изменить приветствие для чата сможете только вы.\n\nПриветственные сообщения можно форматировать (пока MarkdownV2 не поддерживается). Для этого используйте следующий синтаксис:\n\n\_текст\_ - курсив\n\*текст\* - жирный\n\[текст](ссылка) - для вставки ссылки в форме текста\n\\n - перенос строки\n\nСсылки на пользователей через @ и хештеги # работают как обычно.\nДля поддержки пишите @mrsnowball");
			}
		} else {
			sendMessage($chat_id, "У вас нет прав на изменение приветственных сообщений для этого чата!\nТекущий владелец доступен по [ссылке](tg://user?id=".$owner_id.").");
		}
	}
	pg_free_result($sql);
	pg_close($db);
}

if ((is_int(stripos($message, '/set '))) && ($chat_id > 0)) {
	$db = pg_connect("host=".$db_host." user=".$db_username." password=".$db_pass." dbname=".$db_schema);
	
	$stat = pg_connection_status($db);
	if ($stat === PGSQL_CONNECTION_OK) echo "PGSQL connection is ok";
		else echo "PGSQL failed to connect.\n".$stat;

	if ($check = pg_query($db, 'select * from main')) {
		$count = pg_num_rows($check);
		echo "There is $count records in DB.\n\n";
		pg_free_result($check);
	}

	$setup_array = explode(" ", substr($message, 5), 2);
	$chat_to_setup = $setup_array[0];
	$message_to_setup = $setup_array[1];

	$query = pg_query($db, 'select chat_owner_user_id from main where chat_id='.$chat_to_setup);
	while ($sql = pg_fetch_object($query)) {
		$owner_id = $sql->chat_owner_user_id;
	}
	if (($owner_id == $user_id) || ($owner_id === NULL)) {
		pg_query($db, "update main set welcome_message_text='".$message_to_setup."' where chat_id=".$chat_to_setup);
		sendMessage($chat_id, "Сообщение \n\n".$message_to_setup."\n\n для `".$chat_to_setup."` установлено.");
	} else {
		sendMessage($chat_id, "У вас нет прав на изменение приветственных сообщений для этого чата!\nТекущий владелец доступен по [ссылке](tg://user?id=".$owner.").");
	}
	pg_close($db);
}

if ($new_user !== 'new_user_empty') {
	$db = pg_connect("host=".$db_host." user=".$db_username." password=".$db_pass." dbname=".$db_schema);
	
	$stat = pg_connection_status($db);
	if ($stat === PGSQL_CONNECTION_OK) echo "PGSQL connection is ok";
		else echo "PGSQL failed to connect.\n".$stat;

	if ($check = pg_query($db, 'select * from main')) {
		$count = pg_num_rows($check);
		echo "There is $count records in DB.\n\n";
		pg_free_result($check);
	}

	$query = pg_query($db, 'select chat_id from main where chat_id='.$chat_id);
	while ($sql = pg_fetch_object($query)) {
		$sql_chat_id = $sql->chat_id;
	}
	
	if ($sql_chat_id == $chat_id) {
		$query = pg_query($db, 'select welcome_message_text from main where chat_id='.$chat_id);
		while ($sql = pg_fetch_object($query)) {
			$welcome_message = $sql->welcome_message_text;
		}
		pg_query($db, 'update main set welcome_count=welcome_count+1 where chat_id='.$chat_id);
		sendWelcomeMessage($chat_id, $welcome_message, $message_id);
	} else {
		sendWelcomeMessage($chat_id, "Привет!", $message_id);
	}

	pg_free_result($sql);
	pg_close($db);
}

if (is_int(stripos($message, '/mysql'))) {
	$db = pg_connect("host=".$db_host." user=".$db_username." password=".$db_pass." dbname=".$db_schema);
	
	$stat = pg_connection_status($db);
	if ($stat === PGSQL_CONNECTION_OK) echo "PGSQL connection is ok";
		else echo "PGSQL failed to connect.\n".$stat;

	if ($check = pg_query($db, 'select * from main')) {
		$count = pg_num_rows($check);
		echo "There is $count records in DB.\n\n";
		pg_free_result($check);
	}

	$query = substr($message, 7);
	pg_query($db, $query);
	sendMessage($chat_id, "Доне\n".$query);
}

if ($message == '/meme') {
	$file = 'memecount.txt';
	$memecount = file_get_contents($file);
	$fdata = intval($memecount)+1; // increment the value
	file_put_contents($file, $fdata); // write the new value back to file
}

if ($message == '/memecount') {
	$file = 'memecount.txt';
	$memecount = file_get_contents($file);
	sendMessage($chat_id, '/meme count (since 02.04.2020 0:00): '.intval($memecount).' memes.');
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

echo "End script."
?>
