<?php
/*
Plugin Name:	Kilho's Simple Avatar
Plugin URI:		https://kilho.net
Description:	Add a simple avatar upload field to user profiles for custom profile pictures, with automatic resizing and real-time updates.
Version:		0.9.0
Author:			Kilho Oh
License:		GPL v2 or later
*/

// 다이렉트 접속시 종료
if(!defined( 'ABSPATH')) exit; 

// 사용자 프로필 사진을 표시하는 함수
function kh_avatar_display($description, $user)
{
	ob_start();

	wp_enqueue_script('kilho-simple-avatar', plugins_url('script.js', __FILE__), ['jquery'], '0.9.0', true);
	wp_enqueue_style('kilho-simple-avatar', plugins_url('style.css', __FILE__), [], '0.9.0');
	wp_nonce_field('kh_avatar_upload_action', 'kh_avatar_nonce');

	echo '<p>'.get_avatar($user->ID, 96, '', '', ['class' => 'kh-avatar-img']).'</p>';
	echo '<input type="file" name="kh-avatar-file" id="kh-avatar-file" />';

	return ob_get_clean();
}
add_filter('user_profile_picture_description', 'kh_avatar_display', 10, 2);

// 아바타 업데이트
function kh_avatar_update($user_id)
{
    if(
		!isset($_POST['kh_avatar_nonce']) || 
		!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kh_avatar_nonce'], 'kh_avatar_upload_action')))
	) return;
	if(
		!isset($_FILES['kh-avatar-file']['error']) || 
		$_FILES['kh-avatar-file']['error'] != 0
	) return;

	kh_avatar_delete($user_id);

	$avatar = wp_handle_upload(
		$_FILES['kh-avatar-file'],
		[
			'mimes' => [
			'jpg|jpeg|jpe' => 'image/jpeg',
			'gif' => 'image/gif',
			'png' => 'image/png'
		],
		'test_form' => false,
		'unique_filename_callback' => function($dir, $name, $ext) use ($user_id) { return 'kh-avatar-' . $user_id . '-' . time() . $ext; }
		]
	);

	if(isset($avatar['error'])) return;

	update_user_meta($user_id, 'kh_avatar', esc_url($avatar['url']));
}
add_action('personal_options_update', 'kh_avatar_update');
add_action('edit_user_profile_update', 'kh_avatar_update');

// 아바타 삭제
function kh_avatar_delete($user_id)
{
    $avatars = get_user_meta($user_id, 'kh_avatar', false);
    $upload = wp_upload_dir();

    if(is_array($avatars))
	{
        foreach ($avatars as $avatar)
		{
			$avatar_path = str_replace($upload['baseurl'], $upload['basedir'], $avatar);
            foreach (glob(preg_replace('/(\.\w+)$/', '*$1', $avatar_path)) as $file) wp_delete_file($file);
        }
    }

    delete_user_meta($user_id, 'kh_avatar');
}

// 커스텀 아바타 URL 설정 함수
function kh_pre_get_avatar_data($data, $id_or_email)
{
	$user_id = null;

	if(is_numeric($id_or_email)){
		$user_id = (int) $id_or_email;
	}elseif(is_object($id_or_email) && isset($id_or_email->user_id)) {
		$user_id = (int) $id_or_email->user_id;
	}else{
		$user = get_user_by('email', $id_or_email);
		if ($user) $user_id = $user->ID;
	}

	if($user_id)
	{
		$upload_dir = wp_upload_dir();

		$avatar_url = get_user_meta($user_id, 'kh_avatar', true);
		$avatar_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $avatar_url);

		if($avatar_url)
		{
			$thumb_width = !empty($data['width']) ? $data['width'] : 96;
			$thumb_height = !empty($data['height']) ? $data['height'] : 96;
			$thumb_path = preg_replace('/\.(?=[^\.]+$)/', "_{$thumb_width}x{$thumb_height}.", $avatar_path);
			$thumb_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $thumb_path);

			if(!file_exists($thumb_path))
			{
				$image_editor = wp_get_image_editor($avatar_path);
				if(!is_wp_error($image_editor))
				{
					$image_editor->resize($thumb_width, $thumb_height, true);
					$image_editor->save($thumb_path);
				}
			}

			$data['url'] = esc_url($thumb_url);
			$data['width'] = $thumb_width;
			$data['height'] = $thumb_height;
		}
	}

	return $data;
}
add_filter('pre_get_avatar_data', 'kh_pre_get_avatar_data', 10, 2);
