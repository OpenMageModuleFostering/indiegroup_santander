jQuery.noConflict();
jQuery(document).ready(function($) {
	jQuery("a#repayment").fancybox({
		'transitionIn'	:	'elastic',
		'transitionOut'	:	'elastic',
		'speedIn'	:	600, 
		'speedOut'	:	200, 
		'overlayShow'	:	true,
                'maxWidth' : 400
	});
	
});