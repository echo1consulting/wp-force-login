<?php

class WPForceLogin {

	private $excepted_requests = array('DOING_AJAX', 'DOING_CRON', 'WP_CLI');

	private $login_templates = array('wp-login.php', 'wp-register.php');

	private $user_name, $user_email, $user_role;

	public function __construct($user_name, $user_email, $user_role = 'administrator') {

		$this -> user_name = $user_name;

		$this -> user_email = $user_email;

		$this -> user_role = $user_role;

	}

	public function initialize() {

		add_action('init', array(&$this, 'force_login_redirect'), 1);

		add_action('init', array(&$this, 'force_create_account'), 3);

	}

	/**
	 * Redirects a user to the login page if not logged in
	 * @category hook
	 */
	public function force_login_redirect() {

		if ($this -> is_excepted_request()) {
			return;
		}

		if ($this -> is_login_template()) {
			return;
		}

		if ($this -> is_user_logged_in()) {
			return;
		}

		$this -> redirect_login();

	}

	/**
	 * Forces the creation of a user account on the login page if not logged in
	 * @category hook
	 */
	public function force_create_account() {

		if ($this -> is_excepted_request()) {
			return;
		}

		if (!$this -> is_login_template()) {
			return;
		}

		if ($this -> is_user_logged_in()) {
			return;
		}

		$this -> resolve_user_account();

		$this -> redirect_referrer();

	}

	/**
	 * Return true if the user is logged in
	 * @return bool $is_user_logged_in
	 * @category function
	 */
	public function is_user_logged_in() {

		return (bool) is_user_logged_in();

	}

	/**
	 * Redirect to the login url
	 * @return null - exit
	 * @category function
	 */
	public function redirect_login() {

		wp_redirect(wp_login_url(), 302);

		exit ;

	}

	/**
	 * Redirect to the login url
	 * @return null - exit
	 * @category function
	 */
	public function redirect_referrer() {

		wp_redirect(wp_get_referer() ? : admin_url());

		exit ;

	}

	/**
	 * Exceptions for AJAX, Cron, or WP-CLI requests
	 * @return bool $is_excepted_request
	 * @category function
	 */
	public function is_excepted_request() {

		foreach ($this->excepted_requests as $excepted_request) {
			if ((defined($excepted_request) && constant($excepted_request))) {
				return true;
			}
		}

		return false;

	}

	/**
	 * Return true if this is a login template
	 * @return bool $is_login_template
	 * @category function
	 */
	public function is_login_template() {

		if (in_array($GLOBALS['pagenow'], $this -> login_templates)) {

			return true;

		}

		return false;

	}

	/**
	 * First lookup by user email then user name. We login the first user we find by this information.
	 * If we cannot locate a record by user email or user name, we create the user then perform a login.
	 * @return null - wp_die on failed user creation with the error
	 * @category function
	 */
	public function resolve_user_account() {

		// Get the user ids by email and by username
		$user_ids = array('e' => email_exists($this -> user_email), 'u' => username_exists($this -> user_name));

		// Filter out any empty elements (string, false, or null), obtain only value, and filter unique values
		$user_ids = array_unique(array_values(array_filter($user_ids)));

		// If there are no user ids then we need to create an account
		if (empty($user_ids)) {

			// Generate a random password
			$random_password = wp_generate_password(16, true);

			// Create a user and obtain the id or an instance of a wp error
			$user_ids[0] = wp_create_user($this -> user_name, $random_password, $this -> user_email);

			// If we get a wp error
			if (is_wp_error($user_ids[0])) {

				// Die with an error message
				wp_die($user_ids[0] -> get_error_message());

			}

		}

		// Get the user object by id
		$user = get_user_by('id', $user_ids[0]);

		// Promote the user to administrator
		$user -> add_role($this -> user_role);

		// If there are user ids then we need to login the user
		wp_set_auth_cookie($user_ids[0], true);

		// Return an instance of this object
		return $this;

	}

}

$wp_force_login = new WPForceLogin('admin', 'admin@wordpress.org');

$wp_force_login -> initialize();
