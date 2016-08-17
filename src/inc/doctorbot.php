<?php
/** Posting of the bot */

if(!defined('ABSPATH')) die; // Die if accessed directly

/** == Main command == **/
function gwptb_diet_command_response($upd_data){
	global $wpdb;

	$result = array();
	
	$user_id = (int)$upd_data['user_id'];
	$chat_id = (int)$upd_data['chat_id'];
	
	if($chat_id && !$user_id) {
	    $user_id = $chat_id;
	}

	$user_data = doctorbot_get_user_data($user_id);
	if(!$user_data) {
            $data = array('user_id' => $user_id, 'chat_id' => $chat_id, 'moment' => current_time( 'mysql' ), 'step' => 'name');
            $wpdb->insert('wp_doctorbot_users', $data, array('%d', '%d', '%s', '%s',));

	    $user_data = doctorbot_get_user_data($user_id);
	}
	$user_diet = doctorbot_get_user_diet($user_id);
	if(!$user_diet) {
	    $data = array('user_id' => $user_id);
            $wpdb->insert('wp_doctorbot_users_diet', $data, array('%d',));
	    $user_diet = doctorbot_get_user_diet($user_id);
	}
	
	$self = Gwptb_Self::get_instance();
	$command_content = trim(str_replace(array('@', '/diet', $self->get_self_username()), '', $upd_data['content']));
	//return ['text' => print_r($upd_data, true)];

	if(!$user_id || !$chat_id || !$user_data) {
	    $result['text'] = 'Unknown command';
	}
	elseif(!$command_content){ //update - store notification meta
	    if(0) {
	    }
	    else {
		$wpdb->query($wpdb->prepare("DELETE FROM wp_doctorbot_users WHERE user_id = %d", $user_id));
		$wpdb->query($wpdb->prepare("DELETE FROM wp_doctorbot_users_diet WHERE user_id = %d", $user_id));
		
		$data = array('user_id' => $user_id, 'chat_id' => $chat_id, 'moment' => current_time( 'mysql' ), 'step' => 'name');
		$wpdb->insert('wp_doctorbot_users', $data, array('%d', '%d', '%s', '%s',));
		$user_data = doctorbot_get_user_data($user_id);
		$data = array('user_id' => $user_id);
		$wpdb->insert('wp_doctorbot_users_diet', $data, array('%d',));
		$user_diet = doctorbot_get_user_diet($user_id);

		$result['text'] = "Привет! Мне нужны данные, чтобы составить диету. Как вас зовут?";
		}
	}
	elseif($command_content) {
	    $value = str_replace('diet=', '', $upd_data['content']);
	    $value = $value;
	    $result['text'] = $value;
	    //return $result;

	    $update_data = null;
	    if(in_array($value, ['a_no', 'a_less3', 'a_more3'])) {
		$update_data = [
		    'a_no' => 0,
		    'a_less3' => 0,
		    'a_more3' => 0,
		];
		$update_data[$value] = 1;
	    }
	    elseif(in_array($value, ['b_yes', 'b_no'])) {
		$update_data = ['b' => $value == 'b_yes' ? 1 : 0];
	    }
	    elseif(in_array($value, ['c_yes', 'c_no'])) {
		$update_data = ['c' => $value == 'c_yes' ? 1 : 0];
	    }
	    elseif(in_array($value, ['d_yes', 'd_no'])) {
		$update_data = ['d' => $value == 'd_yes' ? 1 : 0];
	    }
	    elseif(in_array($value, ['e_yes', 'e_no'])) {
		$update_data = ['e' => $value == 'e_yes' ? 1 : 0];
	    }

	    if($update_data) {
		$wpdb->update('wp_doctorbot_users_diet', $update_data, ['user_id' => $user_id], ['%d',], ['%d',]);
	    }	    
	    
	    $step = $user_data->step;
	    //return ['text' => $step];
	    doctorbot_save_step_data($user_id, $step, $upd_data);
	    $next_step = doctorbot_get_next_step($step);
	    //return ['text' => $next_step . " - ok"];
	    $wpdb->update('wp_doctorbot_users', ['step' => $next_step], ['user_id' => $user_id], ["%s",], ["%d",]);
	    if(is_valid_raw_step($next_step)) {
        	    $result['text'] = doctorbot_get_step_title($next_step);
	    }
	    elseif(is_valid_choose_step($next_step)) {
		//return ['text' => 'choose_step'];
		$result = diet_show_step_ui($next_step);
	    }
	    elseif($next_step == 'done') {
		$user_diet = doctorbot_get_user_diet($user_id);

		$result_text = "Вам можно есть:\n";

		$food = doctorbot_get_user_food($user_diet);
		$good_food = @$food['good'];
		$bad_food = @$food['bad'];

		$food_ok = "";
		foreach($good_food as $food) {
		    $food_ok .= $food->name . "\n";
		}

		//$food_ok = "брокколи\n";
		$result_text .= $food_ok . "\n";

		$result_text .= "Вам нельзя есть: \n";

		$food_bad = "";
		foreach($bad_food as $food) {
		    $food_bad .= $food->name . "\n";
		}

		//$food_bad .= "свинина\n";
		$result_text .= $food_bad;

		$result['text'] = $result_text;
	    }

	    //return ['text' => print_r($result, true)];
	}
		
	$result['parse_mode'] = 'HTML';
		
	return $result;
}

