<?php
ini_set('display_errors', 1);
include 'config.php';
header('Content-Type: text/html; charset=utf-8');

$api = 'https://api.telegram.org/bot'.$tg_bot_token;

$input = file_get_contents('php://input');
$output = json_decode($input, TRUE); //сюда приходят все запросы по вебхука

//телеграмные события
$chat_id = isset($output['message']['chat']['id']) ? $output['message']['chat']['id'] : 'chat_id_empty'; //отделяем id чата, откуда идет обращение к боту
$chat = isset($output['message']['chat']['title']) ? $output['message']['chat']['title'] : 'chat_title_empty';
$chat_type = isset($output['message']['chat']['type']) ? $output['message']['chat']['type'] : 'chat_type_empty';
$new_chat_title = isset($output['message']['new_chat_title']) ? $output['message']['new_chat_title'] : 'new_chat_title_empty';
$message = isset($output['message']['text']) ? $output['message']['text'] : 'message_text_empty'; //сам текст сообщения
$user = isset($output['message']['from']['username']) ? $output['message']['from']['username'] : 'origin_user_empty';
$user_language_code = isset($output['message']['from']['language_code']) ? $output['message']['from']['language_code'] : 'no_language_set';
$user_id = isset($output['message']['from']['id']) ? $output['message']['from']['id'] : 'origin_user_id_empty';
$message_id = isset($output['message']['message_id']) ? $output['message']['message_id'] : 'message_id_empty';
$new_user = isset($output['message']['new_chat_members']) ? $output['message']['new_chat_members'] : 'new_user_empty';
$migrated_from = isset($output['message']['migrate_from_chat_id']) ? $output['message']['migrate_from_chat_id'] : 'no_migration';
$migrated_to = isset($output['message']['migrate_to_chat_id']) ? $output['message']['migrate_to_chat_id'] : 'no_migration';
$sticker = isset($output['message']['sticker']) ? $output['message']['sticker'] : 'no_sticker';

$callback_query = isset($output['callback_query']) ? $output['callback_query'] : 'callback_query_empty'; //сюда получаем все, что приходит от inline клавиатуры
$callback_id = isset($callback_query['id']) ? $callback_query['id'] : 'callback_id_empty';
$callback_data = isset($callback_query['data']) ? $callback_query['data'] : 'callback_data_empty'; //ответ от клавиатуры идет сюда
$callback_chat_id = isset($callback_query['message']['chat']['id']) ? $callback_query['message']['chat']['id'] : 'callback_chat_id_empty'; //id чата, где был вызов клавиатуры
$callback_user_id = isset($callback_query['from']['id']) ? $callback_query['from']['id'] : 'callback_user_id_empty'; //id чата, где был вызов клавиатуры
$callback_message_text = isset($callback_query['message']['text']) ? $callback_query['message']['text'] : 'callback_message_text_empty'; //оригинальное сообщение с клавой
$callback_message_id = isset($callback_query['message']['message_id']) ? $callback_query['message']['message_id'] : 'callback_message_id_empty'; //id того сообщения, в котором нажата кнопка клавиатуры

echo "Init successful.\n";

//----------------------------------------------------------------------------------------------------------------------------------//

$markdownify_array = [
	//In all other places characters '_‘, ’*‘, ’[‘, ’]‘, ’(‘, ’)‘, ’~‘, ’`‘, ’>‘, ’#‘, ’+‘, ’-‘, ’=‘, ’|‘, ’{‘, ’}‘, ’.‘, ’!‘ must be escaped with the preceding character ’\'.
	'>' => "\>",
	'#' => "\#",
	'+' => "\+",
	'-' => "\-",
	'=' => "\=",
	'|' => "\|",
	'{' => "\{",
	'}' => "\}",
	'.' => "\.",
	'!' => "\!"
];

if ($message == '/start') {
	switch ($user_language_code) {
		case 'ru':
			sendMessage($chat_id, "Для настройки приветственных сообщений \- добавьте меня в чат и наберите там /init\.", NULL);
			break;

		case 'en':
			sendMessage($chat_id, "To set your custom welcome message \- add me in the chat and type /init there\.", NULL);
			break;
		
		default:
			sendMessage($chat_id, "To set your custom welcome message \- add me in the chat and type /init there\.", NULL);
	}
}

