<?php
/*
Plugin Name: Paid Memberships Pro - Signup Shortcode
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-signup-shortcode/
Description: Shortcode for a simplified Membership Signup Form with options for email only signup and more. 
Version: .1
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

/*
	Use Email Address as Username and Generate a Password
*/
function pmprosus_skip_username_password()
{
	//copy email to username if no username field is present
	if(!empty($_REQUEST['bemail']) && !isset($_REQUEST['username']))
		$_REQUEST['username'] = $_REQUEST['bemail'];
	
	if(!empty($_POST['bemail']) && !isset($_POST['username']))
		$_POST['username'] = $_POST['bemail'];

	if(!empty($_GET['bemail']) && !isset($_GET['username']))
		$_GET['username'] = $_GET['bemail'];

	//autogenerate password if no field is present
	if(!empty($_REQUEST['bemail']) && !isset($_REQUEST['password']))
	{
		//genreate password
		$_REQUEST['password'] = pmpro_getDiscountCode() . pmpro_getDiscountCode();	//using two random discount codes
		$_REQUEST['password2'] = $_REQUEST['password'];

		//set flag so we add the password to the confirmation email later
		$_SESSION['pmprosus_autogenerated_password'] = $_REQUEST['password'];
	}
	if(!empty($_POST['bemail']) && !isset($_POST['password']))
	{
		$_POST['password'] = pmpro_getDiscountCode() . pmpro_getDiscountCode();	//using two random discount codes
		$_POST['password2'] = $_POST['password'];
	}
	if(!empty($_GET['bemail']) && !isset($_GET['password']))
	{
		$_GET['password'] = pmpro_getDiscountCode() . pmpro_getDiscountCode();	//using two random discount codes
		$_GET['password2'] = $_GET['password'];
	}
}
add_action('init', 'pmprosus_skip_username_password');

/*
	Add password to confirmation email if it was autogenerated
*/
function pmprosus_pmpro_email_data($data, $email) {
	if(!empty($_SESSION['pmprosus_autogenerated_password']) && strpos($email->template, 'checkout_') !== false) {
		$data['user_email'] = sprintf(__('Password: %s, Email: %s', 'pmprosus'), $_SESSION['pmprosus_autogenerated_password'], $data['user_email']);
	}

	return $data;
}
add_filter('pmpro_email_data', 'pmprosus_pmpro_email_data', 10, 2);

/*
	Make sure we load our version of the shortcode instead of the one bundled in Register Helper.
*/
function pmprosus_load_shortcode() {
	remove_shortcode('pmpro_signup');
	add_shortcode('pmpro_signup', 'pmprosus_signup_shortcode');
}
add_action('init', 'pmprosus_load_shortcode');

/*
	Save referrer to session
*/
function pmprosus_init_referrer() {
	if(!empty($_REQUEST['pmprosus_referrer'])) {
		$_SESSION['pmprosus_referrer'] = $_REQUEST['pmprosus_referrer'];
		$_SESSION['pmprosus_redirect_to'] = $_REQUEST['redirect_to'];
	}
}
add_action('init', 'pmprosus_init_referrer');

/*
	Redirect to referrer if set.
*/
function pmprosus_pmpro_confirmation_url($url, $user_id, $level) {
	global $post;
	
	//figure out referrer
	if(!empty($_REQUEST['pmprosus_referrer']))
		$referrer = $_REQUEST['pmprosus_referrer'];
	elseif(!empty($_SESSION['pmprosus_referrer']))
		$referrer = $_SESSION['pmprosus_referrer'];
	else
		$referrer = '';

	//figure out redirect_to
	if(!empty($_REQUEST['redirect_to']))
		$redirect = $_REQUEST['redirect_to'];
	elseif(!empty($_SESSION['pmprosus_redirect_to']))
		$redirect = $_SESSION['pmprosus_redirect_to'];
	else
		$redirect = '';

	//unset session vars
	unset($_SESSION['pmprosus_referrer']);
	unset($_SESSION['pmprosus_redirect_to']);

	//save referrer to user meta
	update_user_meta($user_id, 'pmprosus_referrer', $referrer );

	//change confirmation URL to redirect if set
	if($redirect) {
		$url = $redirect;
	}

	return $url;
}
add_filter('pmpro_confirmation_url', 'pmprosus_pmpro_confirmation_url', 10, 3);