function doctorbot_get_user_food($user_diet) {
    global $wpdb;
    $good_food = [];
    $bad_food = [];
    $sql = "SELECT * FROM wp_doctorbot_products";
    $source_food = $wpdb->get_results($wpdb->prepare($sql));
    
    foreach($source_food as $f) {
	$is_ok = true;
	if($user_diet->a_less3 && !$f->a_less3) {
	    $is_ok = false;
	}
	if($user_diet->a_more3 && !$f->a_more3) {
	    $is_ok = false;
	}
	if($user_diet->b && !$f->b) {
	    $is_ok = false;
	}
	if($user_diet->c && !$f->c) {
	    $is_ok = false;
	}
	if($user_diet->d && !$f->d) {
	    $is_ok = false;
	}
	if($user_diet->e && !$f->e) {
	    $is_ok = false;
	}

	if($is_ok) {
	    $good_food[] = $f;
	}
	else {
	    $bad_food[] = $f;
	}
    }

    return ['good' => $good_food, 'bad' => $bad_food];
}

function doctorbot_get_user_data($user_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_doctorbot_users WHERE user_id = %d ", $user_id));
}

function doctorbot_get_user_diet($user_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_doctorbot_users_diet WHERE user_id = %d ", $user_id));
}

function gwptb_doctorbot_input_command_response($upd_data) {
    global $wpdb;

    $result = array();
    $user_id = (int)$upd_data['user_id'];
    $chat_id = (int)$upd_data['chat_id'];

    $user_data = doctorbot_get_user_data($user_id);

    if(!$user_data) {
	return ['text' => 'Неизвестная команда. Выполните /help чтобы увидеть доступные команды.'];
    }

    $step = $user_data->step;
    if($step == 'done') {
	return ['text' => 'Неизвестная команда. Выполните /help чтобы увидеть доступные команды.'];
    }


    //return ['text' => $step];
    doctorbot_save_step_data($user_id, $step, $upd_data);
    $next_step = doctorbot_get_next_step($step);
    //return ['text' => $next_step . " - ok"];
    $wpdb->update('wp_doctorbot_users', ['step' => $next_step], ['user_id' => $user_id], ["%s",], ["%d",]);
    if(is_valid_raw_step($next_step)) {
	$result['text'] = doctorbot_get_step_title($next_step);
    }
    elseif(is_valid_choose_step($next_step)) {
	$result = diet_show_step_ui($next_step);
    }

    $result['parse_mode'] = 'HTML';

    //return ['text' => print_r($result, true)];
    return $result;
}

function doctorbot_get_step_title($step) {
    $titles = [
	'name' => '',
	'age' => "Очень приятно!\nСколько вам лет?",
	'gender' => 'Укажите ваш пол',
	'height' => 'Ваш рост',
	'weight' => 'И ваш вес',
    ];
    return isset($titles[$step]) ? $titles[$step] : '';
}