if ($message == '/init') {
	$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
	mysqli_set_charset($db, 'utf8mb4');
	mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
	if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
		else echo "MySQL connect successful.\n";

	deleteMessage($chat_id, $message_id);
	if ($chat_id > 0) {
		switch ($user_language_code) {
			case 'ru':
				sendMessage($chat_id, "Нельзя настроить приветственное сообщение в личном чате :\)\n\nДобавь меня в группу и набери там /init для настройки\!", NULL);
				break;

			case 'en':
				sendMessage($chat_id, "You can't set welcome message inprivate chat :\)\n\nAdd me in your chat and enter /init there\!", NULL);
				break;

			default:
				sendMessage($chat_id, "You can't set welcome message inprivate chat :\)\n\nAdd me in your chat and enter /init there\!", NULL);
		}
	} else {
		$query = mysqli_query($db, 'select chat_owner_user_id from main where chat_id='.$chat_id);
		while ($sql = mysqli_fetch_object($query)) {
			$owner_id = $sql->chat_owner_user_id;
		}
		if (($owner_id == $user_id) || ($owner_id === NULL)) {
			$query = mysqli_query($db, 'select chat_id from main where chat_id='.$chat_id);
			while ($sql = mysqli_fetch_object($query)) {
				$sql_chat_id = $sql->chat_id;
			}
			if ($sql_chat_id == $chat_id) {
				$query = mysqli_query($db, 'select welcome_message_text from main where chat_id='.$chat_id);
				while ($sql1 = mysqli_fetch_object($query)) {
					$welcome_message = strtr($sql1->welcome_message_text, $markdownify_array);
				}
				$query = mysqli_query($db, "update main set chat_title='".filter_var($chat, FILTER_SANITIZE_ADD_SLASHES)."' where chat_id=".$chat_id);
				switch ($user_language_code) {
					case 'ru':
						sendMessage($user_id, "Сообщение для чата *".strtr($chat, $markdownify_array)."* уже настроено\!\nТекущее сообщение:\n\n".$welcome_message."\n\nДля изменения приветственного сообщения используйте команду /mychats", NULL);
						break;
		
					case 'en':
						sendMessage($user_id, "Welcome message for chat *".strtr($chat, $markdownify_array)."* is already set\!\nCurrent message:\n\n".$welcome_message."\n\nTo edit message contents use /mychats", NULL);
						break;

					default:
						sendMessage($user_id, "Welcome message for chat *".strtr($chat, $markdownify_array)."* is already set\!\nCurrent message:\n\n".$welcome_message."\n\nTo edit message contents use /mychats", NULL);
				}
			} else {
				mysqli_query($db, "insert into main (chat_id, chat_title, chat_owner_user_id) values (".$chat_id.", '".filter_var($chat, FILTER_SANITIZE_ADD_SLASHES)."', ".$user_id.")");
				switch ($user_language_code) {
					case 'ru':
						sendMessage($user_id, "Вы включили приветственные сообщения для *".strtr($chat, $markdownify_array)."*\!\nЧтобы задать своё приветствие, используйте меню /mychats\n\nВ дальнейшем, изменить приветствие для чата сможете только вы\.\n\nПриветственные сообщения поддерживают форматирование и эмодзи\.\nДля поддержки пишите @mrsnowball", NULL);
						break;
		
					case 'en':
						sendMessage($user_id, "You enabled custom welcome messages for *".strtr($chat, $markdownify_array)."*\!\nTo edit your welcome message, use /mychats command\.\n\nOnly you are able to edit messages you set\.\n\nEmojis and formatting are supported inside welcome messages\.\nSupport: @mrsnowball", NULL);
						break;

					default:
						sendMessage($user_id, "You enabled custom welcome messages for *".strtr($chat, $markdownify_array)."*\!\nTo edit your welcome message, use /mychats command\.\n\nOnly you are able to edit messages you set\.\n\nEmojis and formatting are supported inside welcome messages\.\nSupport: @mrsnowball", NULL);
				}
			}
		} else {
			sendMessage($chat_id, "_У вас нет прав на изменение приветственных сообщений для этого чата\!\nТекущий владелец доступен по [ссылке](tg://user?id=".$owner_id.")\._", NULL);
		}
	}
	mysqli_free_result($sql);
	mysqli_close($db);
}

