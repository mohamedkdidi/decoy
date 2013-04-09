<?php namespace Bkwld\Decoy\Controllers;

// Dependencies
use \Config;
use \Decoy_Auth;
use \Exception;
use \Former;
use \Input;
use \Redirect;
use \Section;
use \Sentry;
use \Session;
use \URL;
use \View;

// The account controller deals with auth
class Account extends Base {
	
	// Validation rules for resetting password
	static private $reset_rules = array(
		'rules' => array(
			'email' => 'required|email',
			'password' => 'required',
			'password_repeat' => 'required|same:password'
		), 'messages' => array(
			'same' => 'The passwords do not match'
		)
	);
	
	// Redirect to the profile managament page
	public function index() {
		return Redirect::to(Decoy_Auth::user_url());
	}

	// Login Functionality.  Users can get bounced here by a filter in routes.php.
	public function login() {

		// Remember where they attempted to go to if they were dropped here from a
		// ACL deny.  This keeps the value around for another request
		Session::keep('login_redirect');
		
		// If the user is logged in, take them to whatever the dashboard should be
		if (Decoy_Auth::check()) return Redirect::action(Config::get('decoy::post_login_redirect'));
		
		// Pass validation rules
		Former::withRules(array(
			'email' => 'required|email',
			'password' => 'required',
		));
		
		// Show the login homepage
		View::inject('title', 'Login');
		$this->layout->nest('content', 'decoy::account.login');
	}
	
	// Handle login submissions
	public function post() {
		try {
			
			// Attempt to login
			Sentry::authenticate(array(
				'email' => Input::get('email'),
				'password' => Input::get('password'),
			), Input::get('is_remember') == 1);
			
			// Login must have succeeded
			return Redirect::to(Session::get('login_redirect', URL::current()));

		// Make more easily read errros
		} catch (\Cartalyst\Sentry\Users\LoginRequiredException $e) {
			return $this->loginError('Email is required.');
		} catch (\Cartalyst\Sentry\Users\PasswordRequiredException $e) {
			return $this->loginError('Password is required.');
		} catch (\Cartalyst\Sentry\Users\UserNotFoundException $e) {
			return $this->loginError('Those credentials could not be found.');
		} catch (\Cartalyst\Sentry\Throttling\UserSuspendedException $e) {
			return $this->loginError('Your ability to login has been suspended for '.Config::get('cartalyst/sentry::sentry.throttling.suspension_time').' minutes.');
		
		// Handle other errrors
		} catch (Exception $e) {			
			return $this->loginError($e->getMessage());
		}

	}

	// Logout Functionality
	public function logout() {
		Sentry::logout();
		
		// I've gotten errors when going directly to this route
		try { 
			return Redirect::back();
		} catch(Exception $e) {
			return Redirect::to('/'.Config::get('decoy::dir'));
		}
		
	}
	
	// Show forgot password page
	public function forgot() {
		
		// Pass validation rules
		Former::withRules(self::$reset_rules['rules']);

		// Show the page
		Section::inject('title', 'Forgot Password');
		$this->layout->nest('content', 'decoy::account.forgot');
		
		// Set the breadcrumbs
		$this->breadcrumbs(array(
			action('decoy::') => 'Login',
			URL::current() => 'Forgot Password',
		));
		
	}
	
	// Email the link to recover their password.
	public function forget() {
		
		// Validate
		if ($result = $this->validate(self::$reset_rules['rules'], self::$reset_rules['messages'])) return $result;

		// Generate the email link
		try {
			if (!($reset = Sentry::reset_password(Input::get('email'), Input::get('password')))) {
				return $this->loginError('There was an error');
			}
			
			// Form the link
			$url = action('decoy::account@reset', array($reset['link']));
			
			// Send an email to the user with the reset token
			$mail = Message::to(Input::get('email'))
			->from(Config::get('decoy::decoy.mail_from_address'), Config::get('decoy::decoy.mail_from_name'))
			->subject('Recover access to the '.Config::get('decoy::decoy.site_name').' CMS')
			->body('Follow this link to activate the new password you set for yourself: <a href="'.$url.'">'.$url.'</a>.  If you did not recently try to reset your password, then ignore this email.')
			->html(true)
			->send();
			
			// Handle sending errors
			if (!$mail->was_sent()) return loginError('The mail could not be sent');
			
			// Redirect back to login page
			return Redirect::to_action('decoy::account@forgot')
				->with('login_notice', 'An email with a link to reset your password has been sent.');
			
		// User could not be found	
		} catch (Sentry\SentryException $e) {
			return $this->loginError($e->getMessage());
		}
		
	}
	
	// Process the reset token and then redirect the user to the login page
	public function reset($login_token, $token) {
		
		// Validate their input
		try {
			$login = base64_decode($login_token); // Their email will come through like this
			if (!($confirm_reset = Sentry::reset_password_confirm($login_token, $token))) {
				return $this->loginError('There was an error', action('decoy::account@forgot'));
			}
			
			// Sign the user in
			try {
				
				// Getting an (int) ID manually because of this bug:
				// https://github.com/cartalyst/sentry/pull/47
				$id = Sentry::user($login)->get('id');
				Sentry::force_login((int) $id);
				
				// Redirect to the specified route
				return Redirect::action(Config::get('decoy::post_login_redirect'));
				
			// There was an error
			} catch (Sentry\SentryException $e) {
				return $this->loginError($e->getMessage(), action('decoy::account@forgot'));
			}
		
		// Token could not be matched
		} catch (Sentry\SentryException $e) {
			return $this->loginError($e->getMessage(), action('decoy::account@forgot'));
		}
	}

	// Redirect with a login error
	private function loginError($msg, $url = null) {
		return Redirect::to($url ? $url : URL::current())
		->with('login_error', $msg)
		->withInput();
	}

}