/*
	This shortcode will show a signup form with account fields based on attributes.
	
	If the level is not free, the user will be taken to the membership checkout
	page to enter billing information.
*/
function pmprosus_signup_shortcode($atts, $content=null, $code="")
{
	// $atts    ::= array of attributes
	// $content ::= text within enclosing form of shortcode element
	// $code    ::= the shortcode found, when == callback name
	// examples: [pmpro_signup level="3" short="1" intro="0" submit_button="Signup Now"]

	//make sure PMPro is activated
	if(!function_exists('pmpro_getLevel'))
		return "Paid Memberships Pro must be installed to use the pmpro_signup shortcode.";

	//set defaults
	extract(shortcode_atts(array(
		'intro' => "0",
		'level' => NULL,
		'login' => true,
		'redirect' => NULL,
		'short' => NULL,
		'submit_button' => __("Sign Up Now", 'pmprosus'),
		'title' => NULL,
	), $atts));
	
		
	// set title
	if($title === "1" || $title === "true" || $title === "yes")
		$title_display = true;

	if(isset($title_display))
		if(!empty($level))
			$title = 'Register For ' . pmpro_getLevel($level)->name;
		else
			$title = 'Register For ' . get_option('blogname');
	
	//turn 0's into falses
	if($login === "0" || $login === "false" || $login === "no")
		$login = false;
	else
		$login = true;

	//check which form format is specified
	if($intro === "0" || $intro === "false" || $intro === "no")
		$intro = false;

	if($short === "1" || $short === "true" || $short === "yes")
		$short = true;
	elseif($short === "emailonly")
		$short = "emailonly";
	else
		$short = false;
		
	global $current_user, $membership_levels, $pmpro_pages;	
	
	ob_start();
	?>
		<?php if(!empty($current_user->ID) && pmpro_hasMembershipLevel($level,$current_user->ID)) { ?>
			<?php 
				if(current_user_can("manage_options") )
				{
					?>
					<div class="pmpro_message pmpro_alert"><?php _e('&#91;pmpro_signup&#93; Admin Only Shortcode Alert: You are logged in as an administrator and already have the membership level specified.', 'pmprosus'); ?></div>
					<?php
				}
			?>
		<?php } else { ?>
		<form class="pmpro_form pmpro_signup_form" action="<?php echo pmpro_url("checkout"); ?>" method="post">
			<?php
				if(!empty($title))
					echo '<h2>' . $title . '</h2>';
			?>
			<?php
				if(!empty($intro))
					echo wpautop($intro);
			?>
			<input type="hidden" id="level" name="level" value="<?php echo $level; ?>" />
			<input type="hidden" id="pmpro_signup_shortcode" name="pmpro_signup_shortcode" value=1>
			<?php do_action( 'pmpro_signup_form_before_fields' ); ?>
			<?php
				if(!empty($current_user->ID))
				{
					?>
					<p id="pmpro_account_loggedin">
						<?php printf(__('You are logged in as <strong>%s</strong>. If you would like to use a different account for this membership, <a href="%s">log out now</a>.', 'pmprosus'), $current_user->user_login, wp_logout_url($_SERVER['REQUEST_URI'])); ?>
					</p>
					<?php
				}
				else
				{
					?>
					<?php if( $short !== 'emailonly') { ?>
					<div>
						<label for="username"><?php _e('Username', 'pmprosus');?></label>
						<input id="username" name="username" type="text" class="input" size="30" value="" />
					</div>
					<?php } ?>
					<?php do_action("pmpro_checkout_after_username");?>
					<?php if( $short !== 'emailonly') { ?>
					<div>
						<label for="password"><?php _e('Password', 'pmprosus');?></label>
						<input id="password" name="password" type="password" class="input" size="30" value="" />
					</div>
					<?php } ?>
					<?php if( !empty($short) ) { ?>
						<input type="hidden" name="password2_copy" value="1" />
					<?php } else { ?>
						<div>
							<label for="password2"><?php _e('Confirm Password', 'pmprosus');?></label>
							<input id="password2" name="password2" type="password" class="input" size="30" value="" />
						</div>
					<?php } ?>
					<?php do_action("pmpro_checkout_after_password");?>
					<div>
						<label for="bemail"><?php _e('E-mail Address', 'pmprosus');?></label>
						<input id="bemail" name="bemail" type="email" class="input" size="30" value="" />
					</div>
					<?php if( !empty($short) ) { ?>
						<input type="hidden" name="bconfirmemail_copy" value="1" />
					<?php } else { ?>
						<div>
							<label for="bconfirmemail"><?php _e('Confirm E-mail', 'pmprosus');?></label>
							<input id="bconfirmemail" name="bconfirmemail" type="email" class="input" size="30" value="" />
						</div>
					<?php } ?>
					<input type="hidden" name="pmprosus_referrer" value="<?php echo esc_attr($_SERVER['REQUEST_URI']);?>" />
					<?php 
						if($redirect == 'referrer') 
							$redirect_to = $_SERVER['REQUEST_URI'];
						elseif($redirect == 'account')
							$redirect_to = get_permalink($pmpro_pages['account']);
						elseif(empty($redirect) )
							$redirect_to = '';
						else
							$redirect_to = $redirect;
					?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to);?>" />
					<?php do_action("pmpro_checkout_after_email");?>
					<div class="pmpro_hidden">
						<label for="fullname"><?php _e('Full Name', 'pmprosus');?></label>
						<input id="fullname" name="fullname" type="text" class="input" size="30" value="" /> <strong><?php _e('LEAVE THIS BLANK', 'pmprosus');?></strong>
					</div>
					<?php
						global $recaptcha, $recaptcha_publickey;							
						if($recaptcha == 2 || (!empty($level) && $recaptcha == 1 && pmpro_isLevelFree(pmpro_getLevel($level))))
						{
							?>
							<div class="pmpro_captcha">
								<?php echo pmpro_recaptcha_get_html($recaptcha_publickey, NULL, true); ?>
							</div> <!-- end pmpro_captcha -->
							<?php
						}
					?>
					<?php
				}
			?>
			<?php do_action( 'pmpro_signup_form_before_submit' ); ?>
			<div>
				<span id="pmpro_submit_span" >
					<input type="hidden" name="submit-checkout" value="1" />
					<input type="submit" class="pmpro_btn pmpro_btn-submit-checkout" value="<?php echo $submit_button; ?>" />
				</span>
			</div>
			<?php if(!empty($login) && empty($current_user->ID)) { ?>
			<div style="text-align:center;">
				<a href="<?php echo wp_login_url(get_permalink()); ?>"><?php _e('Log In','pmpro'); ?></a>
			</div>
			<?php } ?>
			<?php do_action( 'pmpro_signup_form_after_submit' ); ?>
		</form>
		<?php do_action( 'pmpro_signup_form_after_form' ); ?>
		<?php } ?>
	<?php
	$temp_content = ob_get_contents();
	ob_end_clean();
	return $temp_content;
}

/*
Function to add links to the plugin row meta
*/
function pmprosus_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-signup-shortcode.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('http://www.paidmembershipspro.com/add-ons/plus-add-ons/pmpro-signup-shortcode/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url('http://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmprosus_plugin_row_meta', 10, 2);