if ((is_int(stripos($message, '/set '))) && ($chat_id > 0)) {
	switch ($user_language_code) {
		case 'ru':
			sendMessage($chat_id, "Команда больше не поддерживается, используйте меню /mychats для установки приветствий\.", NULL);
			break;

		case 'en':
			sendMessage($chat_id, "This command is no longer supported, use /mychats command to edit welcome messages\.", NULL);
			break;

		default:
			sendMessage($chat_id, "This command is no longer supported, use /mychats command to edit welcome messages\.", NULL);
	}
}

if ($new_user !== 'new_user_empty') {
	$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
	mysqli_set_charset($db, 'utf8mb4');
	mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
	if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
		else echo "MySQL connect successful.\n";
	
	$query = mysqli_query($db, 'select chat_id from main where chat_id='.$chat_id);
	while ($sql = mysqli_fetch_object($query)) {
		$sql_chat_id = $sql->chat_id;
	}
	
	if ($sql_chat_id == $chat_id) {
		$query = mysqli_query($db, 'select welcome_message_text from main where chat_id='.$chat_id);
		while ($sql = mysqli_fetch_object($query)) {
			$welcome_message = strtr($sql->welcome_message_text, $markdownify_array);
		}
		mysqli_query($db, 'update main set welcome_count=welcome_count+1, last_joined=CURRENT_TIMESTAMP where chat_id='.$chat_id);
		sendWelcomeMessage($chat_id, $welcome_message, $message_id);
	} else {
		sendWelcomeMessage($chat_id, "Привет\!", $message_id);
	}

	mysqli_free_result($sql);
	mysqli_close($db);
}

if ($message == '/settings' || $message == '/settings@welcome_ng_bot') {
	if ($chat_type == 'private') {
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";
		
		$query = mysqli_query($db, 'select distinct language from main where chat_owner_user_id='.$user_id);
		while ($sql = mysqli_fetch_object($query)) {
			$language = $sql->language;
		}
	
		switch ($language) {
			case 'ru':
				$language_switcher_keyboard = ['inline_keyboard' => [
					[['text' => 'Change to 🇺🇸 English', 'callback_data' => 'lang_switch_to_en']]
				]];
				sendMessage($chat_id, "_Ваши настройки:_\n\nЯзык: 🇷🇺 Русский", $language_switcher_keyboard);
				break;
	
			case 'en':
				$language_switcher_keyboard = ['inline_keyboard' => [
					[['text' => 'Сменить на 🇷🇺 Русский', 'callback_data' => 'lang_switch_to_ru']]
				]];
				sendMessage($chat_id, "_Your settings:_\n\nLanguage: 🇺🇸 English", $language_switcher_keyboard);
				break;
		}
		mysqli_free_result($sql);
		mysqli_close($db);
	} else {
		switch ($user_language_code) {
			case 'ru':
				sendWelcomeMessage($chat_id, "_Эта команда доступна только в личном чате с ботом\._", $message_id);
				break;
			
			default:
				sendWelcomeMessage($chat_id, "_This command is only for private chat with the bot\._", $message_id);
				break;
		}
	}
}


if ($new_chat_title !== 'new_chat_title_empty') {
	$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
	mysqli_set_charset($db, 'utf8mb4');
	mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
	if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
		else echo "MySQL connect successful.\n";

	mysqli_query($db, "update main set chat_title='".filter_var($new_chat_title, FILTER_SANITIZE_ADD_SLASHES)."' where chat_id=".$chat_id);
	mysqli_close($db);
}

if ($migrated_from !== 'no_migration') {
	$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
	mysqli_set_charset($db, 'utf8mb4');
	mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
	if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
		else echo "MySQL connect successful.\n";
	
	mysqli_query($db, 'update main set chat_id='.$migrated_to.' where chat_id='.$migrated_from);
	mysqli_close($db);
}

