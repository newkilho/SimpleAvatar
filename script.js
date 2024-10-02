jQuery(function($){
	$('#your-profile').attr('enctype', 'multipart/form-data');
	$('#kh-avatar-file').on('change', function(event) {
		var reader = new FileReader();
		reader.readAsDataURL(event.target.files[0]);
		reader.onload = function(e) {
			$('.kh-avatar-img').attr('src', e.target.result).removeAttr('srcset');
		};
	});
	$('.kh-avatar-img').on('click', function() {
		$('#kh-avatar-file').click();
	});
});
