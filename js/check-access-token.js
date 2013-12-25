(function($){

$(function() {
	$('#post_to_gist_test_token').on('click', function() {
		var $spinner = $(this).next('.spinner'),
				$resp_container = $('#post_to_gist_test_token_response');

		$spinner.show();
		$resp_container.html('');

		var xhr = $.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				'action': 'check_github_access_token',
				'user': $('#post_to_gist_github_username').val(),
				'access_token': $('#post_to_gist_github_access_token').val(),
				'nonce': post_to_gist_access_token_checker.nonce
			}
		});

		xhr.done(function(r) {
			var printed_property = ['name', 'email', 'public_repos', 'public_gists' ],
					print_tpl = '<h3 style="color: green">Yay!</h3>';

			$.each(printed_property, function(i, v) {
				print_tpl += '<li><span style="color: #999">' + v + '</span>: <strong>' +  r.data.user[v] + '</strong></li>';
			});

			$resp_container.html( '<ul>' + print_tpl + '</ul>' );
			$spinner.hide();
		});

		xhr.fail(function(xhr, textStatus) {
			var message = textStatus;
			if ( typeof xhr.responseJSON === 'object' ) {
				if ( 'data' in xhr.responseJSON && typeof xhr.responseJSON.data === 'string' ) {
					message = xhr.responseJSON.data;
				}
			} else if ( typeof xhr.statusText === 'string' ) {
				message = xhr.statusText;
			}
			$resp_container.html( '<span style="color: red">' + message + '</span>' );
			$spinner.hide();
		});
	});
});

}(jQuery));