if ($message == '/mychats' || $message == '/mychats@welcome_ng_bot') {
	if ($chat_type == 'private') {
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";
	
		$query = mysqli_query($db, 'select chat_id, chat_title, language from main where chat_owner_user_id='.$user_id);
		while ($sql = mysqli_fetch_object($query)) {
			$language = $sql->language;
			if ($language == 'ru') {
				$menu_chat[] = [['text' => $sql->chat_title, 'callback_data' => 'chat_selected_ru:'.$sql->chat_id]];
			} else {
				$menu_chat[] = [['text' => $sql->chat_title, 'callback_data' => 'chat_selected_en:'.$sql->chat_id]];
			}
		}
	
		$menu_keyboard_chat_list = ['inline_keyboard' => $menu_chat];
	
		switch ($language) {
			case 'ru':
				sendMessage($chat_id, "Список чатов, где вы настроили приветствия:", $menu_keyboard_chat_list);
				break;
	
			case 'en':
				sendMessage($chat_id, "Here's the list of chats where you set up welcome messages:", $menu_keyboard_chat_list);
				break;
		}

		mysqli_close($db);
	} else {
		switch ($user_language_code) {
			case 'ru':
				sendWelcomeMessage($chat_id, "_Эта команда доступна только в личном чате с ботом\._", $message_id);
				break;
			
			default:
				sendWelcomeMessage($chat_id, "_This command is only for private chat with the bot\._", $message_id);
				break;
		}
	}
}







if ($message && $chat_id > 0) {
	$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
	mysqli_set_charset($db, 'utf8mb4');
	mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
	if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
		else echo "MySQL connect successful.\n";

	$query = mysqli_query($db, 'select settings_step, chat_id, language from main where chat_owner_user_id='.$user_id);
	while ($sql = mysqli_fetch_object($query)) {
		$current_chat_id = $sql->chat_id;
		$current_step = $sql->settings_step;
		$current_language = $sql->language;
	}

	if ($current_step == 'edit_chat_entering_new_message') {
		mysqli_query($db, "update main set welcome_message_text='".$message."', settings_step='chat_list' where chat_id=".$current_chat_id." and chat_owner_user_id=".$user_id);
		switch ($current_language) {
			case 'ru':
				$edit_success_keyboard = ['inline_keyboard' => [
					[['text' => '⬅ Назад к списку чатов', 'callback_data' => 'back_to_list:'.$current_chat_id]]
				]];
				sendMessage($chat_id, "Готово\! Новое сообщение установлено\.", $edit_success_keyboard);
				break;

			case 'en':
				$edit_success_keyboard = ['inline_keyboard' => [
					[['text' => '⬅ Back to chat list', 'callback_data' => 'back_to_list:'.$current_chat_id]]
				]];
				sendMessage($chat_id, "Done\! New message has been set\.", $edit_success_keyboard);
				break;
		}
	}

	mysqli_close($db);
}