function is_valid_raw_step($step) {
    return in_array($step, ['name', 'age', 'gender', 'height', 'weight']);
}

function is_valid_choose_step($step) {
    return in_array($step, ['a', 'b', 'c', 'd', 'e']);
}

function doctorbot_save_step_data($user_id, $step, $upd_data) {
    global $wpdb;
    if(is_valid_raw_step($step)) {
	$wpdb->update('wp_doctorbot_users', [$step => $upd_data['content']], ['user_id' => $user_id], ["%s",], ["%d",]);
    }
    elseif(in_array($step, ['a', 'b', 'c', 'd', 'e'])) {
	//$wpdb->update('wp_doctorbot_users_diet', [ => $next_step], ['user_id' => $user_id], ["%s",], ["%d",]);
    }
}

function doctorbot_get_next_step($step) {
    $steps = ['name', 'age', 'gender', 'height', 'weight', 'a', 'b', 'c', 'd', 'e', 'done'];
    $index = array_search($step, $steps);
    $next_step = $step;
    if($index !== false && isset($steps[$index + 1])) {
	$next_step = $steps[$index + 1];
    }
    return $next_step;
    //return 'done';
    //return 'a';
}

function diet_show_step_ui($step) {
    $action_name = 'diet_step_' . $step; 
    $step_result = null;
    //return ['text' => $action_name];
    if(function_exists($action_name)) {
        $step_result = call_user_func($action_name);
    }
    return $step_result;
}

function diet_step_a() {
    $keys = array('inline_keyboard' => array());
    $keys['inline_keyboard'][0][] = array('text' => 'Менее 3-х месяцев назад', 'callback_data' => 'diet=a_less3');
    $keys['inline_keyboard'][1][] = array('text' => 'Более 3-х месяцев назад', 'callback_data' => 'diet=a_more3');
    $keys['inline_keyboard'][2][] = array('text' => 'Инсульта не было. Профилактика.', 'callback_data' => 'diet=a_no');
    $result = [];
    $result['reply_markup'] = json_encode($keys);
    $result['text'] = 'Когда был перенесен инсульт?';
    return $result;
}


function diet_step_b() {
    $keys = array('inline_keyboard' => array());
    $keys['inline_keyboard'][0][] = array('text' => 'Да', 'callback_data' => 'diet=b_yes');
    $keys['inline_keyboard'][1][] = array('text' => 'Нет', 'callback_data' => 'diet=b_no');
    $result = [];
    $result['reply_markup'] = json_encode($keys);
    $result['text'] = 'Страдаете ли вы гипертонической болезнью?';
    return $result;
}

function diet_step_c() {
    $keys = array('inline_keyboard' => array());
    $keys['inline_keyboard'][0][] = array('text' => 'Да', 'callback_data' => 'diet=c_yes');
    $keys['inline_keyboard'][1][] = array('text' => 'Нет', 'callback_data' => 'diet=c_no');
    $result = [];
    $result['reply_markup'] = json_encode($keys);
    $result['text'] = 'Страдаете ли сахарным диабетом?';
    return $result;
}

function diet_step_d() {
    $keys = array('inline_keyboard' => array());
    $keys['inline_keyboard'][0][] = array('text' => 'Да', 'callback_data' => 'diet=d_yes');
    $keys['inline_keyboard'][1][] = array('text' => 'Нет', 'callback_data' => 'diet=d_no');
    $result = [];
    $result['reply_markup'] = json_encode($keys);
    $result['text'] = 'Повышен ли у вас холестерин?';
    return $result;
}

function diet_step_e() {
    $keys = array('inline_keyboard' => array());
    $keys['inline_keyboard'][0][] = array('text' => 'Да', 'callback_data' => 'diet=e_yes');
    $keys['inline_keyboard'][1][] = array('text' => 'Нет', 'callback_data' => 'diet=e_no');
    $result = [];
    $result['reply_markup'] = json_encode($keys);
    $result['text'] = 'Страдаете ли вы ожирением?';
    return $result;
}

