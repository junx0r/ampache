<?php
	require_once '../../lib/init.php';
        require_once( Config::get('prefix') . "/modules/twitter/twitteroauth/twitteroauth.php");

	session_start();
	
	if(!empty($_SESSION['twitterusername'])) {
		header('Location: ' . Config::Get('web_path') . '/modules/twitter/twitter_update.php');
	}

	if(!empty($_GET['oauth_verifier']) && !empty($_SESSION['oauth_token']) && !empty($_SESSION['oauth_token_secret'])){
	
		// Good to go
	} else {
		// Something's missing, go back to square 1
		header('Location: ' . Config::Get('web_path') . '/modules/twitter/twitter_login.php');
	}

	// TwitterOAuth instance, with two new parameters we got in twitter_login.php
	$twitteroauth = new TwitterOAuth( Config::get('twitter_consumer_key'), Config::get('twitter_consumer_secret'), $_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);

	// Let's request the access token
	$access_token = $twitteroauth->getAccessToken($_GET['oauth_verifier']);

	// Save it in a session var
	$_SESSION['access_token'] = $access_token;

	// Let's get the user's info
	$user_info = $twitteroauth->get('account/verify_credentials');
	
	// Print user's info
//	print_r($user_info);

	print "{$user_info->id}";
	echo '<br>';
	print "{$user_info->screen_name}";
	echo '<br>';
	print "access token:" . $access_token['oauth_token'] . "\n";
	echo '<br>';
	print "access token secret:" .  $access_token['oauth_token_secret'] . "\n";
	echo '<br>';
	
	// $twitteroauth->post('statuses/update', array('status' => 'Does it work?'));


        if(isset($user_info->error)){

                // Something's wrong, go back to square 1
		session_destroy();
//                header('Location: ' . Config::Get('web_path') . '/modules/twitter/twitter_error.php');
		echo "Session killed";
        } else {
		
		$link = mysql_connect(Config::get('database_hostname'), Config::get('database_username') , Config::get('database_password') );
        	mysql_select_db( Config::get('database_name') , $link);
                
		// Let's find the user by its ID
                $query = mysql_query("SELECT * FROM twitter_users WHERE oauth_provider = 'twitter' AND oauth_uid = ". $user_info->id . " AND ampache_id = " . $_SESSION['userdata']['uid']) or die( mysql_error() );
                $result = mysql_fetch_array($query);

		print_r($result);
		echo "<br>ampache_id: {$_SESSION['userdata']['uid']}";
		echo "<br>oauth_uid: {$user_info->id}";
		echo "<br>oauth_token: {$access_token['oauth_token']}";
		echo "<br>oauth_secret: {$access_token['oauth_token_secret']}";
		echo "<br>username: {$user_info->screen_name} <br>";

                // If not, let's add it to the database
                if(empty($result)){
			$query = mysql_query("INSERT INTO twitter_users (ampache_id, oauth_provider, oauth_uid, oauth_token, oauth_secret, username) VALUES ( '{$_SESSION['userdata']['uid']}', 'twitter', '{$user_info->id}', '{$access_token['oauth_token']}', '{$access_token['oauth_token_secret']}', '{$user_info->screen_name}')") or die( mysql_error() );

                        $query = mysql_query("SELECT * FROM twitter_users WHERE username = '" . $user_info->screen_name . "' AND ampache_id = " . $_SESSION['userdata']['uid'] ) or die( mysql_error() );
                        $result = mysql_fetch_array($query);
			echo "insert: ";
			print_r($result);
			echo "<br>";
                } else {
                        // Update the tokens
                        $query = mysql_query("UPDATE twitter_users SET oauth_token = '{$access_token['oauth_token']}', oauth_secret = '{$access_token['oauth_token_secret']}' WHERE oauth_provider = 'twitter' AND oauth_uid = {$user_info->id} AND ampache_id = {$_SESSION['userdata']['uid']}") or die( mysql_error() );
			$query = mysql_query("SELECT * FROM twitter_users WHERE username = '" . $user_info->screen_name . "'") or die( mysql_error() );
                        $result = mysql_fetch_array($query);
			echo "update/select";
			print_r($result);
			echo "<br>";
                }

	        $_SESSION['id'] = $result['id'];
        	$_SESSION['twitterusername'] = $result['username'];
        	$_SESSION['oauth_uid'] = $result['oauth_uid'];
        	$_SESSION['oauth_provider'] = $result['oauth_provider'];
        	$_SESSION['oauth_token'] = $result['oauth_token'];
        	$_SESSION['oauth_secret'] = $result['oauth_secret'];

		mysql_close($link);

		header('Location: ' . Config::get('web_path') . '/modules/twitter/twitter_update.php');
		echo "session twitterusername: " . $_SESSION['twitterusername'] . "<br>";
		echo 'got here';
        }
?>