$callback_data = explode(':', $callback_data);
switch ($callback_data[0]) {
	case 'callback_data_empty':
		break;

	case 'lang_switch_to_ru':
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, 'update main set language=\'ru\' where chat_owner_user_id='.$callback_user_id);
		$language_switcher_keyboard = ['inline_keyboard' => [
			[['text' => 'Change to 🇺🇸 English', 'callback_data' => 'lang_switch_to_en']]
		]];
		updateMessage($callback_chat_id, $callback_message_id, "_Ваши настройки:_\n\nЯзык: 🇷🇺 Русский", $language_switcher_keyboard);

		mysqli_close($db);
		break;

	case 'lang_switch_to_en':
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, 'update main set language=\'en\' where chat_owner_user_id='.$callback_user_id);
		$language_switcher_keyboard = ['inline_keyboard' => [
			[['text' => 'Сменить на 🇷🇺 Русский', 'callback_data' => 'lang_switch_to_ru']]
		]];
		updateMessage($callback_chat_id, $callback_message_id, "_Your settings:_\n\nLanguage: 🇺🇸 English", $language_switcher_keyboard);

		mysqli_close($db);
		break;







	case 'chat_selected_ru':
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, "update main set settings_step='chat_selected' where chat_id=".$callback_data[1]);
		$query = mysqli_query($db, "select chat_title, welcome_message_text from main where chat_id=".$callback_data[1]);
		while ($sql = mysqli_fetch_object($query)) {
			$selected_chat_title = strtr($sql->chat_title, $markdownify_array);
			$selected_chat_message = strtr($sql->welcome_message_text, $markdownify_array);
		}
		$chat_selected_keyboard = ['inline_keyboard' => [
			[['text' => '✏ Изменить', 'callback_data' => 'edit_chat_ru:'.$callback_data[1]], ['text' => '❌ Удалить', 'callback_data' => 'delete_chat_ru:'.$callback_data[1]]],
			[['text' => '⬅ Назад к списку чатов', 'callback_data' => 'back_to_list:'.$callback_data[1]]]
		]];
		updateMessage($callback_chat_id, $callback_message_id, "_Чат:_\n*".$selected_chat_title."*\n\n_Текущее сообщение:_\n".$selected_chat_message, $chat_selected_keyboard);

		mysqli_free_result($sql);
		mysqli_close($db);
		break;

	case 'chat_selected_en':
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, "update main set settings_step='chat_selected' where chat_id=".$callback_data[1]);
		$query = mysqli_query($db, "select chat_title, welcome_message_text from main where chat_id=".$callback_data[1]);
		while ($sql = mysqli_fetch_object($query)) {
			$selected_chat_title = strtr($sql->chat_title, $markdownify_array);
			$selected_chat_message = strtr($sql->welcome_message_text, $markdownify_array);
		}
		$chat_selected_keyboard = ['inline_keyboard' => [
			[['text' => '✏ Edit', 'callback_data' => 'edit_chat_en:'.$callback_data[1]], ['text' => '❌ Delete', 'callback_data' => 'delete_chat_en:'.$callback_data[1]]],
			[['text' => '⬅ Back to chat list', 'callback_data' => 'back_to_list:'.$callback_data[1]]]
		]];
		updateMessage($callback_chat_id, $callback_message_id, "_Chat:_\n*".$selected_chat_title."*\n\n_Welcome message:_\n".$selected_chat_message, $chat_selected_keyboard);

		mysqli_free_result($sql);
		mysqli_close($db);
		break;








	case 'edit_chat_ru':
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, "update main set settings_step='edit_chat_entering_new_message' where chat_id=".$callback_data[1]);
		$query = mysqli_query($db, "select chat_title, welcome_message_text from main where chat_id=".$callback_data[1]);
		while ($sql = mysqli_fetch_object($query)) {
			$selected_chat_title = strtr($sql->chat_title, $markdownify_array);
			$selected_chat_message = strtr($sql->welcome_message_text, $markdownify_array);
		}

		$cancel_new_message_keyboard = ['inline_keyboard' => [
			[['text' => '❌ Назад к чату', 'callback_data' => 'chat_selected_ru:'.$callback_data[1]]]
		]];

		updateMessage($callback_chat_id, $callback_message_id, 
		"Хорошо\! Отправьте следующим сообщением то, что вы хотите видеть в качестве приветствия\.\n\nПодсказка по форматированию:\n\*текст\* \- выделение жирным\n\_текст\_ \- выделение курсивом\n\\\ \`текст\\\ \` \- моноширинный текст\n\~текст\~ \- зачеркнутый текст\n\\\ \_\_текст\\\ \_\_ \- подчеркнутый текст\n\[текст\]\(ссылка\) \- вставка ссылки\nЭмодзи поддерживаются\. Форматирование совместимо с MarkdownV2\.\n\n_Текущее сообщение:_\n".$selected_chat_message, $cancel_new_message_keyboard);

		mysqli_free_result($sql);
		mysqli_close($db);
		break;

	case 'edit_chat_en':
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, "update main set settings_step='edit_chat_entering_new_message' where chat_id=".$callback_data[1]);
		$query = mysqli_query($db, "select chat_title, welcome_message_text from main where chat_id=".$callback_data[1]);
		while ($sql = mysqli_fetch_object($query)) {
			$selected_chat_title = strtr($sql->chat_title, $markdownify_array);
			$selected_chat_message = strtr($sql->welcome_message_text, $markdownify_array);
		}

		$cancel_new_message_keyboard = ['inline_keyboard' => [
			[['text' => '❌ Back to chat', 'callback_data' => 'chat_selected_ru:'.$callback_data[1]]]
		]];

		updateMessage($callback_chat_id, $callback_message_id, 
		"Good\! Now send me your desired welcome in the next message\.\n\nFormatting guidelines:\n\*text\* \- bold\n\_text\_ \- italic\n\\\ \`text\\\ \` \- monospace text\n\~text\~ \- strikethrough text\n\\\ \_\_text\\\ \_\_ \- underline text\n\[text\]\(link\) \- insert link\nEmojis and MarkdownV2 are supported\.\n\n_Current message:_\n".$selected_chat_message, $cancel_new_message_keyboard);

		mysqli_free_result($sql);
		mysqli_close($db);
		break;








	case 'delete_chat_ru':
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, "update main set settings_step='delete_chat' where chat_id=".$callback_data[1]);
		$query = mysqli_query($db, "select chat_title from main where chat_id=".$callback_data[1]);
		while ($sql = mysqli_fetch_object($query)) {
			$delete_prompt_chat_title = strtr($sql->chat_title, $markdownify_array);
		}
		$delete_prompt_keyboard = ['inline_keyboard' => [
			[['text' => '✅ Да', 'callback_data' => 'delete_chat_confirm_ru:'.$callback_data[1]], ['text' => '❌ Нет', 'callback_data' => 'chat_selected_ru:'.$callback_data[1]]],
			[['text' => '⬅ Назад к списку чатов', 'callback_data' => 'back_to_list:'.$callback_data[1]]]
		]];
		updateMessage($callback_chat_id, $callback_message_id, "_Вы действительо хотите удалить чат:_\n".$delete_prompt_chat_title."?\n\nБот покинет чат и приветсвенныхх сообщений больше не будет\.", $delete_prompt_keyboard);

		mysqli_free_result($sql);
		mysqli_close($db);
		break;
	
	case 'delete_chat_en':
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, "update main set settings_step='delete_chat' where chat_id=".$callback_data[1]);
		$query = mysqli_query($db, "select chat_title from main where chat_id=".$callback_data[1]);
		while ($sql = mysqli_fetch_object($query)) {
			$delete_prompt_chat_title = strtr($sql->chat_title, $markdownify_array);
		}
		$delete_prompt_keyboard = ['inline_keyboard' => [
			[['text' => '✅ Yes', 'callback_data' => 'delete_chat_confirm_en:'.$callback_data[1]], ['text' => '❌ No', 'callback_data' => 'chat_selected_en:'.$callback_data[1]]],
			[['text' => '⬅ Back to chat list', 'callback_data' => 'back_to_list:'.$callback_data[1]]]
		]];
		updateMessage($callback_chat_id, $callback_message_id, "_Are you sure you want to delete chat:_\n".$delete_prompt_chat_title."?\n\nThe bot will leave the chat and there will be no more welcome messages\.", $delete_prompt_keyboard);

		mysqli_free_result($sql);
		mysqli_close($db);
		break;

		




	case 'delete_chat_confirm_ru':
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, "delete from main where chat_id=".$callback_data[1]." and chat_owner_user_id=".$callback_user_id);
		
		$delete_success_keyboard = ['inline_keyboard' => [
			[['text' => '⬅ Назад к списку чатов', 'callback_data' => 'back_to_list:'.$callback_data[1]]]
		]];

		leaveChat($callback_data[1]);
		updateMessage($callback_chat_id, $callback_message_id, "Настройка для чата удалена\.", $delete_success_keyboard);

		mysqli_free_result($sql);
		mysqli_close($db);
		break;

	case 'delete_chat_confirm_en':
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, "delete from main where chat_id=".$callback_data[1]." and chat_owner_user_id=".$callback_user_id);
		
		$delete_success_keyboard = ['inline_keyboard' => [
			[['text' => '⬅ Back to chat list', 'callback_data' => 'back_to_list:'.$callback_data[1]]]
		]];

		leaveChat($callback_data[1]);
		updateMessage($callback_chat_id, $callback_message_id, "Chat settings deleted\.", $delete_success_keyboard);

		mysqli_free_result($sql);
		mysqli_close($db);
		break;


			
	case 'back_to_list':
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, "update main set settings_step='chat_list' where chat_id=".$callback_data[1]);
		$query = mysqli_query($db, 'select chat_id, chat_title, language from main where chat_owner_user_id='.$callback_user_id);
		while ($sql = mysqli_fetch_object($query)) {
			$language = $sql->language;
			if ($language == 'ru') {
				$menu_chat[] = [['text' => $sql->chat_title, 'callback_data' => 'chat_selected_ru:'.$sql->chat_id]];
			} else {
				$menu_chat[] = [['text' => $sql->chat_title, 'callback_data' => 'chat_selected_en:'.$sql->chat_id]];
			}
		}

		$menu_keyboard_chat_list = ['inline_keyboard' => $menu_chat];

		switch ($language) {
			case 'ru':
				updateMessage($callback_chat_id, $callback_message_id, "Список чатов, где вы настроили приветствия:", $menu_keyboard_chat_list);
				break;

			case 'en':
				updateMessage($callback_chat_id, $callback_message_id, "Here's the list of chats where you set up welcome messages:", $menu_keyboard_chat_list);
				break;
		}

		mysqli_free_result($sql);
		mysqli_close($db);
		break;
}







if (is_int(stripos($message, '/notify ')) && $chat_id == '197416875') {
	$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
	mysqli_set_charset($db, 'utf8mb4');
	mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
	if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
		else echo "MySQL connect successful.\n";
	
	$query = mysqli_query($db, 'select distinct chat_owner_user_id from main');
	while ($sql = mysqli_fetch_object($query)) {
		$owners_list[] = $sql->chat_owner_user_id;
	}

	$notify = substr($message, 8);	
	foreach ($owners_list as $id) {
		sendMessage($id, strtr($notify, $markdownify_array), NULL);
	}
	mysqli_free_result($sql);
	mysqli_close($db);
}







if (is_int(stripos($message, '/meme')) && $message !== '/memecount') {
	$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
	if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
		else echo "MySQL connect successful.\n";

	mysqli_query($db, 'update main set memecount=memecount+1 where chat_id=\'-1001268103928\'');
	mysqli_free_result($sql);
	mysqli_close($db);
}

if ($message == '/memecount') {
	$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
	if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
		else echo "MySQL connect successful.\n";

	$query = mysqli_query($db, 'select memecount from main where chat_id=\'-1001268103928\'');
	while ($sql = mysqli_fetch_object($query)) {
		$memecount = $sql->memecount;
	}
	sendMessage($chat_id, 'Every meme count: *'.$memecount.'* memes\.', NULL);
	mysqli_free_result($sql);
	mysqli_close($db);
}

if ($sticker['file_unique_id'] == 'AgADuAADq1fECw' && $chat_id == '-1001268103928') {
	$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
	if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
		else echo "MySQL connect successful.\n";

	mysqli_query($db, 'update main set dubascount=dubascount+1 where chat_id=\'-1001268103928\'');
	
	$dubas_stats = mysqli_fetch_all(mysqli_query($db, "select user_id from dubas_stats where user_id=".$user_id), MYSQLI_ASSOC);
	if (is_null($dubas_stats[0])) {
		mysqli_query($db, "insert into dubas_stats (user_id, dubas_count) values (".$user_id.", 1)");
	} else {
		mysqli_query($db, "update dubas_stats set dubas_count=dubas_count+1 where user_id=".$user_id);
	}

	mysqli_close($db);
}

if ($message == '/dubasivobot' || $message == '/dubascount') {
	$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
	if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
		else echo "MySQL connect successful.\n";

	$query = mysqli_query($db, 'select dubascount from main where chat_id=\'-1001268103928\'');
	while ($sql = mysqli_fetch_object($query)) {
		$dubascount = $sql->dubascount;
	}

	$top10 = mysqli_fetch_all(mysqli_query($db, "select * from dubas_stats order by dubas_count desc limit 10"), MYSQLI_ASSOC);
	$dubasers = "";
	
	foreach ($top10 as $key => $value) {
		$dubas_last_digit = substr($top10[$key]['dubas_count'], -1);
		$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$chat_id.'&user_id='.$top10[$key]['user_id']), TRUE);
		$members[$key] = $member['result']['user']['first_name'];
		if ($dubas_last_digit == 2 || $dubas_last_digit == 3 || $dubas_last_digit == 4) {
			$dub_format = "* раза\n";
		} else {
			$dub_format = "* раз\n";
		}
		switch ($key) {
			case 0:
				$dubasers .= "🥇 *".strtr($members[$key], $markdownify_array)."*: *".$top10[$key]['dubas_count'].$dub_format;
				break;
			
			case 1:
				$dubasers .= "🥈 *".strtr($members[$key], $markdownify_array)."*: *".$top10[$key]['dubas_count'].$dub_format;
				break;
			
			case 2:
				$dubasers .= "🥉 *".strtr($members[$key], $markdownify_array)."*: *".$top10[$key]['dubas_count'].$dub_format;
				break;
				
			default:
				$dubasers .= $key+1 ."\. ".strtr($members[$key], $markdownify_array).": *".$top10[$key]['dubas_count'].$dub_format;
				break;
		}
	}

	sendMessage($chat_id, "Здесь дубасили *".$dubascount."* раз\.\n\nТоп дубасеров:\n".$dubasers, NULL);
	mysqli_free_result($sql);
	mysqli_close($db);
}

if ($message == '/mydubas') {
	$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
	if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
		else echo "MySQL connect successful.\n";

	$query = mysqli_fetch_all(mysqli_query($db, 'select dubas_count from dubas_stats where user_id='.$user_id), MYSQLI_ASSOC);
	$dubas_last_digit = substr($query[0]['dubas_count'], -1);

	if (is_null($query[0])) {
		sendWelcomeMessage($chat_id, "Ты ещё ни разу не дубасил\. За работу\!", $message_id);
	} else {
		if ($dubas_last_digit == 2 || $dubas_last_digit == 3 || $dubas_last_digit == 4) {
			$dub_format = "* раза\n";
		} else {
			$dub_format = "* раз\n";
		}
		sendWelcomeMessage($chat_id, "Ты дубасил *".$query[0]['dubas_count'].$dub_format, $message_id);
	}
}
//----------------------------------------------------------------------------------------------------------------------------------//

//отправка форматированного сообщения
function sendMessage($chat_id, $message, $inline_keyboard) {
	if ($inline_keyboard === NULL) {
		file_get_contents($GLOBALS['api'].'/sendMessage?chat_id='.$chat_id.'&text='.urlencode($message).'&parse_mode=MarkdownV2');
	} else {
		file_get_contents($GLOBALS['api'].'/sendMessage?chat_id='.$chat_id.'&text='.urlencode($message).'&reply_markup='.json_encode($inline_keyboard).'&parse_mode=MarkdownV2');
	}
}

//редактирование сообщения
function updateMessage($chat_id, $message_id, $new_message, $inline_keyboard)
{
	if ($inline_keyboard === NULL) {
		file_get_contents($GLOBALS['api'].'/editMessageText?chat_id='.$chat_id.'&message_id='.$message_id.'&text='.urlencode($new_message).'&parse_mode=MarkdownV2');
	} else {
		file_get_contents($GLOBALS['api'].'/editMessageText?chat_id='.$chat_id.'&message_id='.$message_id.'&text='.urlencode($new_message).'&reply_markup='.json_encode($inline_keyboard).'&parse_mode=MarkdownV2');
		file_get_contents($GLOBALS['api'].'/answerCallbackQuery?callback_query_id='.$GLOBALS['callback_id']);
	}
}

//отправка приветствия
function sendWelcomeMessage($chat_id, $message, $new_member_message_id) {
	file_get_contents($GLOBALS['api'].'/sendMessage?chat_id='.$chat_id.'&text='.urlencode($message).'&parse_mode=MarkdownV2'.'&reply_to_message_id='.$new_member_message_id);
}

//удаление служебного сообщения
function deleteMessage($chat_id, $message_id) {
	file_get_contents($GLOBALS['api'].'/deleteMessage?chat_id='.$chat_id.'&message_id='.$message_id);
}

//покидание чата
function leaveChat($chat_id) {
	file_get_contents($GLOBALS['api'].'/leaveChat?chat_id='.$chat_id);
}

echo "End script."
?>